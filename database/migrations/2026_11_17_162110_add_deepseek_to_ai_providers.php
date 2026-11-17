<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** DeepSeek joins the AI providers. Lookup ids never change, so it takes the next free one. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_providers')->insert(['id' => 6, 'value' => 'deepseek']);
    }

    public function down(): void
    {
        DB::table('ai_providers')->where('id', 6)->delete();
    }
};
