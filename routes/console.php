<?php

use Illuminate\Support\Facades\Schedule;

// Both commands check the switches on the Settings page, so running them daily is safe.
Schedule::command('billing:month-end')->dailyAt('09:00');
Schedule::command('billing:reminders')->dailyAt('10:00');
