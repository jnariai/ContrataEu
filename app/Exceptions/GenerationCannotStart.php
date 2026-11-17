<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/** Why "Generate" can't run right now, with what to do about it. */
class GenerationCannotStart extends BusinessRuleException
{
    public static function missingProfile(): self
    {
        return new self(
            message: __('Fill in your candidate profile first (Settings → Candidate profile).'),
            errorCode: 'generation.missing_profile',
        );
    }

    public static function missingMasterCv(): self
    {
        return new self(
            message: __('Add your master CV first (Settings → Documents). Every generation is tailored from it.'),
            errorCode: 'generation.missing_master_cv',
        );
    }

    public static function missingAiKey(): self
    {
        return new self(
            message: __('Add an AI key first (Settings → AI keys). Generations run on your own AI account.'),
            errorCode: 'generation.missing_ai_key',
        );
    }

    public static function alreadyRunning(): self
    {
        return new self(
            message: __('A generation is already running for this application. Wait for it to finish, then try again.'),
            errorCode: 'generation.already_running',
        );
    }

    public static function tooMany(int $retryAfterSeconds): self
    {
        return new self(
            message: __('You reached the limit of generations for now. Try again in :minutes minutes.', ['minutes' => max(1, (int) ceil($retryAfterSeconds / 60))]),
            errorCode: 'generation.rate_limited',
            status: Response::HTTP_TOO_MANY_REQUESTS,
            logContext: ['retry_after' => $retryAfterSeconds],
        );
    }
}
