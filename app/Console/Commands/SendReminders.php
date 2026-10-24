<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Setting;
use App\Services\BillSender;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'billing:reminders {--force : run even if automatic reminders are off or today is not a reminder day}';

    protected $description = 'Email (and WhatsApp, when the Business API is set up) payment reminders to every client with dues';

    public function handle(BillSender $sender, WhatsAppService $whatsapp): int
    {
        if (! $this->option('force')) {
            $days = array_map('intval', array_filter(array_map('trim', explode(',', (string) Setting::get('reminder_days')))));
            if (Setting::get('auto_reminders') !== '1' || ! in_array((int) now()->day, $days, true)) {
                $this->line('No reminders today.');

                return self::SUCCESS;
            }
        }

        foreach (Client::where('is_active', true)->get() as $client) {
            if ($client->outstanding() <= 0.009) {
                continue;
            }
            if ($client->email) {
                try {
                    $sender->emailReminder($client);
                    $this->line("Reminder emailed to {$client->name}");
                } catch (\Throwable $e) {
                    $this->error("{$client->name}: {$e->getMessage()}");
                }
            }
            if ($whatsapp->mode() === 'cloud' && $client->whatsapp) {
                try {
                    $sender->whatsappReminder($client);
                } catch (\Throwable $e) {
                    $this->error("{$client->name} WhatsApp: {$e->getMessage()}");
                }
            }
        }

        return self::SUCCESS;
    }
}
