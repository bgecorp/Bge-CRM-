<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\SalesRep;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Accounts, managed by the super admin. */
class UserController extends Controller
{
    public function index()
    {
        return User::with('salesRep:id,name')->orderBy('role', 'desc')->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'sales_rep_id', 'active', 'last_login_at', 'created_at']);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
        ]);

        return response()->json(User::create($this->withSalesRep($data))->load('salesRep:id,name'), 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate($this->rules() + [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            // Leave blank to keep the current password.
            'password' => ['nullable', Password::min(8)],
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $this->keepOneSuperAdmin($request, $user, $data);
        $user->update($this->withSalesRep($data));

        // A turned-off account, or a new password, signs that person out everywhere else.
        if (! $user->active || isset($data['password'])) {
            $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()?->id)->delete();
        }

        return $user->load('salesRep:id,name');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot delete your own account.');
        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }

    /** Sign-in and sign-out history, newest first. */
    public function loginLogs(Request $request)
    {
        return LoginLog::with('user:id,name,role')
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->event))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(500)
            ->get();
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(User::ROLES)],
            'active' => ['boolean'],
        ];
    }

    /**
     * A seller is listed in the sales team under their name (created if new),
     * so customers can be assigned to them. Other roles are not linked.
     */
    private function withSalesRep(array $data): array
    {
        $data['sales_rep_id'] = $data['role'] === User::REPRESENTATIVE
            ? SalesRep::firstOrCreate(['name' => trim($data['name'])])->id
            : null;

        return $data;
    }

    /** The super admin cannot demote or turn off themself, so the CRM always has one. */
    private function keepOneSuperAdmin(Request $request, User $user, array $data): void
    {
        if ($user->id !== $request->user()->id) {
            return;
        }
        abort_if(($data['role'] ?? $user->role) !== User::SUPER_ADMIN, 422, 'You cannot remove your own super admin role.');
        abort_if(array_key_exists('active', $data) && ! $data['active'], 422, 'You cannot turn off your own account.');
    }
}
