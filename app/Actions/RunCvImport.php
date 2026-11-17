<?php

namespace App\Actions;

use App\Enums\Language;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\CvImportCannotRun;
use App\Gateways\GatewayException;
use App\Gateways\Prism\PrismClient;
use App\Models\AiCredential;
use App\Models\CvImport;
use Illuminate\Filesystem\FilesystemManager;
use Throwable;

/**
 * Runs one queued CV import: Queued → Running → (one AI call reading the PDF) → Succeeded with Markdown, or Failed
 * with a message the user can act on. Called by RunCvImportJob; an already-claimed import is left alone.
 */
class RunCvImport
{
    public function __construct(
        private PrismClient $client,
        private FilesystemManager $storage,
    ) {}

    public function handle(CvImport $import): void
    {
        if (! $import->start(now()->toImmutable())) {
            return;
        }

        $import->loadMissing('user');

        try {
            $credential = $this->credentialFor($import);
            $file = $import->pdfFile()->first() ?? throw CvImportCannotRun::fileMissing();
            $disk = $this->storage->disk($file->disk);

            if (! $disk->exists($file->path)) {
                throw CvImportCannotRun::fileMissing();
            }

            $pdf = (string) $disk->get($file->path);

            $answer = $this->client->readCv($import->provider, $import->model, $credential->api_key, $pdf, self::instructions());

            $import->succeed(self::markdownFrom($answer), now()->toImmutable());
        } catch (BusinessRuleException $exception) {
            $import->fail($exception->getMessage(), now()->toImmutable());
        } catch (GatewayException $exception) {
            report($exception);
            $import->fail(GatewayException::userMessage(), now()->toImmutable());
        } catch (Throwable $exception) {
            report($exception);
            $import->fail(self::unexpectedFailureMessage(), now()->toImmutable());
        }
    }

    public static function unexpectedFailureMessage(): string
    {
        return __('Something went wrong while reading your PDF. Please try again, or paste your CV as text.');
    }

    /** The app-owned prompt, with the standard section names of both CV languages. */
    public static function instructions(): string
    {
        return strtr(trim((string) file_get_contents(resource_path('prompts/app/cv-import.md'))), [
            ':headings_en' => implode(', ', Language::En->sectionHeadings()),
            ':headings_pt' => implode(', ', Language::PtBr->sectionHeadings()),
            ':date_en' => Language::En->dateFormatExample(),
            ':date_pt' => Language::PtBr->dateFormatExample(),
        ]);
    }

    /** Strips code fences the model may add anyway; an answer without a `# Name` heading isn't a CV. */
    private static function markdownFrom(string $answer): string
    {
        $markdown = trim($answer);
        $markdown = (string) preg_replace('/^```[a-z]*\R|\R?```$/i', '', $markdown);
        $markdown = trim($markdown);

        if ($markdown === 'NO_CV' || preg_match('/^# \S/m', $markdown) !== 1) {
            throw CvImportCannotRun::noCvFound();
        }

        return $markdown;
    }

    /** The user's key for the provider the import was queued with: the default first, then any other. */
    private function credentialFor(CvImport $import): AiCredential
    {
        return $import->user->aiCredentials()
            ->get()
            ->filter(fn (AiCredential $credential): bool => $credential->provider === $import->provider)
            ->sortByDesc(fn (AiCredential $credential): bool => $credential->is_default)
            ->first() ?? throw CvImportCannotRun::keyRemoved($import->provider);
    }
}
