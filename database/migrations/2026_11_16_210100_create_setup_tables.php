<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('headline')->nullable();
            $table->string('location')->nullable();
            $table->string('work_authorization')->nullable();
            $table->string('region_convention', 16)->nullable();
            $table->jsonb('links')->nullable();
            $table->unsignedSmallInteger('default_output_language_id')->default(1);
            $table->foreign('default_output_language_id')->references('id')->on('languages')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('master_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('type_id');
            $table->foreign('type_id')->references('id')->on('document_types')->restrictOnDelete();
            $table->longText('content');
            $table->timestamps();

            $table->unique(['user_id', 'type_id']);
        });

        Schema::create('prompt_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('key_id');
            $table->foreign('key_id')->references('id')->on('prompt_keys')->restrictOnDelete();
            $table->longText('content');
            $table->timestamps();

            $table->unique(['user_id', 'key_id']);
        });

        Schema::create('ai_credentials', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('provider_id');
            $table->foreign('provider_id')->references('id')->on('ai_providers')->restrictOnDelete();
            $table->text('api_key');
            $table->string('key_hint', 8);
            $table->string('model');
            $table->boolean('is_default')->default(false);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
        });

        // At most one default credential per user.
        DB::statement('CREATE UNIQUE INDEX ai_credentials_one_default_per_user ON ai_credentials (user_id) WHERE is_default');
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_credentials');
        Schema::dropIfExists('prompt_overrides');
        Schema::dropIfExists('master_documents');
        Schema::dropIfExists('candidate_profiles');
    }
};
