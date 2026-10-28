<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantChange extends Model
{
    protected $fillable = ['conversation_id', 'tool', 'summary', 'target', 'backup_path', 'status', 'result'];
}
