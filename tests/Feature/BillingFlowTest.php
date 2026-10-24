<?php

namespace Tests\Feature;

use App\Mail\InvoiceMail;
use App\Mail\ReminderMail;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\Entry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\SampleFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BillingFlowTest extends TestCase
{
    use RefreshDatabase;

    private const MONTH = '2026-10';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-31 12:00:00');
        $this->actingAs(User::factory()->create());
    }

    private function importSample(): void
    {
        $path = app(SampleFiles::class)->entriesWorkbook(null, self::MONTH);
        $this->post(route('entries.import.store'), [
            'file' => new UploadedFile($path, 'entries.xlsx', null, null, true),
        ])->assertRedirect(route('entries.index'));

        // Give the dummy clients their contact details (as the owner would on the Clients page)
        foreach (SampleFiles::CLIENTS as $c) {
            Client::where('name', $c['name'])->update(collect($c)->except('name')->all());
        }
    }

    public function test_guests_are_sent_to_login_and_can_log_in(): void
    {
        auth()->logout();
        $this->get('/')->assertRedirect('/login');
        $user = User::factory()->create(['password' => 'secret-pass']);
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        auth()->logout();
        $user = User::factory()->create(['password' => 'secret-pass']);
        $this->post('/login', ['email' => $user->email, 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_every_page_loads(): void
    {
        $this->importSample();
        app(InvoiceService::class)->generateForMonth(self::MONTH);
        $invoice = Invoice::first();
        $entry = Entry::first();
        $client = Client::first();

        foreach (['/', '/entries', '/entries/create', '/entries/import', '/billing', '/billing/whatsapp', '/invoices',
            "/invoices/{$invoice->id}", '/payments', '/payments/create', '/bank', '/outstanding', '/clients',
            '/clients/create', "/clients/{$client->id}", "/clients/{$client->id}/edit", '/settings', "/entries/{$entry->id}/edit"] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_import_of_sample_sheet_creates_50_entries_and_clients_from_party_name(): void
    {
        $this->importSample();

        $this->assertSame(50, Entry::count());
        $this->assertSame(8, Client::count());
        $entry = Entry::orderBy('id')->first();
        $this->assertSame('Exporter Adda', $entry->party_name);
        $this->assertSame('Exporter Adda', $entry->client->name);
        $this->assertNotNull($entry->reference_no);
        $this->assertNotNull($entry->invoice_number);
        $this->assertContains($entry->ir_na, ['IR', 'NA']);
        $this->assertNotNull($entry->transaction_amount);
        $this->assertNotNull($entry->mail_by);
    }

    public function test_adding_entries_by_hand_follows_the_bill_to_setting(): void
    {
        $row = ['party_name' => 'Mehta Textiles', 'amount' => 750, 'mail_by' => 'Sharma CHA', 'certificate' => 'COO', 'destination' => 'UAE', 'ir_na' => 'IR'];

        $this->post(route('entries.store'), ['issued_date' => '2026-10-05', 'rows' => [$row]])->assertSessionHasNoErrors();
        $this->assertSame('Mehta Textiles', Entry::latest('id')->first()->client->name);

        Setting::put('bill_to', 'mail_by');
        $this->post(route('entries.store'), ['issued_date' => '2026-10-05', 'rows' => [$row]])->assertSessionHasNoErrors();
        $this->assertSame('Sharma CHA', Entry::latest('id')->first()->client->name);

        Setting::put('bill_to', 'manual');
        $this->post(route('entries.store'), ['issued_date' => '2026-10-05', 'rows' => [$row]])->assertSessionHasErrors('rows.0.client_id');
        $client = Client::first();
        $this->post(route('entries.store'), ['issued_date' => '2026-10-05', 'rows' => [$row + ['client_id' => $client->id]]])->assertSessionHasNoErrors();
        $this->assertSame($client->id, Entry::latest('id')->first()->client_id);

        // Empty rows are ignored, half-filled rows are refused
        $this->post(route('entries.store'), ['issued_date' => '2026-10-05', 'rows' => [['party_name' => 'X']]])->assertSessionHasErrors();
        $this->assertSame(3, Entry::count());
    }

    public function test_month_end_makes_one_bill_per_client_with_sequential_numbers(): void
    {
        $this->importSample();
        Entry::create(['client_id' => Client::first()->id, 'issued_date' => '2026-09-30', 'party_name' => 'Old', 'amount' => 100]);

        $this->post(route('billing.generate'), ['month' => self::MONTH])->assertRedirect();

        $invoices = Invoice::orderBy('number')->get();
        $this->assertSame(Client::has('entries')->whereHas('entries', fn ($q) => $q->where('issued_date', '>=', '2026-10-01'))->count(), $invoices->count());
        $this->assertSame('RC/26-27/0001', $invoices->first()->number);
        $this->assertSame('RC/26-27/000'.$invoices->count(), $invoices->last()->number);
        $this->assertSame('2026-10-31', $invoices->first()->invoice_date->toDateString());

        foreach ($invoices as $invoice) {
            $this->assertEquals($invoice->entries()->sum('amount'), $invoice->total);
            $this->assertSame('unpaid', $invoice->status);
        }
        $this->assertEquals(Entry::where('issued_date', '>=', '2026-10-01')->sum('amount'), $invoices->sum('total'));
        $this->assertSame(1, Entry::whereNull('invoice_id')->count()); // September entry untouched

        // Late entry for the same month is added to the existing bill, not a new one
        $first = $invoices->first();
        Entry::create(['client_id' => $first->client_id, 'issued_date' => '2026-10-28', 'party_name' => $first->client->name, 'amount' => 500]);
        $this->post(route('billing.generate'), ['month' => self::MONTH]);
        $this->assertSame($invoices->count(), Invoice::count());
        $this->assertEquals((float) $first->total + 500, (float) $first->fresh()->total);
    }

    public function test_bill_pdf_and_client_link_work(): void
    {
        $this->importSample();
        app(InvoiceService::class)->generateForMonth(self::MONTH);
        $invoice = Invoice::first();

        $this->get(route('invoices.pdf', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');

        auth()->logout();
        $this->get($invoice->publicUrl())->assertOk()->assertSee($invoice->number);
        $this->get(route('public.invoice.pdf', $invoice->public_token))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/i/not-a-real-token')->assertNotFound();
    }

    public function test_bills_are_emailed_with_pdf_attached(): void
    {
        Mail::fake();
        $this->importSample();
        app(InvoiceService::class)->generateForMonth(self::MONTH);

        $this->post(route('billing.email-all'), ['month' => self::MONTH])->assertRedirect();

        Mail::assertSent(InvoiceMail::class, Invoice::count());
        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) {
            return $mail->invoice->client->name !== 'Exporter Adda'
                || ($mail->hasTo('exporteradda@gmail.com') && count($mail->attachments()) === 1 && str_contains($mail->body, $mail->invoice->number));
        });
        $this->assertSame(0, Invoice::whereNull('emailed_at')->count());

        // Second press does not send again
        $this->post(route('billing.email-all'), ['month' => self::MONTH]);
        Mail::assertSent(InvoiceMail::class, Invoice::count());
    }

    public function test_whatsapp_link_mode_opens_whatsapp_with_the_bill(): void
    {
        $this->importSample();
        app(InvoiceService::class)->generateForMonth(self::MONTH);
        $invoice = Invoice::whereHas('client', fn ($q) => $q->where('name', 'Exporter Adda'))->first();

        $response = $this->get(route('invoices.whatsapp', $invoice));
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/918114414800?text=', $location);
        $this->assertStringContainsString(rawurlencode($invoice->number), $location);
        $this->assertNotNull($invoice->fresh()->whatsapp_at);

        // Client without a number gets a clear error
        $other = Invoice::whereHas('client', fn ($q) => $q->whereNull('whatsapp'))->first();
        $this->get(route('invoices.whatsapp', $other))->assertSessionHasErrors('send');
    }

    public function test_whatsapp_cloud_mode_sends_template_with_pdf(): void
    {
        config(['services.whatsapp.token' => 'test-token', 'services.whatsapp.phone_number_id' => '12345']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

        $this->importSample();
        app(InvoiceService::class)->generateForMonth(self::MONTH);
        $invoice = Invoice::whereHas('client', fn ($q) => $q->where('name', 'Exporter Adda'))->first();

        $this->get(route('invoices.whatsapp', $invoice))->assertSessionHasNoErrors();

        Http::assertSent(function ($request) use ($invoice) {
            $doc = $request['template']['components'][0]['parameters'][0]['document'];

            return $request->url() === 'https://graph.facebook.com/v21.0/12345/messages'
                && $request['to'] === '918114414800'
                && $request['template']['name'] === 'monthly_bill'
                && str_ends_with($doc['link'], '/i/'.$invoice->public_token.'/pdf');
        });
        $this->assertNotNull($invoice->fresh()->whatsapp_at);
    }

    public function test_bank_statement_matches_and_records_payments(): void
    {
        $this->importSample();
        app(InvoiceService::class)->generateForMonth(self::MONTH);
        $totals = Invoice::with('client')->get()->mapWithKeys(fn ($i) => [$i->client->name => (float) $i->total]);

        $path = app(SampleFiles::class)->bankStatement(null, self::MONTH);
        $this->post(route('bank.upload'), ['file' => new UploadedFile($path, 'pnb.xlsx', null, null, true)])->assertRedirect();

        $txns = BankTransaction::with('client')->get();
        $this->assertSame(8, $txns->count());
        $this->assertSame(2, $txns->where('status', 'ignored')->count()); // ATM + electricity debits
        $byNarration = fn ($text) => $txns->first(fn ($t) => str_contains($t->narration, $text));

        $this->assertSame('Shree Balaji Exports', $byNarration('SHREE BALAJI')->client->name);
        $this->assertSame('name', $byNarration('SHREE BALAJI')->match_reason);
        $this->assertSame('Exporter Adda', $byNarration('EXPORTERADDA')->client->name);
        $this->assertSame('Jaipur Gems Overseas', $byNarration('JAIPUR GEMS')->client->name);
        $this->assertSame('Rajasthan Handicrafts Pvt Ltd', $byNarration('RAJ HANDICRAFTS')->client->name);
        $this->assertNull($byNarration('UNKNOWN PARTY')->client_id);

        $ramesh = $byNarration('RAMESH KUMAR');
        $mehtaUnique = $totals->filter(fn ($t) => abs($t - $totals['Mehta Textiles']) < 0.01)->count() === 1;
        if ($mehtaUnique) {
            $this->assertSame('Mehta Textiles', $ramesh->client->name);
            $this->assertSame('amount', $ramesh->match_reason);
        }

        // Owner assigns the unknown cheque by hand, then saves
        $import = $ramesh->import;
        $kota = Client::where('name', 'Kota Stone Traders')->first();
        $this->post(route('bank.rows', $import), ['rows' => [$byNarration('UNKNOWN PARTY')->id => (string) $kota->id], 'then' => 'post'])->assertRedirect();

        $this->assertSame($mehtaUnique ? 6 : 5, Payment::count());
        $this->assertSame('paid', Invoice::whereHas('client', fn ($q) => $q->where('name', 'Shree Balaji Exports'))->first()->status);
        $this->assertSame('partial', Invoice::whereHas('client', fn ($q) => $q->where('name', 'Jaipur Gems Overseas'))->first()->status);
        $this->assertStringContainsString('UNKNOWN PARTY', $kota->fresh()->bank_keywords); // learned for next month

        // Uploading the same statement again does not double count
        $path = app(SampleFiles::class)->bankStatement(null, self::MONTH);
        $this->post(route('bank.upload'), ['file' => new UploadedFile($path, 'pnb.xlsx', null, null, true)]);
        $latest = \App\Models\BankImport::latest('id')->first();
        $this->post(route('bank.post', $latest));
        $this->assertSame($mehtaUnique ? 6 : 5, Payment::count());
        $this->assertSame($mehtaUnique ? 6 : 5, $latest->transactions()->where('status', 'duplicate')->count());
    }

    public function test_payments_are_applied_to_oldest_bills_first(): void
    {
        $client = Client::create(['name' => 'Test Co', 'opening_balance' => 1000]);
        foreach (['2026-08' => 3000, '2026-09' => 2000] as $month => $amount) {
            Entry::create(['client_id' => $client->id, 'issued_date' => $month.'-10', 'party_name' => 'P', 'amount' => $amount]);
            app(InvoiceService::class)->generateForMonth($month);
        }
        [$aug, $sep] = $client->invoices()->orderBy('invoice_date')->get()->all();

        $this->post(route('payments.store'), ['client_id' => $client->id, 'paid_on' => '2026-10-01', 'amount' => 2500, 'mode' => 'upi']);
        $this->assertSame(['partial', 'unpaid'], [$aug->fresh()->status, $sep->fresh()->status]); // 1000 opening + 1500 Aug
        $this->assertEquals(1500, $aug->fresh()->amount_paid);
        $this->assertEquals(3500, $client->fresh()->outstanding());

        $this->post(route('payments.store'), ['client_id' => $client->id, 'paid_on' => '2026-10-02', 'amount' => 3500, 'mode' => 'cash']);
        $this->assertSame(['paid', 'paid'], [$aug->fresh()->status, $sep->fresh()->status]);
        $this->assertEquals(0, $client->fresh()->outstanding());

        $this->delete(route('payments.destroy', Payment::latest('id')->first()));
        $this->assertSame('unpaid', $sep->fresh()->status);
    }

    public function test_outstanding_reminders_email_pending_bills(): void
    {
        Mail::fake();
        $this->importSample();
        app(InvoiceService::class)->generateForMonth(self::MONTH);
        $paidClient = Client::where('name', 'Shree Balaji Exports')->first();
        Payment::create(['client_id' => $paidClient->id, 'paid_on' => '2026-10-31', 'amount' => $paidClient->outstanding()]);
        $paidClient->reallocatePayments();

        $this->get('/'); // clear the import's flash message
        $this->get(route('outstanding.index'))->assertOk()->assertSee('Exporter Adda')->assertDontSee('Shree Balaji Exports');

        $this->post(route('outstanding.email-all'))->assertRedirect();
        Mail::assertSent(ReminderMail::class, Client::all()->filter(fn ($c) => $c->outstanding() > 0)->count());
        Mail::assertNotSent(ReminderMail::class, fn ($m) => $m->client->is($paidClient));
        $this->assertSame(1, Invoice::whereHas('client', fn ($q) => $q->where('name', 'Exporter Adda'))->first()->reminder_count);

        $wa = $this->get(route('outstanding.whatsapp', Client::where('name', 'Exporter Adda')->first()));
        $this->assertStringStartsWith('https://wa.me/918114414800?text=', $wa->headers->get('Location'));
    }

    public function test_editing_and_deleting_billed_entries_keeps_bills_correct(): void
    {
        $this->importSample();
        app(InvoiceService::class)->generateForMonth(self::MONTH);
        $invoice = Invoice::first();
        $entry = $invoice->entries()->first();
        $before = (float) $invoice->total;

        $this->put(route('entries.update', $entry), array_merge($entry->only(Entry::FIELDS), ['issued_date' => $entry->issued_date->toDateString(), 'amount' => (float) $entry->amount + 100]))->assertSessionHasNoErrors();
        $this->assertEquals($before + 100, (float) $invoice->fresh()->total);

        // Moving a billed entry to another month is refused
        $this->put(route('entries.update', $entry), array_merge($entry->fresh()->only(Entry::FIELDS), ['issued_date' => '2026-09-01']))->assertSessionHasErrors('party_name');

        // Deleting the bill releases its entries
        $count = $invoice->entries()->count();
        $this->delete(route('invoices.destroy', $invoice));
        $this->assertSame($count, Entry::whereNull('invoice_id')->count());
    }

    public function test_scheduled_month_end_command_respects_settings(): void
    {
        Mail::fake();
        $this->importSample();
        $this->travelTo('2026-11-01 09:00:00');

        $this->artisan('billing:month-end')->expectsOutput('Automatic month-end is off in Settings.')->assertSuccessful();
        $this->assertSame(0, Invoice::count());

        Setting::put('auto_month_end', '1');
        $this->artisan('billing:month-end')->assertSuccessful();
        $this->assertGreaterThan(0, Invoice::where('period', self::MONTH)->count());
        Mail::assertSent(InvoiceMail::class, Invoice::count());
    }

    public function test_reminder_command_runs_only_on_reminder_days(): void
    {
        Mail::fake();
        $this->importSample();
        app(InvoiceService::class)->generateForMonth(self::MONTH);
        Setting::put('auto_reminders', '1');
        Setting::put('reminder_days', '10,20');

        $this->travelTo('2026-11-09 10:00:00');
        $this->artisan('billing:reminders')->assertSuccessful();
        Mail::assertNothingSent();

        $this->travelTo('2026-11-10 10:00:00');
        $this->artisan('billing:reminders')->assertSuccessful();
        Mail::assertSent(ReminderMail::class);
    }

    public function test_settings_save_and_show_on_bill(): void
    {
        $this->put(route('settings.update'), array_merge(Setting::allWithDefaults(), ['business_name' => 'NEW NAME', 'invoice_prefix' => 'NN']))->assertSessionHasNoErrors();
        $this->assertSame('NEW NAME', Setting::get('business_name'));

        Entry::create(['client_id' => Client::create(['name' => 'A'])->id, 'issued_date' => '2026-10-01', 'party_name' => 'A', 'amount' => 10]);
        app(InvoiceService::class)->generateForMonth(self::MONTH);
        $this->assertSame('NN/26-27/0001', Invoice::first()->number);
    }

    public function test_one_tap_sends_bill_on_email_and_whatsapp(): void
    {
        Mail::fake();
        $this->importSample();
        app(InvoiceService::class)->generateForMonth(self::MONTH);
        $invoice = Invoice::whereHas('client', fn ($q) => $q->where('name', 'Exporter Adda'))->first();

        $response = $this->post(route('invoices.send', $invoice));
        $this->assertStringStartsWith('https://wa.me/918114414800?text=', $response->headers->get('Location'));
        Mail::assertSent(InvoiceMail::class, fn ($m) => $m->invoice->is($invoice) && $m->hasTo('exporteradda@gmail.com'));
        $invoice->refresh();
        $this->assertNotNull($invoice->emailed_at);
        $this->assertNotNull($invoice->whatsapp_at);

        $reminder = $this->post(route('outstanding.send', $invoice->client));
        $this->assertStringStartsWith('https://wa.me/918114414800?text=', $reminder->headers->get('Location'));
        Mail::assertSent(ReminderMail::class, 1);
    }

    public function test_send_all_emails_every_bill_then_opens_whatsapp_list(): void
    {
        Mail::fake();
        $this->importSample();
        app(InvoiceService::class)->generateForMonth(self::MONTH);

        $this->post(route('billing.send-all'), ['month' => self::MONTH])
            ->assertRedirect(route('billing.whatsapp', ['month' => self::MONTH]));
        Mail::assertSent(InvoiceMail::class, Invoice::count());

        // With the WhatsApp Business API, both go out automatically
        config(['services.whatsapp.token' => 't', 'services.whatsapp.phone_number_id' => '1']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'x']]])]);
        Invoice::query()->update(['emailed_at' => null, 'whatsapp_at' => null]);

        $this->post(route('billing.send-all'), ['month' => self::MONTH])->assertRedirect(route('billing.index', ['month' => self::MONTH]));
        Http::assertSentCount(Invoice::whereHas('client', fn ($q) => $q->whereNotNull('whatsapp'))->count());
        $this->assertSame(0, Invoice::whereNull('emailed_at')->count());
    }

    public function test_whatsapp_api_errors_are_shown_and_link_mode_can_be_forced(): void
    {
        config(['services.whatsapp.token' => 't', 'services.whatsapp.phone_number_id' => '1']);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template name does not exist']], 400)]);
        $this->importSample();
        app(InvoiceService::class)->generateForMonth(self::MONTH);
        $invoice = Invoice::whereHas('client', fn ($q) => $q->where('name', 'Exporter Adda'))->first();

        $this->get(route('invoices.whatsapp', $invoice))->assertSessionHasErrors(['send' => 'WhatsApp not sent: WhatsApp API error: Template name does not exist']);
        $this->assertNull($invoice->fresh()->whatsapp_at);

        Setting::put('whatsapp_mode', 'link');
        $this->assertStringStartsWith('https://wa.me/', $this->get(route('invoices.whatsapp', $invoice))->headers->get('Location'));
    }

    public function test_reminder_goes_through_whatsapp_api_when_connected(): void
    {
        config(['services.whatsapp.token' => 't', 'services.whatsapp.phone_number_id' => '1']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'x']]])]);
        Mail::fake();
        $this->importSample();
        app(InvoiceService::class)->generateForMonth(self::MONTH);
        $client = Client::where('name', 'Exporter Adda')->first();

        $this->post(route('outstanding.send', $client))->assertRedirect();
        Mail::assertSent(ReminderMail::class, 1);
        Http::assertSent(fn ($r) => $r['template']['name'] === 'payment_reminder' && $r['to'] === '918114414800'
            && count($r['template']['components'][0]['parameters']) === 3);
    }
}
