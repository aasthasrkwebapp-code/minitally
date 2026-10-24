<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->text('address')->nullable();
            // Names/words as they appear in bank statement narration, comma separated
            $table->string('bank_keywords')->nullable();
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('name');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->date('invoice_date');
            $table->string('period', 7); // YYYY-MM
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('status', 10)->default('unpaid'); // unpaid, partial, paid
            $table->string('public_token', 40)->unique();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('whatsapp_at')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->unsignedInteger('reminder_count')->default(0);
            $table->timestamps();
            $table->index(['period', 'client_id']);
            $table->index('status');
        });

        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            // Columns as in the office's daily sheet
            $table->date('issued_date');
            $table->string('party_name');
            $table->string('reference_no')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('ir_na', 20)->nullable();
            $table->string('destination')->nullable();
            $table->string('certificate')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('transaction_amount', 14, 2)->nullable();
            $table->string('mail_by')->nullable();
            $table->timestamps();
            $table->index(['issued_date', 'client_id']);
        });

        Schema::create('bank_imports', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_credit')->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 12, 2);
            $table->string('mode', 20)->default('bank'); // bank, upi, cash, cheque
            $table->string('reference')->nullable();
            $table->text('narration')->nullable();
            $table->string('source', 10)->default('manual'); // manual, bank
            $table->timestamps();
            $table->index(['client_id', 'paid_on']);
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_import_id')->constrained()->cascadeOnDelete();
            $table->date('txn_date');
            $table->text('narration')->nullable();
            $table->string('reference')->nullable();
            $table->decimal('credit', 12, 2)->default(0);
            $table->decimal('debit', 12, 2)->default(0);
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('match_reason')->nullable();
            $table->string('status', 10)->default('pending'); // pending, posted, ignored, duplicate
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hash', 64)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bank_imports');
        Schema::dropIfExists('entries');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('settings');
    }
};
