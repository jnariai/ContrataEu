<?php

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->beforeEach(function (): void {
        Http::preventStrayRequests();
        fakeFilesDisk(); // no Feature test ever touches the real bucket
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A structured AI answer that satisfies the output contract, for Prism::fake().
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function generationAnswer(array $overrides = []): array
{
    return [
        'report' => [
            'jd_analysis' => [
                'must_haves' => ['5+ years PHP', 'Laravel'],
                'nice_to_haves' => ['AWS'],
                'seniority' => 'Senior',
                'domain' => 'Payments',
                'keywords' => ['PHP', 'Laravel', 'PostgreSQL', 'AWS'],
                'knockouts' => ['EU work authorization'],
            ],
            'matches' => [
                ['requirement' => 'Laravel', 'evidence' => 'Backend Engineer at Acme', 'strength' => 'strong'],
                ['requirement' => 'AWS', 'evidence' => 'Not in the master CV', 'strength' => 'missing'],
            ],
            'keyword_coverage' => ['matched' => 3, 'total' => 4, 'missing' => ['AWS']],
            'red_flags' => [],
            'gaps' => ['No AWS experience'],
            'todos' => ['How much faster did billing get?'],
            'interview_questions' => ['Tell me about the billing migration.'],
        ],
        'cv_markdown' => "# Jane Doe\nLisbon, Portugal · jane@example.com\n\n## Experience\n### Backend Engineer — Acme\n*Mar 2022 – Present · Remote*\n- Cut billing run time by [TODO: metric?] with queued Laravel jobs.\n",
        ...$overrides,
    ];
}

function fakeGenerationResponse(array $structured, int $inputTokens = 1500, int $outputTokens = 2500): StructuredResponseFake
{
    return StructuredResponseFake::make()
        ->withStructured($structured)
        ->withUsage(new Usage($inputTokens, $outputTokens));
}

/**
 * Fakes the files disk (R2) and makes its signed URLs predictable: https://r2.test/{path}?expires=…&{options}.
 */
function fakeFilesDisk(): FilesystemAdapter
{
    $disk = Storage::fake(config('filesystems.files_disk'));

    $disk->buildTemporaryUrlsUsing(
        fn (string $path, DateTimeInterface $expiresAt, array $options = []): string => 'https://r2.test/'.$path.'?'.http_build_query(['expires' => $expiresAt->getTimestamp(), ...$options]),
    );

    return $disk;
}
