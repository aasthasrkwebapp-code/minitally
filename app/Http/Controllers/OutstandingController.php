<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\BillSender;

class OutstandingController extends Controller
{
    public function index()
    {
        $rows = Client::with(['invoices' => fn ($q) => $q->where('status', '!=', 'paid')->orderBy('invoice_date')])
            ->get()
            ->map(fn (Client $c) => (object) [
                'client' => $c,
                'due' => $c->outstanding(),
                'oldest' => $c->invoices->first()?->invoice_date,
                'unpaid' => $c->invoices,
                'last_reminded' => $c->invoices->max('last_reminded_at'),
            ])
            ->filter(fn ($r) => $r->due > 0.009)
            ->sortByDesc('due')->values();

        return view('outstanding.index', ['rows' => $rows, 'total' => $rows->sum('due')]);
    }

    public function email(Client $client, BillSender $sender)
    {
        try {
            $sender->emailReminder($client);
        } catch (\Throwable $e) {
            return back()->withErrors(['send' => 'Reminder not sent: '.$e->getMessage()]);
        }

        return back()->with('ok', "Reminder emailed to {$client->name}.");
    }

    public function whatsapp(Client $client, BillSender $sender)
    {
        try {
            $url = $sender->whatsappReminder($client);
        } catch (\Throwable $e) {
            return back()->withErrors(['send' => 'Reminder not sent: '.$e->getMessage()]);
        }

        return $url ? redirect()->away($url) : back()->with('ok', "Reminder sent to {$client->name} on WhatsApp.");
    }

    /** Email reminder (with pending bills) and WhatsApp reminder in one tap. */
    public function send(Client $client, BillSender $sender)
    {
        $failed = [];
        if ($client->email) {
            try {
                $sender->emailReminder($client);
            } catch (\Throwable $e) {
                $failed[] = 'Email: '.$e->getMessage();
            }
        }
        $url = null;
        if ($client->whatsapp) {
            try {
                $url = $sender->whatsappReminder($client);
            } catch (\Throwable $e) {
                $failed[] = 'WhatsApp: '.$e->getMessage();
            }
        }
        session()->flash('ok', "Reminder sent to {$client->name}.");
        session()->flash('errors_list', $failed);

        return $url ? redirect()->away($url) : back();
    }

    public function emailAll(BillSender $sender)
    {
        @set_time_limit(0);
        [$sent, $failed] = [0, []];
        foreach (Client::all() as $client) {
            if ($client->outstanding() <= 0.009) {
                continue;
            }
            try {
                $sender->emailReminder($client);
                $sent++;
            } catch (\Throwable $e) {
                $failed[] = $client->name.': '.$e->getMessage();
            }
        }

        return back()->with('ok', "$sent reminder(s) emailed.")->with('errors_list', $failed);
    }
}
