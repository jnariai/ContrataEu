<?php

namespace App\Http\Controllers\Settings;

use App\Actions\SaveMasterDocument;
use App\Enums\DocumentType;
use App\Enums\FilePurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateMasterDocumentRequest;
use App\Models\CvImport;
use App\Models\MasterDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MasterDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $documents = $request->user()->masterDocuments()->with('originalFile')->get()
            ->keyBy(fn (MasterDocument $document): string => $document->type->value);

        return Inertia::render('settings/documents', [
            'documents' => array_map(function (DocumentType $type) use ($documents): array {
                /** @var MasterDocument|null $document */
                $document = $documents->get($type->value);
                $file = $document?->originalFile;

                return [
                    'type' => $type->value,
                    'label' => $type->label(),
                    'required' => $type->isRequired(),
                    'content' => $document?->content,
                    'updated_at' => $document?->updated_at->toIso8601ZuluString(),
                    'file' => $file === null ? null : [
                        'id' => $file->public_id,
                        'name' => $file->original_name,
                        'size_bytes' => $file->size_bytes,
                    ],
                ];
            }, DocumentType::cases()),
            'cvImport' => $this->cvImport($request),
            'canImportCv' => $this->canImportCv($request),
            'maxCharacters' => UpdateMasterDocumentRequest::MAX_CHARACTERS,
            'maxUploadKilobytes' => FilePurpose::MasterOriginal->maxKilobytes(),
        ]);
    }

    /** @return array<string, mixed>|null the newest PDF import, so the page can poll it and fill the CV editor */
    private function cvImport(Request $request): ?array
    {
        $import = CvImport::latestFor($request->user());

        return $import === null ? null : [
            'id' => $import->public_id,
            'status' => ['value' => $import->status->value, 'finished' => $import->isFinished()],
            'markdown' => $import->isReady() ? $import->markdown : null,
            'error' => $import->error,
            'file_name' => $import->pdfFile?->original_name,
            'provider' => $import->provider->label(),
        ];
    }

    /** @return array{allowed: bool, reason: string|null} whether the active key can read a PDF, and why not */
    private function canImportCv(Request $request): array
    {
        $credential = $request->user()->aiCredentials()->where('is_default', true)->first();

        return match (true) {
            $credential === null => ['allowed' => false, 'reason' => __('Add an AI key first: your AI account reads the PDF.')],
            ! $credential->provider->readsPdf() => ['allowed' => false, 'reason' => __(':provider can’t read PDFs. Make an Anthropic, OpenAI, Gemini or OpenRouter key active to import one.', ['provider' => $credential->provider->label()])],
            default => ['allowed' => true, 'reason' => null],
        };
    }

    public function update(UpdateMasterDocumentRequest $request, DocumentType $type, SaveMasterDocument $saveMasterDocument): RedirectResponse
    {
        $saved = $saveMasterDocument->handle($request->user(), $type, $request->string('content')->toString(), $request->cvImport());

        Inertia::flash('toast', ['type' => 'success', 'message' => $saved === null
            ? __(':document removed.', ['document' => $type->label()])
            : __(':document saved.', ['document' => $type->label()])]);

        return to_route('master-documents.index');
    }
}
