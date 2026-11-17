<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table): void {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('value', 64)->unique();
        });

        DB::table('languages')->insert([
            ['id' => 1, 'value' => 'en'],
            ['id' => 2, 'value' => 'pt-BR'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
