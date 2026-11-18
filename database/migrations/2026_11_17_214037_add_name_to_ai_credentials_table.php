<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** An optional name for an AI key ("Work OpenRouter"), so several keys for the same provider can be told apart. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_credentials', function (Blueprint $table): void {
            $table->string('name', 60)->nullable()->after('provider_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_credentials', function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }
};
