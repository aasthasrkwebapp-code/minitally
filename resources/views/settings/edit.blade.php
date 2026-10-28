@php
$field = fn ($key, $label, $type = 'text', $extra = '') => ['key' => $key, 'label' => $label, 'type' => $type, 'extra' => $extra];
$groups = [
    'Business (printed on every bill)' => [
        $field('business_name', 'Business name'), $field('pan', 'PAN no.'),
        $field('address_line1', 'Address line 1'), $field('address_line2', 'Address line 2'),
        $field('phone', 'Phone'), $field('email', 'Email'),
    ],
    'Bank account (printed on every bill)' => [
        $field('bank_account_name', 'Account name'), $field('bank_account_no', 'Account no.'),
        $field('bank_ifsc', 'IFSC'), $field('bank_account_type', 'Account type'), $field('bank_name', 'Bank'),
    ],
    'Bill numbering' => [
        $field('invoice_prefix', 'Prefix (bill no. looks like RC/26-27/0001)'),
        $field('invoice_next_number', 'First number of the financial year', 'number'),
    ],
];
@endphp
<x-layouts.app title="Settings">
    <x-page-header title="Settings" />

    <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
        @csrf @method('PUT')

        @foreach ($groups as $title => $fields)
            <section class="card card-pad">
                <h2 class="mb-4 font-semibold">{{ $title }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($fields as $f)
                        <div><label class="label">{{ $f['label'] }}</label><input type="{{ $f['type'] }}" name="{{ $f['key'] }}" value="{{ old($f['key'], $s[$f['key']]) }}" class="input"></div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <section class="card card-pad">
            <h2 class="mb-1 font-semibold">Who gets the month-end bill</h2>
            <p class="mb-4 text-sm text-slate-500">Each exporter gets one bill per month listing all of their entries.</p>
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach (['party' => ['Party name', 'Every party name is an exporter'], 'mail_by' => ['Mail by', 'The name in Mail by is the exporter'], 'manual' => ['Choose on each entry', 'Pick the exporter in a "Bill to" box']] as $k => [$t, $d])
                    <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                        <input type="radio" name="bill_to" value="{{ $k }}" @checked($s['bill_to'] === $k) class="mt-0.5">
                        <span><span class="block text-sm font-medium">{{ $t }}</span><span class="block text-xs text-slate-500">{{ $d }}</span></span>
                    </label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-slate-500">Changing this affects new entries only.</p>
        </section>

        <section class="card card-pad">
            <h2 class="mb-1 font-semibold">Messages</h2>
            <p class="mb-4 text-xs text-slate-500">You can use: {exporter} {invoice} {month} {amount} {link} {business}. Reminders also: {invoices}. ({client} still works too.)</p>
            <div class="space-y-4">
                <div><label class="label">Bill email</label><textarea name="email_bill_message" rows="7" class="input">{{ old('email_bill_message', $s['email_bill_message']) }}</textarea></div>
                <div><label class="label">Bill WhatsApp</label><textarea name="whatsapp_bill_message" rows="3" class="input">{{ old('whatsapp_bill_message', $s['whatsapp_bill_message']) }}</textarea></div>
                <div><label class="label">Payment reminder (email and WhatsApp)</label><textarea name="reminder_message" rows="4" class="input">{{ old('reminder_message', $s['reminder_message']) }}</textarea></div>
            </div>
        </section>

        <section class="card card-pad">
            <h2 class="mb-4 font-semibold">WhatsApp</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                    <input type="radio" name="whatsapp_mode" value="auto" @checked($s['whatsapp_mode'] !== 'link') class="mt-0.5">
                    <span><span class="block text-sm font-medium">Automatic when connected (recommended)</span><span class="block text-xs text-slate-500">
                        @if ($cloudReady) <span class="font-medium text-emerald-700">WhatsApp Business API is connected.</span> Bills and reminders are sent by themselves with the PDF.
                        @else WhatsApp Business API keys are not in .env yet, so WhatsApp opens with the message ready and you press send. Once the keys are added it sends by itself. @endif
                    </span></span>
                </label>
                <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                    <input type="radio" name="whatsapp_mode" value="link" @checked($s['whatsapp_mode'] === 'link') class="mt-0.5">
                    <span><span class="block text-sm font-medium">Always use WhatsApp link</span><span class="block text-xs text-slate-500">Open WhatsApp with the message ready and press send yourself, even if the API is connected.</span></span>
                </label>
            </div>
        </section>

        <section class="card card-pad">
            <h2 class="mb-4 font-semibold">Automatic sending</h2>
            <div class="space-y-4">
                <label class="flex items-start gap-3 text-sm">
                    <input type="hidden" name="auto_month_end" value="0">
                    <input type="checkbox" name="auto_month_end" value="1" @checked($s['auto_month_end'] === '1') class="mt-0.5 size-4 rounded border-slate-300">
                    <span>Make and email last month's bills automatically on day
                        <input type="number" name="auto_month_end_day" min="1" max="28" value="{{ $s['auto_month_end_day'] }}" class="input inline-block w-16 px-2 py-1"> of every month.
                        <span class="block text-xs text-slate-500">WhatsApp is also sent if the Business API is connected.</span></span>
                </label>
                <label class="flex items-start gap-3 text-sm">
                    <input type="hidden" name="auto_reminders" value="0">
                    <input type="checkbox" name="auto_reminders" value="1" @checked($s['auto_reminders'] === '1') class="mt-0.5 size-4 rounded border-slate-300">
                    <span>Email payment reminders automatically on these days of the month:
                        <input name="reminder_days" value="{{ $s['reminder_days'] }}" class="input inline-block w-28 px-2 py-1" placeholder="10,20"></span>
                </label>
                <p class="text-xs text-slate-500">Needs the server's cron job (see README). Mail is sent from <strong>{{ $mailFrom }}</strong> using <strong>{{ $mailer }}</strong>.</p>
            </div>
        </section>

        <div class="sticky bottom-16 z-10 rounded-xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur md:bottom-4"><button class="btn btn-primary">Save settings</button></div>
    </form>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('settings.test-email') }}" class="card card-pad space-y-3">
            @csrf
            <h2 class="font-semibold">Send a test email</h2>
            <input type="email" name="to" value="{{ auth()->user()->email }}" class="input" required>
            <button class="btn btn-secondary" data-busy="Sending..."><x-icon name="mail" class="size-4" />Send test</button>
        </form>
        <form method="POST" action="{{ route('settings.password') }}" class="card card-pad space-y-3">
            @csrf @method('PUT')
            <h2 class="font-semibold">Change password</h2>
            <input type="password" name="current_password" placeholder="Current password" class="input" required autocomplete="current-password">
            <input type="password" name="password" placeholder="New password (8+ characters)" class="input" required autocomplete="new-password">
            <input type="password" name="password_confirmation" placeholder="Repeat new password" class="input" required autocomplete="new-password">
            <button class="btn btn-secondary">Change password</button>
        </form>
    </div>
</x-layouts.app>
