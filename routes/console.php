<?php

use Illuminate\Support\Facades\Schedule;

// Automatic SSL Renewal check (daily at 03:00)
Schedule::command('vps:ssl-renew')->dailyAt('03:00');

// Scheduled automated backups (hourly check)
Schedule::command('vps:backups-run')->hourly();
