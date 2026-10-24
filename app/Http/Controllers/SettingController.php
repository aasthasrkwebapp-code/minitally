<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class SettingController extends Controller
{
    public function edit(WhatsAppService $whatsapp)
    {
        return view('settings.edit', [
            's' => Setting::allWithDefaults(),
            'cloudReady' => $whatsapp->cloudConfigured(),
            'mailer' => config('mail.default'),
            'mailFrom' => config('mail.from.address'),
        ]);
    }

    public function update(Request $request)
    {
        $rules = collect(Setting::DEFAULTS)->map(fn () => ['nullable', 'string', 'max:5000'])->all();
        $rules['invoice_next_number'] = ['nullable', 'integer', 'min:1'];
        $rules['due_days'] = ['nullable', 'integer', 'min:0'];
        $rules['auto_month_end_day'] = ['nullable', 'integer', 'min:1', 'max:28'];
        $rules['whatsapp_mode'] = ['nullable', 'in:link,cloud'];
        $rules['bill_to'] = ['nullable', 'in:party,mail_by,manual'];
        $data = $request->validate($rules);

        $data['auto_month_end'] = $request->boolean('auto_month_end') ? '1' : '0';
        $data['auto_reminders'] = $request->boolean('auto_reminders') ? '1' : '0';

        foreach ($data as $key => $value) {
            Setting::put($key, $value);
        }

        return back()->with('ok', 'Settings saved.');
    }

    public function testEmail(Request $request)
    {
        $data = $request->validate(['to' => ['required', 'email']]);
        try {
            Mail::raw('This is a test email from '.Setting::get('business_name').' billing. Email sending works.', function ($m) use ($data) {
                $m->to($data['to'])->subject('Test email - billing website');
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['to' => 'Test email failed: '.$e->getMessage()]);
        }

        return back()->with('ok', "Test email sent to {$data['to']}.");
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);
        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('ok', 'Password changed.');
    }
}
