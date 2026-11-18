<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Where an OpenAI-compatible key's server lives; null for the providers we know the address of. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_credentials', function (Blueprint $table): void {
            $table->string('base_url', 255)->nullable()->after('api_key');
        });
    }

    public function down(): void
    {
        Schema::table('ai_credentials', function (Blueprint $table): void {
            $table->dropColumn('base_url');
        });
    }
};
