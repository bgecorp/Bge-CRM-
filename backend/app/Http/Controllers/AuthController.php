<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Sign-in with email and password; each sign-in gets its own API token. */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            LoginLog::record('failed', $request, $user, $data['email']);
            throw ValidationException::withMessages(['email' => 'Wrong email or password.']);
        }
        if (! $user->active) {
            LoginLog::record('failed', $request, $user);
            throw ValidationException::withMessages(['email' => 'This account is turned off. Ask the super admin.']);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        LoginLog::record('login', $request, $user);

        return [
            'token' => $user->createToken('crm', [$user->role])->plainTextToken,
            'user' => $this->present($user),
        ];
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        LoginLog::record('logout', $request, $user);
        $user->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return $this->present($request->user());
    }

    private function present(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'role', 'sales_rep_id']) + ['sales_rep' => $user->salesRep?->name];
    }
}
