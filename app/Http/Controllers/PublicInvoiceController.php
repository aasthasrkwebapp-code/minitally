<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Setting;
use App\Services\InvoiceService;

class PublicInvoiceController extends Controller
{
    public function show(string $token)
    {
        $invoice = Invoice::with('client')->where('public_token', $token)->firstOrFail();

        return view('public.invoice', ['invoice' => $invoice, 's' => Setting::allWithDefaults()]);
    }

    public function pdf(string $token, InvoiceService $service)
    {
        $invoice = Invoice::where('public_token', $token)->firstOrFail();

        return $service->pdf($invoice)->stream($invoice->fileName());
    }
}
