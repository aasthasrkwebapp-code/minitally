<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quotations: a rate per certificate, type (IR = Retrospective, NA = Normal) and sets.
 * An accepted quotation fills the Amount on daily entries. Existing rows are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->date('quote_date');
            $table->date('valid_until')->nullable();
            $table->string('status', 10)->default('draft'); // draft, sent, accepted, rejected
            $table->text('notes')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
            $table->index(['client_id', 'status']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('certificate');
            $table->string('ir_na', 20)->default('NA'); // IR = Retrospective, NA = Normal
            $table->unsignedTinyInteger('sets')->default(1); // 1, 2 or 3 (test, meaning to be confirmed)
            $table->decimal('rate', 12, 2);
            $table->timestamps();
        });

        Schema::table('entries', function (Blueprint $table) {
            $table->unsignedTinyInteger('sets')->default(1)->after('certificate');
            $table->foreignId('quotation_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quotation_id');
            $table->dropColumn('sets');
        });
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
