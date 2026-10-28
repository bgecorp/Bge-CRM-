<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Follow-ups are set by people only; nothing is scheduled automatically. */
class FollowUpController extends Controller
{
    public function index(Request $request)
    {
        $today = today()->toDateString();
        $base = FollowUp::query()
            ->when($request->filled('sales_rep_id'), fn ($q) => $q->where('sales_rep_id', $request->sales_rep_id))
            ->when($request->filled('store_id'), fn ($q) => $q->where('store_id', $request->store_id));

        $buckets = [
            'overdue' => fn ($q) => $q->where('status', 'pending')->where('due_date', '<', $today)->orderBy('due_date'),
            'today' => fn ($q) => $q->where('status', 'pending')->where('due_date', $today)->orderBy('id'),
            'upcoming' => fn ($q) => $q->where('status', 'pending')->where('due_date', '>', $today)->orderBy('due_date'),
            'completed' => fn ($q) => $q->where('status', 'completed')->orderByDesc('completed_at'),
        ];

        $bucket = array_key_exists($request->input('bucket'), $buckets) ? $request->input('bucket') : 'today';

        $items = $buckets[$bucket]((clone $base))
            ->with(['store:id,name,contact_person,phone,messenger_url,facebook_url', 'salesRep:id,name'])
            ->limit(500)
            ->get();

        $counts = collect($buckets)
            ->except('completed')
            ->map(fn ($apply) => $apply((clone $base))->count());

        return ['items' => $items, 'counts' => $counts];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'due_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'next_action' => ['nullable', 'string', 'max:255'],
        ]);

        $store = Store::findOrFail($data['store_id']);
        $followUp = FollowUp::create($data + [
            'type' => 'manual',
            'status' => 'pending',
            'sales_rep_id' => $store->sales_rep_id,
            'reason' => $data['reason'] ?? 'Manual follow-up',
        ]);

        return response()->json($followUp, 201);
    }

    /**
     * Record what happened on a contact, and optionally set the next contact date.
     */
    public function complete(Request $request, FollowUp $followUp)
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::in(FollowUp::OUTCOMES)],
            'outcome_notes' => ['nullable', 'string'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'next_order_date' => ['nullable', 'date'],
            'next_contact_date' => ['nullable', 'date', 'after:today'],
        ]);

        abort_if($followUp->status !== 'pending', 422, 'This follow-up is already closed.');

        return DB::transaction(function () use ($data, $followUp) {
            $followUp->update([
                'status' => 'completed',
                'outcome' => $data['outcome'],
                'outcome_notes' => $data['outcome_notes'] ?? null,
                'next_action' => $data['next_action'] ?? null,
                'next_order_date' => $data['next_order_date'] ?? null,
                'completed_at' => now(),
            ]);

            $store = $followUp->store;

            if (! empty($data['next_contact_date'])) {
                FollowUp::create([
                    'store_id' => $store->id,
                    'sales_rep_id' => $store->sales_rep_id,
                    'due_date' => $data['next_contact_date'],
                    'type' => 'manual',
                    'status' => 'pending',
                    'reason' => 'Scheduled after the customer said "'.str_replace('_', ' ', $data['outcome']).'"',
                    'next_action' => $data['next_action'] ?? null,
                ]);
            }

            $next = $store->followUps()->where('status', 'pending')->orderBy('due_date')->first();

            return ['completed' => $followUp->fresh(), 'next' => $next];
        });
    }
}
