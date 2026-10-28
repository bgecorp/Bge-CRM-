<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantConversation extends Model
{
    protected $fillable = ['title', 'messages', 'pending'];

    protected $casts = [
        'messages' => 'array',
        'pending' => 'array',
    ];
}
