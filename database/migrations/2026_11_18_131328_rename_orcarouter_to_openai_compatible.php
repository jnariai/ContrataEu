<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * OrcaRouter becomes one case of a generic OpenAI-compatible provider (the user types its base URL). No key ever used
 * OrcaRouter, so its lookup row is renamed in place and keeps id 7.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_providers')->where('id', 7)->update(['value' => 'openai_compatible']);
    }

    public function down(): void
    {
        DB::table('ai_providers')->where('id', 7)->update(['value' => 'orcarouter']);
    }
};
