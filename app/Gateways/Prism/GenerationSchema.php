<?php

namespace App\Gateways\Prism;

use App\Datas\BuiltPrompt;
use App\Datas\GeneratedAnswerDraft;
use App\Datas\GenerationOutput;
use App\Datas\GenerationReport;
use App\Enums\MatchStrength;
use InvalidArgumentException;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/**
 * The app-owned output contract as a structured-output schema, and the reader that turns the model's answer into
 * a GenerationOutput. Optional documents are left out of the schema when they weren't requested, and answers are
 * only asked for when there are screening questions. Users can override instructions, never this.
 */
class GenerationSchema
{
    public static function for(BuiltPrompt $prompt): ObjectSchema
    {
        $properties = [self::report(), new StringSchema('cv_markdown', 'The tailored CV in the required Markdown source structure.')];
        $required = ['report', 'cv_markdown'];

        if ($prompt->wantsCoverLetter) {
            $properties[] = new StringSchema('cover_letter', 'The cover letter, plain text paragraphs separated by a blank line.');
            $required[] = 'cover_letter';
        }

        if ($prompt->wantsRecruiterMessage) {
            $properties[] = new StringSchema('recruiter_message', 'The recruiter message, plain text.');
            $required[] = 'recruiter_message';
        }

        if ($prompt->questionCount > 0) {
            $properties[] = new ArraySchema('screening_answers', 'One answer per screening question.', new ObjectSchema(
                'screening_answer',
                'The answer to one screening question.',
                [
                    new NumberSchema('question_number', 'The question number from <screening_questions>.'),
                    new StringSchema('answer', 'The answer, plain text.'),
                ],
                ['question_number', 'answer'],
            ));
            $required[] = 'screening_answers';
        }

        return new ObjectSchema('application_package', 'Tailored application package for one job.', $properties, $required);
    }

    /**
     * @param  array<array-key, mixed>  $structured
     *
     * @throws InvalidArgumentException when the answer doesn't fit the contract
     */
    public static function read(array $structured, BuiltPrompt $prompt, int $inputTokens, int $outputTokens): GenerationOutput
    {
        $report = $structured['report'] ?? null;

        if (! is_array($report)) {
            throw new InvalidArgumentException('The answer has no report.');
        }

        return new GenerationOutput(
            report: GenerationReport::fromArray($report),
            cvMarkdown: self::text($structured, 'cv_markdown'),
            coverLetter: $prompt->wantsCoverLetter ? self::text($structured, 'cover_letter') : null,
            recruiterMessage: $prompt->wantsRecruiterMessage ? self::text($structured, 'recruiter_message') : null,
            answers: $prompt->questionCount > 0 ? self::answers($structured, $prompt->questionCount) : [],
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
        );
    }

    private static function report(): ObjectSchema
    {
        $strings = static fn (string $name, string $description): ArraySchema => new ArraySchema($name, $description, new StringSchema('item', $description));

        return new ObjectSchema('report', 'Analysis for the candidate, in the report language.', [
            new ObjectSchema('jd_analysis', 'What the job asks for.', [
                $strings('must_haves', 'Must-have requirements.'),
                $strings('nice_to_haves', 'Nice-to-have requirements.'),
                new StringSchema('seniority', 'Seniority level the job targets.'),
                new StringSchema('domain', 'Core problem domain.'),
                $strings('keywords', 'Top 10 keywords, with the job description spelling.'),
                $strings('knockouts', 'Likely knockout criteria.'),
            ], ['must_haves', 'nice_to_haves', 'seniority', 'domain', 'keywords', 'knockouts']),
            new ArraySchema('matches', 'One row per requirement.', new ObjectSchema('match', 'Evidence for one requirement.', [
                new StringSchema('requirement', 'The requirement.'),
                new StringSchema('evidence', 'Evidence from the master CV, or why it is missing.'),
                new EnumSchema('strength', 'How well the evidence meets it.', array_column(MatchStrength::cases(), 'value')),
            ], ['requirement', 'evidence', 'strength'])),
            new ObjectSchema('keyword_coverage', 'Top keywords present in the tailored CV.', [
                new NumberSchema('matched', 'How many top keywords the tailored CV contains.'),
                new NumberSchema('total', 'How many top keywords there are.'),
                $strings('missing', 'Top keywords the tailored CV does not contain.'),
            ], ['matched', 'total', 'missing']),
            $strings('red_flags', 'Red flags found.'),
            $strings('gaps', 'Honest gaps between the job and the candidate.'),
            $strings('todos', 'Questions for the candidate, matching the [TODO: …] placeholders.'),
            $strings('interview_questions', 'Likely interview questions about claims in the CV.'),
        ], ['jd_analysis', 'matches', 'keyword_coverage', 'red_flags', 'gaps', 'todos', 'interview_questions']);
    }

    /** @param array<array-key, mixed> $structured */
    private static function text(array $structured, string $key): string
    {
        $value = $structured[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("The answer has no [{$key}].");
        }

        return trim($value);
    }

    /**
     * @param  array<array-key, mixed>  $structured
     * @return list<GeneratedAnswerDraft>
     */
    private static function answers(array $structured, int $questionCount): array
    {
        $answers = [];

        foreach ((array) ($structured['screening_answers'] ?? []) as $row) {
            $number = is_array($row) && is_numeric($row['question_number'] ?? null) ? (int) $row['question_number'] : 0;
            $answer = is_array($row) && is_string($row['answer'] ?? null) ? trim($row['answer']) : '';

            // Ignore answers to questions that weren't asked, duplicates and empty answers.
            if ($number >= 1 && $number <= $questionCount && $answer !== '' && ! isset($answers[$number])) {
                $answers[$number] = new GeneratedAnswerDraft($number, $answer);
            }
        }

        ksort($answers);

        return array_values($answers);
    }
}
