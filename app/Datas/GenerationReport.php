<?php

namespace App\Datas;

use App\Enums\MatchStrength;
use App\ValueObjects\KeywordCoverage;
use InvalidArgumentException;

/**
 * The AI's analysis for the candidate: job analysis, requirement matches, keyword coverage, red flags, gaps,
 * TODOs and interview prep. Stored as JSON on the generation (snake_case keys, the same shape the AI returns).
 */
final readonly class GenerationReport
{
    /**
     * @param  list<RequirementMatch>  $matches
     * @param  list<string>  $redFlags
     * @param  list<string>  $gaps
     * @param  list<string>  $todos
     * @param  list<string>  $interviewQuestions
     */
    public function __construct(
        public JobAnalysis $jobAnalysis,
        public array $matches,
        public KeywordCoverage $keywordCoverage,
        public array $redFlags,
        public array $gaps,
        public array $todos,
        public array $interviewQuestions,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     *
     * @throws InvalidArgumentException when the shape is wrong
     */
    public static function fromArray(array $data): self
    {
        $analysis = self::map($data, 'jd_analysis');
        $coverage = self::map($data, 'keyword_coverage');

        return new self(
            jobAnalysis: new JobAnalysis(
                mustHaves: self::strings($analysis, 'must_haves'),
                niceToHaves: self::strings($analysis, 'nice_to_haves'),
                seniority: self::string($analysis, 'seniority'),
                domain: self::string($analysis, 'domain'),
                keywords: self::strings($analysis, 'keywords'),
                knockouts: self::strings($analysis, 'knockouts'),
            ),
            matches: array_map(static function (mixed $row): RequirementMatch {
                if (! is_array($row)) {
                    throw new InvalidArgumentException('A match row must be an object.');
                }

                return new RequirementMatch(
                    requirement: self::string($row, 'requirement'),
                    evidence: self::string($row, 'evidence'),
                    strength: MatchStrength::tryFrom(strtolower(self::string($row, 'strength')))
                        ?? throw new InvalidArgumentException('Unknown match strength.'),
                );
            }, self::list($data, 'matches')),
            keywordCoverage: KeywordCoverage::of(
                self::integer($coverage, 'matched'),
                self::integer($coverage, 'total'),
                self::strings($coverage, 'missing'),
            ),
            redFlags: self::strings($data, 'red_flags'),
            gaps: self::strings($data, 'gaps'),
            todos: self::strings($data, 'todos'),
            interviewQuestions: self::strings($data, 'interview_questions'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'jd_analysis' => [
                'must_haves' => $this->jobAnalysis->mustHaves,
                'nice_to_haves' => $this->jobAnalysis->niceToHaves,
                'seniority' => $this->jobAnalysis->seniority,
                'domain' => $this->jobAnalysis->domain,
                'keywords' => $this->jobAnalysis->keywords,
                'knockouts' => $this->jobAnalysis->knockouts,
            ],
            'matches' => array_map(static fn (RequirementMatch $match): array => [
                'requirement' => $match->requirement,
                'evidence' => $match->evidence,
                'strength' => $match->strength->value,
            ], $this->matches),
            'keyword_coverage' => [
                'matched' => $this->keywordCoverage->matched,
                'total' => $this->keywordCoverage->total,
                'missing' => $this->keywordCoverage->missing,
            ],
            'red_flags' => $this->redFlags,
            'gaps' => $this->gaps,
            'todos' => $this->todos,
            'interview_questions' => $this->interviewQuestions,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function map(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : throw new InvalidArgumentException("Missing object [{$key}].");
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<mixed>
     */
    private static function list(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        return is_array($value) ? array_values($value) : throw new InvalidArgumentException("[{$key}] must be a list.");
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    private static function strings(array $data, string $key): array
    {
        $items = array_map(static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '', self::list($data, $key));

        return array_values(array_filter($items, static fn (string $item): bool => $item !== ''));
    }

    /** @param array<array-key, mixed> $data */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : throw new InvalidArgumentException("Missing text [{$key}].");
    }

    /** @param array<array-key, mixed> $data */
    private static function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : throw new InvalidArgumentException("Missing number [{$key}].");
    }
}
