<?php

namespace App\Http\Controllers\Settings;

use App\Actions\SaveMasterDocument;
use App\Enums\DocumentType;
use App\Enums\FilePurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateMasterDocumentRequest;
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
            'maxCharacters' => UpdateMasterDocumentRequest::MAX_CHARACTERS,
            'maxUploadKilobytes' => FilePurpose::MasterOriginal->maxKilobytes(),
        ]);
    }

    public function update(UpdateMasterDocumentRequest $request, DocumentType $type, SaveMasterDocument $saveMasterDocument): RedirectResponse
    {
        $saved = $saveMasterDocument->handle($request->user(), $type, $request->string('content')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => $saved === null
            ? __(':document removed.', ['document' => $type->label()])
            : __(':document saved.', ['document' => $type->label()])]);

        return to_route('master-documents.index');
    }
}
