<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A CV PDF being read by the user's AI key into Markdown. Rows are short-lived: saving the CV with the result moves
 * the PDF onto the master CV and deletes the import; discarded or stale imports are deleted with their PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('status_id');
            $table->foreign('status_id')->references('id')->on('generation_statuses');
            $table->unsignedSmallInteger('provider_id');
            $table->foreign('provider_id')->references('id')->on('ai_providers');
            $table->string('model', 200);
            $table->longText('markdown')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // One import queued or running per user (status ids 1 and 2 are Queued and Running).
        DB::statement('CREATE UNIQUE INDEX cv_imports_one_unfinished_per_user ON cv_imports (user_id) WHERE status_id IN (1, 2)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_imports');
    }
};
