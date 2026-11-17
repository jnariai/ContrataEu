<?php

namespace App\Datas;

final readonly class JobAnalysis
{
    /**
     * @param  list<string>  $mustHaves
     * @param  list<string>  $niceToHaves
     * @param  list<string>  $keywords
     * @param  list<string>  $knockouts
     */
    public function __construct(
        public array $mustHaves,
        public array $niceToHaves,
        public string $seniority,
        public string $domain,
        public array $keywords,
        public array $knockouts,
    ) {}
}
