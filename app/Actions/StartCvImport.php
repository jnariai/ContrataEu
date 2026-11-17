<?php

namespace App\Actions;

use App\Enums\DocumentType;
use App\Enums\FilePurpose;
use App\Exceptions\CvImportCannotStart;
use App\Jobs\RunCvImportJob;
use App\Models\CvImport;
use App\Models\User;
use App\ValueObjects\StoragePath;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * "Import from PDF": stores the CV PDF in the bucket and queues the user's AI key to read it into Markdown.
 * The result only fills the CV editor; nothing changes until the user saves (SaveMasterDocument adopts the PDF).
 */
class StartCvImport
{
    public const int MAX_PER_HOUR = 10;

    public function __construct(
        private StoreFile $storeFile,
        private Dispatcher $bus,
        private RateLimiter $limiter,
    ) {}

    public function handle(User $user, UploadedFile $pdf): CvImport
    {
        $credential = $user->aiCredentials()->where('is_default', true)->first()
            ?? throw CvImportCannotStart::missingAiKey();

        if (! $credential->provider->readsPdf()) {
            throw CvImportCannotStart::unsupportedProvider($credential->provider);
        }

        if ($user->cvImports()->unfinished()->exists()) {
            throw CvImportCannotStart::alreadyRunning();
        }

        $rateKey = "cv-imports:user:{$user->id}";

        if ($this->limiter->tooManyAttempts($rateKey, self::MAX_PER_HOUR)) {
            throw CvImportCannotStart::tooMany($this->limiter->availableIn($rateKey));
        }

        // Only the newest import is kept: older results nobody saved are replaced (with their PDF).
        $user->cvImports()->get()->each(fn (CvImport $previous) => $previous->delete());

        try {
            $import = CvImport::queue($user, $credential->provider, $credential->model);
        } catch (UniqueConstraintViolationException) {
            // Two uploads raced past the check; the partial unique index let only one through.
            throw CvImportCannotStart::alreadyRunning();
        }

        try {
            $this->storeFile->handle(
                $user,
                $import,
                FilePurpose::MasterOriginal,
                StoragePath::forMasterOriginal($user->id, DocumentType::Cv, 'pdf'),
                $pdf,
            );
        } catch (Throwable $exception) {
            $import->delete();

            throw $exception;
        }

        $this->limiter->hit($rateKey, 3600);
        $this->bus->dispatch(new RunCvImportJob($import));

        return $import;
    }
}
