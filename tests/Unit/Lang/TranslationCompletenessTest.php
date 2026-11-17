<?php

use Symfony\Component\Finder\Finder;

/**
 * Every UI string is an English sentence used as a key (`__('…')` in PHP, `t('…')` / `tk('…')` in React).
 * Each one must exist in lang/pt_BR.json, or Portuguese users see English.
 *
 * @return array<string, list<string>> key => files using it
 */
function usedTranslationKeys(): array
{
    $sources = [
        // [directory, file pattern, call regex]
        [dirname(__DIR__, 3).'/app', '*.php', '/(?<![\w>:])__\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s'],
        [dirname(__DIR__, 3).'/resources/js', '/\.tsx?$/', '/(?<![\w.])tk?\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s'],
    ];

    $keys = [];

    foreach ($sources as [$directory, $pattern, $regex]) {
        $finder = Finder::create()->files()->in($directory)->name($pattern)
            ->exclude(['actions', 'routes', 'wayfinder', 'components/ui']);

        foreach ($finder as $file) {
            preg_match_all($regex, $file->getContents(), $matches);

            foreach ($matches[2] as $raw) {
                $key = stripcslashes($raw);

                // Translation group keys (`validation.required`) live in lang/pt_BR/*.php, not the JSON file.
                if (preg_match('/^[a-z_]+\.[a-z_.]+$/', $key) === 1) {
                    continue;
                }

                $keys[$key][] = $file->getRelativePathname();
            }
        }
    }

    return $keys;
}

it('has a Brazilian Portuguese translation for every UI string', function (): void {
    $translations = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/lang/pt_BR.json'), true, flags: JSON_THROW_ON_ERROR);

    $missing = array_diff_key(usedTranslationKeys(), $translations);

    expect($missing)->toBeEmpty('Missing in lang/pt_BR.json: '.json_encode(array_map(fn (array $files): string => $files[0], $missing), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
});

it('finds the keys it is supposed to check', function (): void {
    expect(usedTranslationKeys())->toHaveKeys(['Log in', 'CV', 'Profile updated.']);
});
