<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    protected $signature = 'vps:create-admin 
                            {--email= : Admin email} 
                            {--password= : Admin password} 
                            {--name= : Administrator name}';

    protected $description = 'Create or reset an administrator account for the VPS control panel';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Enter administrator email', 'admin@example.com');
        $name = $this->option('name') ?: $this->ask('Enter administrator name', 'Super Admin');
        $password = $this->option('password') ?: $this->secret('Enter administrator password (leave blank to generate random)');

        if (empty($password)) {
            $password = Str::password(16);
            $this->warn("Generated random password: {$password}");
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => UserRole::SuperAdmin,
                'is_active' => true,
            ]
        );

        $this->info("Administrator account [{$user->email}] configured successfully!");
        return self::SUCCESS;
    }
}
