<?php

namespace App\Actions;

use App\Datas\GenerationOutput;
use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\GeneratedAnswer;
use App\Models\GeneratedDocument;
use App\Models\Generation;
use App\Models\ScreeningQuestion;
use Illuminate\Database\DatabaseManager;

/**
 * Saves one run's result in a single transaction: the report, the documents, the answers, and the application now
 * showing this version, which only now gets its number. Either everything is saved or nothing is, so a failed run never leaves half a package.
 */
class PersistGenerationOutput
{
    public function __construct(private DatabaseManager $db) {}

    /** @param list<ScreeningQuestion> $questions the questions in the order they were sent to the AI */
    public function handle(Generation $generation, GenerationOutput $output, array $questions): void
    {
        $this->db->transaction(function () use ($generation, $output, $questions): void {
            // Lock the application so the version number is taken once, even if two runs finished together.
            $application = Application::query()->whereKey($generation->application_id)->lockForUpdate()->firstOrFail();

            GeneratedDocument::write($generation, DocumentType::Cv, $output->cvMarkdown);

            if ($output->coverLetter !== null) {
                GeneratedDocument::write($generation, DocumentType::CoverLetter, $output->coverLetter);
            }

            if ($output->recruiterMessage !== null) {
                GeneratedDocument::write($generation, DocumentType::RecruiterMessage, $output->recruiterMessage);
            }

            foreach ($output->answers as $answer) {
                $question = $questions[$answer->questionNumber - 1] ?? null;

                if ($question !== null) {
                    GeneratedAnswer::write($generation, $question, $answer->answer);
                }
            }

            $generation->succeed($application->nextGenerationVersion(), $output->report, $output->inputTokens, $output->outputTokens, now()->toImmutable());
            $application->adoptGeneration($generation);
            $generation->setRelation('application', $application);
        });
    }
}
