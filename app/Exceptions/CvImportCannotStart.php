<?php

namespace App\Exceptions;

use App\Enums\AiProvider;
use Symfony\Component\HttpFoundation\Response;

/** Why "Import from PDF" can't run right now, with what to do about it. */
class CvImportCannotStart extends BusinessRuleException
{
    public static function missingAiKey(): self
    {
        return new self(
            message: __('Add an AI key first (Settings → AI keys). Your AI account reads the PDF.'),
            errorCode: 'cv_import.missing_ai_key',
        );
    }

    public static function unsupportedProvider(AiProvider $provider): self
    {
        return new self(
            message: __(':provider can’t read PDFs. Make an Anthropic, OpenAI, Gemini or OpenRouter key active, or paste your CV as text.', ['provider' => $provider->label()]),
            errorCode: 'cv_import.unsupported_provider',
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    public static function alreadyRunning(): self
    {
        return new self(
            message: __('Your CV is already being read. Wait for it to finish, then try again.'),
            errorCode: 'cv_import.already_running',
        );
    }

    public static function tooMany(int $retryAfterSeconds): self
    {
        return new self(
            message: __('You reached the limit of PDF imports for now. Try again in :minutes minutes.', ['minutes' => max(1, (int) ceil($retryAfterSeconds / 60))]),
            errorCode: 'cv_import.rate_limited',
            status: Response::HTTP_TOO_MANY_REQUESTS,
            logContext: ['retry_after' => $retryAfterSeconds],
        );
    }
}
