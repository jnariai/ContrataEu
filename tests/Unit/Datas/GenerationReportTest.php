<?php

use App\Datas\GenerationReport;
use App\Enums\MatchStrength;
use App\ValueObjects\KeywordCoverage;

function reportData(): array
{
    return [
        'jd_analysis' => [
            'must_haves' => ['Laravel'], 'nice_to_haves' => ['AWS'], 'seniority' => 'Senior',
            'domain' => 'Payments', 'keywords' => ['Laravel', 'AWS'], 'knockouts' => [],
        ],
        'matches' => [['requirement' => 'Laravel', 'evidence' => 'Acme', 'strength' => 'strong']],
        'keyword_coverage' => ['matched' => 1, 'total' => 2, 'missing' => ['AWS']],
        'red_flags' => [], 'gaps' => ['AWS'], 'todos' => [], 'interview_questions' => ['Why Laravel?'],
    ];
}

it('round-trips through its stored array shape', function (): void {
    $report = GenerationReport::fromArray(reportData());

    expect($report->toArray())->toBe(reportData())
        ->and($report->matches[0]->strength)->toBe(MatchStrength::Strong);
});

it('accepts a strength in any letter case', function (): void {
    $data = reportData();
    $data['matches'][0]['strength'] = 'Partial';

    expect(GenerationReport::fromArray($data)->matches[0]->strength)->toBe(MatchStrength::Partial);
});

it('refuses a report with missing parts or an unknown strength', function (array $data): void {
    GenerationReport::fromArray($data);
})->with([
    'no analysis' => [array_diff_key(reportData(), ['jd_analysis' => true])],
    'no coverage' => [array_diff_key(reportData(), ['keyword_coverage' => true])],
    'unknown strength' => [array_replace_recursive(reportData(), ['matches' => [['strength' => 'great']]])],
])->throws(InvalidArgumentException::class);

it('computes the keyword coverage percentage', function (int $matched, int $total, int $percentage): void {
    expect(KeywordCoverage::of($matched, $total, [])->percentage())->toBe($percentage);
})->with([
    [7, 10, 70],
    [2, 3, 67],
    [0, 0, 0],
    [10, 10, 100],
]);

it('refuses impossible keyword coverage', function (): void {
    KeywordCoverage::of(5, 4, []);
})->throws(InvalidArgumentException::class);
