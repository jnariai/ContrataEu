<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Why a run failed, as the business exception's stable code (`ai_provider.invalid_key`); null when unknown. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table): void {
            $table->string('error_code', 64)->nullable()->after('error');
        });
    }

    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table): void {
            $table->dropColumn('error_code');
        });
    }
};
