<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdminCommand extends Command
{
    protected $signature = 'panel:admin {--email=} {--password=} {--name=Admin}';
    protected $description = 'Create or reset the VPS Control Panel admin credentials';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Admin Email Address', 'admin@vps-panel.local');
        $name = $this->option('name') ?: 'VPS Administrator';
        $password = $this->option('password') ?: $this->secret('Admin Password (leave blank for random)', null);

        if (!$password) {
            $password = Str::password(12, symbols: false);
            $this->info("Generated Random Password: {$password}");
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ]
        );

        $this->newLine();
        $this->info('==============================================');
        $this->info('   VPS PANEL ADMIN CREDENTIALS CONFIGURED');
        $this->info('==============================================');
        $this->line("   Email:    <comment>{$user->email}</comment>");
        $this->line("   Password: <comment>{$password}</comment>");
        $this->info('==============================================');

        return Command::SUCCESS;
    }
}
