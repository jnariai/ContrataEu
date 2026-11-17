<?php

namespace App\Actions;

use App\Datas\BuiltPrompt;
use App\Datas\PromptContext;
use App\Enums\Language;

/**
 * Prompt = the user's sections (override or default) ⊕ app-owned contract. The app-owned parts (hard rules, CV source
 * structure, language rules, output description) are always appended after the user's text, so no override can break
 * parsing or the PDF template. Third-party text (job description, job details, questions) is wrapped in tags and the
 * system prompt says to treat it as data.
 */
class BuildGenerationPrompt
{
    public function handle(PromptContext $context): BuiltPrompt
    {
        $system = implode("\n\n", [
            trim($context->system),
            trim($context->playbook),
            $this->appPart('hard-rules'),
        ]);

        $user = implode("\n\n", array_filter([
            $this->tag('candidate_profile', $this->lines($context->candidate)),
            $this->tag('master_cv', $context->masterCv),
            $context->masterCoverLetter !== null ? $this->tag('master_cover_letter', $context->masterCoverLetter) : null,
            $context->recruiterMessageInspiration !== null ? $this->tag('recruiter_message_inspiration', $context->recruiterMessageInspiration) : null,
            $this->tag('job', $this->lines($context->job)),
            $this->tag('job_description', $context->jobDescription),
            $context->questions !== [] ? $this->tag('screening_questions', $this->numbered($context->questions)) : null,
            $this->tag('instructions', implode("\n\n", array_filter([
                trim($context->cvInstructions),
                $context->wantsCoverLetter ? trim($context->coverLetterInstructions) : null,
                $context->wantsRecruiterMessage ? trim($context->recruiterMessageInstructions) : null,
                $context->questions !== [] ? trim($context->screeningAnswerInstructions) : null,
            ]))),
            $this->tag('language_rules', $this->languageRules($context->documentLanguage, $context->reportLanguage)),
            $this->tag('output_contract', $this->appPart('cv-source-structure', $this->structureReplacements($context->documentLanguage))."\n\n".$this->output($context)),
        ]));

        return new BuiltPrompt(
            system: $system,
            user: $user,
            wantsCoverLetter: $context->wantsCoverLetter,
            wantsRecruiterMessage: $context->wantsRecruiterMessage,
            questionCount: count($context->questions),
        );
    }

    private function languageRules(Language $document, Language $report): string
    {
        $rules = $this->appPart('language', [
            ...$this->structureReplacements($document),
            ':document_language' => $document->promptName(),
            ':report_language' => $report->promptName(),
            ':present' => $document->presentWord(),
        ]);

        return $document === Language::PtBr ? $rules."\n\n".$this->appPart('language-pt-br') : $rules;
    }

    private function output(PromptContext $context): string
    {
        $optional = array_filter([
            $context->wantsCoverLetter ? '- `cover_letter`: the cover letter, plain text paragraphs separated by a blank line.' : null,
            $context->wantsRecruiterMessage ? '- `recruiter_message`: the recruiter message, plain text.' : null,
            $context->questions !== [] ? '- `screening_answers`: one answer per screening question, with `question_number` matching its number in `<screening_questions>`.' : null,
        ]);

        return $this->appPart('output', [':optional_fields' => implode("\n", $optional)]);
    }

    /** @return array<string, string> */
    private function structureReplacements(Language $language): array
    {
        $headings = $language->sectionHeadings();

        return [
            ':section_list' => implode(', ', $headings),
            ':date_example' => $language->dateFormatExample(),
            ':remote' => $language->remoteWord(),
            ...array_combine(array_map(static fn (string $key): string => ":{$key}", array_keys($headings)), array_values($headings)),
        ];
    }

    /** @param array<string, string> $replacements */
    private function appPart(string $name, array $replacements = []): string
    {
        $text = trim((string) file_get_contents(resource_path("prompts/app/{$name}.md")));

        // strtr() tries the longest keys first, so ":section_list" is never broken by a shorter key.
        return trim(strtr($text, $replacements));
    }

    private function tag(string $name, string $content): string
    {
        return "<{$name}>\n".trim($content)."\n</{$name}>";
    }

    /** @param array<string, string> $values */
    private function lines(array $values): string
    {
        return implode("\n", array_map(static fn (string $label, string $value): string => "{$label}: {$value}", array_keys($values), $values));
    }

    /** @param list<string> $questions */
    private function numbered(array $questions): string
    {
        return implode("\n", array_map(static fn (int $index, string $question): string => ($index + 1).'. '.$question, array_keys($questions), $questions));
    }
}
