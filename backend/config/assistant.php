<?php

return [
    // Claude API (Anthropic PHP SDK).
    'api_key' => env('ANTHROPIC_API_KEY'),

    'model' => env('ASSISTANT_MODEL', 'claude-opus-5-5'),

    // low | medium | high | xhigh | max. Higher = more careful, slower, more tokens.
    'effort' => env('ASSISTANT_EFFORT', 'medium'),

    // Longest single reply, thinking included.
    'max_tokens' => 16000,

    // Longest tool result sent back to the model, in characters.
    'max_result_chars' => 8000,

    // Model calls per chat request before the assistant pauses and asks to continue.
    'max_steps' => 12,

    // Access code required on every /api/assistant request (header X-Assistant-Token). Empty = no code.
    'token' => env('ASSISTANT_TOKEN'),

    // Code edits are only ever possible on a local development machine, and only when enabled.
    'allow_code_edits' => env('ASSISTANT_ALLOW_CODE_EDITS', false) && env('APP_ENV') === 'local',

    // Project root (the folder holding backend/ and frontend/).
    'project_root' => realpath(base_path('..')) ?: base_path('..'),

    // Paths (relative to project_root) the assistant may read.
    'readable' => [
        'README.md',
        'backend/app', 'backend/routes', 'backend/database', 'backend/config', 'backend/resources',
        'backend/tests', 'backend/bootstrap/app.php', 'backend/composer.json',
        'frontend/src', 'frontend/index.html', 'frontend/package.json', 'frontend/vite.config.js',
    ],

    // Paths the assistant may change (each change still needs approval).
    'editable' => [
        'backend/app', 'backend/routes', 'backend/database/migrations', 'backend/database/seeders',
        'backend/resources', 'backend/tests', 'frontend/src',
    ],

    // Never readable or editable, even inside the folders above.
    'blocked' => [
        '.env', 'vendor', 'node_modules', 'storage', '.git', 'dist',
    ],

    // Readable but never editable: the assistant must not be able to loosen its own safeguards.
    'protected' => [
        'backend/app/Services/Assistant',
        'backend/app/Http/Controllers/AssistantController.php',
        'backend/app/Http/Middleware/EnsureAssistantToken.php',
        // Sign-in and roles: the assistant must not be able to loosen who can do what.
        'backend/app/Http/Middleware/EnsureSuperAdmin.php',
        'backend/app/Http/Controllers/AuthController.php',
        'backend/app/Http/Controllers/UserController.php',
        'backend/routes/api.php',
        'backend/config/sanctum.php',
        'backend/config/assistant.php',
        'backend/bootstrap/app.php',
        'backend/routes/assistant.php',
    ],
];
