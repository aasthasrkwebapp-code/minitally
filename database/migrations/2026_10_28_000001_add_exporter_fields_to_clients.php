<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Clients are shown as "Exporters". Adds their DGFT, ICEGATE and DSC details. Existing rows are kept. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('dgft_user_id')->nullable()->after('bank_keywords');
            // Passwords are stored encrypted with APP_KEY, so text
            $table->text('dgft_password')->nullable()->after('dgft_user_id');
            $table->string('icegate_id')->nullable()->after('dgft_password');
            $table->text('icegate_password')->nullable()->after('icegate_id');
            $table->date('dsc_valid_till')->nullable()->after('icegate_password');
            // available / delivered / expired
            $table->string('dsc_status', 20)->nullable()->after('dsc_valid_till');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['dgft_user_id', 'dgft_password', 'icegate_id', 'icegate_password', 'dsc_valid_till', 'dsc_status']);
        });
    }
};
