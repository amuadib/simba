<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserEdit extends Command
{
    protected $signature = 'user:edit
        {email}
        {--name=}
        {--new-email=}
        {--password=}';

    protected $description = 'Edit existing user';

    public function handle()
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('User not found');
            return;
        }

        if ($this->option('name')) {
            $user->name = $this->option('name');
        }

        if ($this->option('new-email')) {
            $user->email = $this->option('new-email');
        }

        if ($this->option('password')) {
            $user->password = Hash::make($this->option('password'));
        }

        $user->save();

        $this->info("User updated: {$user->email}");
    }
}
