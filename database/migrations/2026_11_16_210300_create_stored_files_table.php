<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_files', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('fileable');
            $table->unsignedSmallInteger('purpose_id');
            $table->foreign('purpose_id')->references('id')->on('file_purposes')->restrictOnDelete();
            $table->string('disk', 32)->default('r2');
            $table->string('path', 512)->unique();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->index(['fileable_type', 'fileable_id', 'purpose_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_files');
    }
};
