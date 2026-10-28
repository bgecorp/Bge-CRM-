<?php

namespace App\Http\Controllers;

use App\Models\SalesRep;
use Illuminate\Http\Request;

class SalesRepController extends Controller
{
    public function index()
    {
        return SalesRep::withCount('stores')->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        return response()->json(SalesRep::create($this->validated($request)), 201);
    }

    public function update(Request $request, SalesRep $salesRep)
    {
        $salesRep->update($this->validated($request));

        return $salesRep;
    }

    public function destroy(SalesRep $salesRep)
    {
        $salesRep->delete();

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
