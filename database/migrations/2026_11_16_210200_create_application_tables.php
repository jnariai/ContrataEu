<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('role_title');
            $table->string('job_url', 2048)->nullable();
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('work_mode_id')->nullable();
            $table->foreign('work_mode_id')->references('id')->on('work_modes')->restrictOnDelete();
            $table->unsignedSmallInteger('language_id');
            $table->foreign('language_id')->references('id')->on('languages')->restrictOnDelete();
            $table->text('company_notes')->nullable();
            $table->longText('job_description');
            $table->boolean('wants_cover_letter')->default(false);
            $table->boolean('wants_recruiter_message')->default(false);
            $table->unsignedSmallInteger('status_id')->default(1);
            $table->foreign('status_id')->references('id')->on('application_statuses')->restrictOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->unsignedBigInteger('current_generation_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status_id']);
        });

        Schema::create('screening_questions', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['application_id', 'position']);
        });

        Schema::create('generations', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedSmallInteger('status_id');
            $table->foreign('status_id')->references('id')->on('generation_statuses')->restrictOnDelete();
            $table->unsignedSmallInteger('provider_id');
            $table->foreign('provider_id')->references('id')->on('ai_providers')->restrictOnDelete();
            $table->string('model');
            $table->unsignedSmallInteger('language_id');
            $table->foreign('language_id')->references('id')->on('languages')->restrictOnDelete();
            $table->jsonb('report')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'version']);
        });

        // At most one queued or running generation per application (status ids 1 = queued, 2 = running).
        DB::statement('CREATE UNIQUE INDEX generations_one_unfinished_per_application ON generations (application_id) WHERE status_id IN (1, 2)');

        Schema::table('applications', function (Blueprint $table): void {
            $table->foreign('current_generation_id')->references('id')->on('generations')->nullOnDelete();
        });

        Schema::create('generated_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('generation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('type_id');
            $table->foreign('type_id')->references('id')->on('document_types')->restrictOnDelete();
            $table->longText('content');
            $table->longText('original_content');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->unique(['generation_id', 'type_id']);
        });

        Schema::create('generated_answers', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('generation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screening_question_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->text('original_content');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->unique(['generation_id', 'screening_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_answers');
        Schema::dropIfExists('generated_documents');

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropForeign(['current_generation_id']);
        });

        Schema::dropIfExists('generations');
        Schema::dropIfExists('screening_questions');
        Schema::dropIfExists('applications');
    }
};
