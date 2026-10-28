<?php

use App\Http\Controllers\AssistantController;
use App\Http\Middleware\EnsureAssistantToken;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

// Kept apart from routes/api.php so the assistant can edit the app's routes but never its own guard.
// Super admin only: the assistant can change data and code.
Route::prefix('api/assistant')->middleware(['api', 'auth:sanctum', EnsureSuperAdmin::class, EnsureAssistantToken::class])->group(function () {
    Route::get('/status', [AssistantController::class, 'status']);
    Route::get('/conversations', [AssistantController::class, 'index']);
    Route::post('/conversations', [AssistantController::class, 'store']);
    Route::get('/conversations/{conversation}', [AssistantController::class, 'show']);
    Route::delete('/conversations/{conversation}', [AssistantController::class, 'destroy']);
    Route::post('/conversations/{conversation}/messages', [AssistantController::class, 'message']);
    Route::post('/conversations/{conversation}/resolve', [AssistantController::class, 'resolve']);
    Route::get('/notes', [AssistantController::class, 'notes']);
    Route::post('/notes', [AssistantController::class, 'storeNote']);
    Route::delete('/notes/{note}', [AssistantController::class, 'destroyNote']);
    Route::get('/changes', [AssistantController::class, 'changes']);
});
