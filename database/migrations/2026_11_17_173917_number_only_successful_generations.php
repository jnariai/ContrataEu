<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Only a generation that succeeds gets a version number (set when its result is saved). Queued, running and failed
 * runs have none: failures are the application's failure log, not versions. Existing failed or unfinished runs
 * lose their number; successful ones keep theirs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table): void {
            $table->unsignedInteger('version')->nullable()->change();
        });

        // Status ids: 1 queued, 2 running, 3 succeeded, 4 failed (generation_statuses).
        DB::table('generations')->where('status_id', '!=', 3)->update(['version' => null]);
    }

    public function down(): void
    {
        // Give unnumbered runs numbers above the highest version of their application, then make it required again.
        DB::table('generations')->whereNull('version')->orderBy('id')->each(function (object $generation): void {
            $next = (int) DB::table('generations')->where('application_id', $generation->application_id)->max('version') + 1;
            DB::table('generations')->where('id', $generation->id)->update(['version' => $next]);
        });

        Schema::table('generations', function (Blueprint $table): void {
            $table->unsignedInteger('version')->nullable(false)->change();
        });
    }
};
