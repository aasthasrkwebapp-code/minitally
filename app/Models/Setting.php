<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Defaults used until the owner changes them on the Settings page. */
    public const DEFAULTS = [
        'business_name' => 'RAHUL CHANDAK',
        'address_line1' => 'LM-76 AMBER TOWER SANSAR CHANDRA ROAD',
        'address_line2' => 'JAIPUR -302001',
        'phone' => '8114414800',
        'email' => 'exporteradda@gmail.com',
        'pan' => 'BMKPC1262M',
        'bank_name' => 'PUNJAB NATIONAL BANK',
        'bank_account_name' => 'RAHUL CHANDAK',
        'bank_account_no' => '4087000100127064',
        'bank_ifsc' => 'PUNB0408700',
        'bank_account_type' => 'SAVING A/c',
        'bill_to' => 'party', // party, mail_by or manual
        'invoice_prefix' => 'RC',
        'invoice_next_number' => '1',
        'due_days' => '7',
        'whatsapp_mode' => 'auto', // auto (API when keys are set, else link) or link
        'auto_month_end' => '0',
        'auto_month_end_day' => '1',
        'auto_reminders' => '0',
        'reminder_days' => '10,20',
        'email_bill_message' => "Dear {client},\n\nPlease find attached invoice {invoice} for {month} amounting to Rs. {amount}.\n\nKindly make the payment to the bank account mentioned in the invoice.\n\nThank you,\n{business}",
        'whatsapp_bill_message' => "Dear {client}, your bill {invoice} for {month} is Rs. {amount}. Download: {link}\n- {business}",
        'reminder_message' => "Dear {client}, a payment of Rs. {amount} is outstanding against your account. Pending bills: {invoices}. Kindly clear it at the earliest.\n- {business}",
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = Cache::rememberForever('settings.all', fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }

    public static function allWithDefaults(): array
    {
        $out = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $out[$key] = self::get($key);
        }

        return $out;
    }
}
