<?php

namespace App\Actions;

use App\Datas\QuestionDraft;
use App\Models\Application;
use App\Models\ScreeningQuestion;

/**
 * Makes the application's screening questions match what the user submitted, in that order.
 * Questions keep their row (and their answers in earlier generations) when reworded; removed ones are deleted,
 * which also deletes their answers. Run inside the caller's transaction.
 */
class SyncScreeningQuestions
{
    /** @param list<QuestionDraft> $drafts */
    public function handle(Application $application, array $drafts): void
    {
        $existing = $application->screeningQuestions()->get()->keyBy('public_id');
        $kept = [];

        foreach ($drafts as $index => $draft) {
            $position = $index + 1;
            $question = $draft->id !== null ? $existing->get($draft->id) : null;

            if ($question instanceof ScreeningQuestion) {
                $question->rephrase($draft->text, $position);
                $kept[] = $question->id;
            } else {
                $kept[] = ScreeningQuestion::ask($application, $draft->text, $position)->id;
            }
        }

        $existing->reject(fn (ScreeningQuestion $question): bool => in_array($question->id, $kept, true))
            ->each(fn (ScreeningQuestion $question) => $question->delete());
    }
}
