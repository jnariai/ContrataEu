<?php

namespace App\Actions;

use App\Enums\DocumentType;
use App\Exceptions\GenerationCannotStart;
use App\Jobs\RunGenerationJob;
use App\Models\AiCredential;
use App\Models\Application;
use App\Models\Generation;
use App\Models\MasterDocument;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * "Generate": checks the setup, queues a new run for the application's documents and dispatches the job. The run
 * becomes a numbered version only if it succeeds.
 * The AI call itself happens in the queue (RunGeneration), never in the request.
 */
class StartGeneration
{
    public const int MAX_PER_HOUR = 10;

    public function __construct(
        private DatabaseManager $db,
        private Dispatcher $bus,
        private RateLimiter $limiter,
    ) {}

    public function handle(Application $application): Generation
    {
        $user = $application->user;

        if (! $user->candidateProfile()->exists()) {
            throw GenerationCannotStart::missingProfile();
        }

        if (MasterDocument::findFor($user, DocumentType::Cv) === null) {
            throw GenerationCannotStart::missingMasterCv();
        }

        $credential = $user->aiCredentials()->where('is_default', true)->first()
            ?? throw GenerationCannotStart::missingAiKey();

        $rateKey = "generations:user:{$user->id}";

        if ($this->limiter->tooManyAttempts($rateKey, self::MAX_PER_HOUR)) {
            throw GenerationCannotStart::tooMany($this->limiter->availableIn($rateKey));
        }

        $generation = $this->queue($application, $credential);

        $this->limiter->hit($rateKey, 3600);
        $this->bus->dispatch(new RunGenerationJob($generation));

        return $generation;
    }

    private function queue(Application $application, AiCredential $credential): Generation
    {
        try {
            return $this->db->transaction(function () use ($application, $credential): Generation {
                $application = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

                if ($application->generations()->unfinished()->exists()) {
                    throw GenerationCannotStart::alreadyRunning();
                }

                return Generation::queue(
                    $application,
                    $credential->provider,
                    $credential->model,
                    $application->language,
                );
            });
        } catch (UniqueConstraintViolationException) {
            // Two clicks raced past the check; the partial unique index let only one through.
            throw GenerationCannotStart::alreadyRunning();
        }
    }
}
