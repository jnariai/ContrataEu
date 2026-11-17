<?php

namespace App\Exceptions;

use App\Enums\AiProvider;

/** Something the generation needs was removed between "Generate" and the moment the job ran. */
class GenerationCannotRun extends BusinessRuleException
{
    public static function keyRemoved(AiProvider $provider): self
    {
        return new self(
            message: __('The :provider key for this generation was deleted. Add a key and generate again.', ['provider' => $provider->label()]),
            errorCode: 'generation.key_removed',
            logContext: ['provider' => $provider->value],
        );
    }

    public static function profileRemoved(): self
    {
        return new self(
            message: __('Fill in your candidate profile, then generate again.'),
            errorCode: 'generation.profile_removed',
        );
    }

    public static function masterCvRemoved(): self
    {
        return new self(
            message: __('Your master CV was removed. Add it again, then generate again.'),
            errorCode: 'generation.master_cv_removed',
        );
    }
}
