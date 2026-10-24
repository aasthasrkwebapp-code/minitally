<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Two ways to send on WhatsApp:
 *  - "link":  opens WhatsApp (web or phone) with the message typed in; you press Send. Free, no setup.
 *  - "cloud": sends automatically through the WhatsApp Business Cloud API (Meta). Needs an approved
 *             business number and message templates, configured in .env.
 */
class WhatsAppService
{
    /** Automatic as soon as the API keys are in .env, unless the owner chose "link" in Settings. */
    public function mode(): string
    {
        return Setting::get('whatsapp_mode') !== 'link' && $this->cloudConfigured() ? 'cloud' : 'link';
    }

    public function cloudConfigured(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    public function link(string $phone, string $text): string
    {
        return 'https://wa.me/'.$phone.'?text='.rawurlencode($text);
    }

    /** Send a bill as a PDF document using the approved bill template. */
    public function sendBill(string $phone, string $pdfUrl, string $fileName, array $bodyParams): void
    {
        $this->sendTemplate($phone, config('services.whatsapp.bill_template'), $bodyParams, [
            'type' => 'header',
            'parameters' => [[
                'type' => 'document',
                'document' => ['link' => $pdfUrl, 'filename' => $fileName],
            ]],
        ]);
    }

    public function sendReminder(string $phone, array $bodyParams): void
    {
        $this->sendTemplate($phone, config('services.whatsapp.reminder_template'), $bodyParams);
    }

    private function sendTemplate(string $phone, ?string $template, array $bodyParams, ?array $header = null): void
    {
        if (! $template) {
            throw new RuntimeException('WhatsApp template name is not set in .env');
        }

        $components = [];
        if ($header) {
            $components[] = $header;
        }
        $components[] = [
            'type' => 'body',
            'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => (string) $p], array_values($bodyParams)),
        ];

        $response = Http::withToken(config('services.whatsapp.token'))
            ->timeout(30)
            ->post(sprintf(
                'https://graph.facebook.com/%s/%s/messages',
                config('services.whatsapp.api_version', 'v21.0'),
                config('services.whatsapp.phone_number_id')
            ), [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => config('services.whatsapp.language', 'en')],
                    'components' => $components,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('WhatsApp API error: '.($response->json('error.message') ?? $response->body()));
        }
    }
}
