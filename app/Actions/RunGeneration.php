<?php

namespace App\Actions;

use App\Datas\PromptContext;
use App\Enums\DocumentType;
use App\Enums\PromptKey;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\GenerationCannotRun;
use App\Gateways\GatewayException;
use App\Gateways\Prism\PrismClient;
use App\Models\AiCredential;
use App\Models\Generation;
use App\Models\MasterDocument;
use App\Models\PromptOverride;
use App\Models\ScreeningQuestion;
use App\Models\User;
use Throwable;

/**
 * Runs one queued generation: Queued → Running → (one AI call) → Succeeded, or Failed with a message the user can
 * act on. Called by RunGenerationJob; a duplicate or retried job finds the generation already claimed and does nothing.
 */
class RunGeneration
{
    public function __construct(
        private PrismClient $client,
        private BuildGenerationPrompt $buildPrompt,
        private PersistGenerationOutput $persistOutput,
        private PruneGenerations $pruneGenerations,
    ) {}

    public function handle(Generation $generation): void
    {
        if (! $generation->start(now()->toImmutable())) {
            return;
        }

        $generation->loadMissing('application.user');
        $application = $generation->application;

        try {
            $questions = array_values($application->screeningQuestions()->get()->all());
            $credential = $this->credentialFor($generation, $application->user);
            $prompt = $this->buildPrompt->handle($this->context($generation, $questions));

            $output = $this->client->generate($generation->provider, $generation->model, $credential->api_key, $prompt);

            $this->persistOutput->handle($generation, $output, $questions);
        } catch (BusinessRuleException $exception) {
            $generation->fail($exception->getMessage(), now()->toImmutable());

            return;
        } catch (GatewayException $exception) {
            report($exception);
            $generation->fail(GatewayException::userMessage(), now()->toImmutable());

            return;
        } catch (Throwable $exception) {
            report($exception);
            $generation->fail(self::unexpectedFailureMessage(), now()->toImmutable());

            return;
        }

        $this->pruneGenerations->handle($application);
    }

    public static function unexpectedFailureMessage(): string
    {
        return __('Something went wrong while generating. Please try again. If it keeps happening, contact support.');
    }

    /**
     * The key for the provider this version was queued with: the default one if it still matches, otherwise any key
     * the user still has for that provider (they may have switched the default since).
     */
    private function credentialFor(Generation $generation, User $user): AiCredential
    {
        return $user->aiCredentials()
            ->get()
            ->filter(fn (AiCredential $credential): bool => $credential->provider === $generation->provider)
            ->sortByDesc(fn (AiCredential $credential): bool => $credential->is_default)
            ->first() ?? throw GenerationCannotRun::keyRemoved($generation->provider);
    }

    /** @param list<ScreeningQuestion> $questions */
    private function context(Generation $generation, array $questions): PromptContext
    {
        $application = $generation->application;
        $user = $application->user;
        $profile = $user->candidateProfile ?? throw GenerationCannotRun::profileRemoved();
        $masterCv = MasterDocument::findFor($user, DocumentType::Cv) ?? throw GenerationCannotRun::masterCvRemoved();

        return new PromptContext(
            candidate: array_filter([
                'Name' => $profile->full_name,
                'Email' => $user->email,
                'Headline' => $profile->headline,
                'Location' => $profile->location,
                'Work authorization' => $profile->work_authorization,
                'CV conventions (region)' => $profile->region_convention,
                'LinkedIn' => $profile->links['linkedin'] ?? null,
                'GitHub' => $profile->links['github'] ?? null,
                'Portfolio' => $profile->links['portfolio'] ?? null,
            ], fn (?string $value): bool => filled($value)),
            masterCv: $masterCv->content,
            masterCoverLetter: MasterDocument::findFor($user, DocumentType::CoverLetter)?->content,
            recruiterMessageInspiration: MasterDocument::findFor($user, DocumentType::RecruiterMessage)?->content,
            job: array_filter([
                'Company' => $application->company_name,
                'Role' => $application->role_title,
                'Location' => $application->location,
                'Work mode' => $application->work_mode?->value,
                'Job posting' => $application->job_url,
                'Company notes' => $application->company_notes,
            ], fn (?string $value): bool => filled($value)),
            jobDescription: $application->job_description,
            questions: array_map(fn (ScreeningQuestion $question): string => $question->question, $questions),
            wantsCoverLetter: $application->wants_cover_letter,
            wantsRecruiterMessage: $application->wants_recruiter_message,
            documentLanguage: $generation->language,
            reportLanguage: $user->locale,
            system: PromptOverride::resolveFor($user, PromptKey::System),
            playbook: PromptOverride::resolveFor($user, PromptKey::Playbook),
            cvInstructions: PromptOverride::resolveFor($user, PromptKey::CvInstructions),
            coverLetterInstructions: PromptOverride::resolveFor($user, PromptKey::CoverLetterInstructions),
            recruiterMessageInstructions: PromptOverride::resolveFor($user, PromptKey::RecruiterMessageInstructions),
            screeningAnswerInstructions: PromptOverride::resolveFor($user, PromptKey::ScreeningAnswerInstructions),
        );
    }
}
