<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserCreate extends Command
{
    protected $signature = 'user:create
        {name}
        {email}
        {--password=}';

    protected $description = 'Create new user';

    public function handle()
    {
        $password = $this->option('password') ?? $this->secret('Password');

        $user = User::create([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => Hash::make($password),
        ]);

        $this->info("User created: {$user->email}");
    }
}
