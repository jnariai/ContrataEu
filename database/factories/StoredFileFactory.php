<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Enums\FilePurpose;
use App\Models\GeneratedDocument;
use App\Models\MasterDocument;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StoredFile> */
class StoredFileFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'fileable_type' => 'master_document',
            'fileable_id' => MasterDocument::factory(),
            'purpose' => FilePurpose::MasterOriginal,
            'disk' => 'r2',
            'path' => 'users/0/master/cv/'.Str::ulid().'.pdf',
            'original_name' => 'cv.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 12_345,
        ];
    }

    public function forMasterDocument(MasterDocument $document): static
    {
        return $this->state([
            'user_id' => $document->user_id,
            'fileable_type' => 'master_document',
            'fileable_id' => $document->id,
            'purpose' => FilePurpose::MasterOriginal,
            'path' => "users/{$document->user_id}/master/{$document->type->value}/".Str::ulid().'.pdf',
        ]);
    }

    public function forGeneratedDocument(GeneratedDocument $document, User $owner): static
    {
        $document->loadMissing('generation');

        return $this->state([
            'user_id' => $owner->id,
            'fileable_type' => 'generated_document',
            'fileable_id' => $document->id,
            'purpose' => FilePurpose::DocumentPdf,
            'path' => "users/{$owner->id}/applications/{$document->generation->application_id}/v{$document->generation->version}/{$document->type->value}.pdf",
            'original_name' => $document->type === DocumentType::Cv ? 'Jane-Doe-Backend-Engineer.pdf' : 'Jane-Doe-Backend-Engineer-Cover-Letter.pdf',
        ]);
    }
}
