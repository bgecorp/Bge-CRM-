<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class LoginLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'email', 'event', 'ip_address', 'user_agent'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $event, Request $request, ?User $user, ?string $email = null): void
    {
        static::create([
            'user_id' => $user?->id,
            'email' => $user?->email ?? $email,
            'event' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
