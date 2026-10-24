<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OutstandingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PublicInvoiceController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Links sent to clients on WhatsApp, no login needed
Route::get('/i/{token}', [PublicInvoiceController::class, 'show'])->name('public.invoice');
Route::get('/i/{token}/pdf', [PublicInvoiceController::class, 'pdf'])->name('public.invoice.pdf');

Route::get('/offline', fn () => view('offline'))->name('offline');

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('clients', ClientController::class);

    Route::get('entries/import', [EntryController::class, 'importForm'])->name('entries.import');
    Route::post('entries/import', [EntryController::class, 'import'])->name('entries.import.store');
    Route::get('entries/sample', [EntryController::class, 'sample'])->name('entries.sample');
    Route::resource('entries', EntryController::class)->except('show');

    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('billing/generate', [BillingController::class, 'generate'])->name('billing.generate');
    Route::post('billing/email-all', [BillingController::class, 'emailAll'])->name('billing.email-all');
    Route::post('billing/send-all', [BillingController::class, 'sendAll'])->name('billing.send-all');
    Route::get('billing/whatsapp', [BillingController::class, 'whatsappQueue'])->name('billing.whatsapp');
    Route::post('billing/whatsapp-all', [BillingController::class, 'whatsappAll'])->name('billing.whatsapp-all');

    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::post('invoices/{invoice}/email', [InvoiceController::class, 'email'])->name('invoices.email');
    Route::get('invoices/{invoice}/whatsapp', [InvoiceController::class, 'whatsapp'])->name('invoices.whatsapp');
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

    Route::resource('payments', PaymentController::class)->only(['index', 'create', 'store', 'destroy']);

    Route::get('bank', [BankController::class, 'index'])->name('bank.index');
    Route::post('bank', [BankController::class, 'upload'])->name('bank.upload');
    Route::get('bank/sample', [BankController::class, 'sample'])->name('bank.sample');
    Route::get('bank/{import}', [BankController::class, 'show'])->name('bank.show');
    Route::post('bank/{import}/rows', [BankController::class, 'updateRows'])->name('bank.rows');
    Route::post('bank/{import}/post', [BankController::class, 'post'])->name('bank.post');
    Route::delete('bank/{import}', [BankController::class, 'destroy'])->name('bank.destroy');

    Route::get('outstanding', [OutstandingController::class, 'index'])->name('outstanding.index');
    Route::post('outstanding/{client}/email', [OutstandingController::class, 'email'])->name('outstanding.email');
    Route::get('outstanding/{client}/whatsapp', [OutstandingController::class, 'whatsapp'])->name('outstanding.whatsapp');
    Route::post('outstanding/{client}/send', [OutstandingController::class, 'send'])->name('outstanding.send');
    Route::post('outstanding/email-all', [OutstandingController::class, 'emailAll'])->name('outstanding.email-all');

    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('settings/test-email', [SettingController::class, 'testEmail'])->name('settings.test-email');
    Route::put('settings/password', [SettingController::class, 'password'])->name('settings.password');
});
