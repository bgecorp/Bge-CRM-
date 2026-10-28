<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// One-time setup: create the first super admin (asks for name, email and password).
Artisan::command('crm:create-admin', function () {
    $name = $this->ask('Name');
    $email = $this->ask('Email');
    $password = $this->secret('Password (at least 8 characters, hidden while typing)');

    $validator = Illuminate\Support\Facades\Validator::make(
        compact('name', 'email', 'password'),
        ['name' => 'required', 'email' => 'required|email|unique:users,email', 'password' => 'required|min:8'],
    );
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    App\Models\User::create(compact('name', 'email', 'password') + ['role' => App\Models\User::SUPER_ADMIN]);
    $this->info("Super admin {$email} created. Sign in at the CRM page.");
})->purpose('Create a super admin account');
