<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** OrcaRouter joins the AI providers. Lookup ids never change, so it takes the next free one. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_providers')->insert(['id' => 7, 'value' => 'orcarouter']);
    }

    public function down(): void
    {
        DB::table('ai_providers')->where('id', 7)->delete();
    }
};
