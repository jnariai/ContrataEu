<?php

namespace App\Actions;

use App\Enums\DocumentType;
use App\Gateways\PdfRenderer;
use App\Models\GeneratedDocument;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Markdown (CV) or plain text (cover letter) → self-contained HTML in the document's Blade view → PDF bytes.
 * The HTML inlines the Inter fonts, so every renderer produces the same file without fetching anything.
 */
class RenderDocumentPdf
{
    /** @var array<string, array{weight: int, style: string}> */
    private const array FONTS = [
        'Inter-Regular.ttf' => ['weight' => 400, 'style' => 'normal'],
        'Inter-Italic.ttf' => ['weight' => 400, 'style' => 'italic'],
        'Inter-SemiBold.ttf' => ['weight' => 600, 'style' => 'normal'],
        'Inter-Bold.ttf' => ['weight' => 700, 'style' => 'normal'],
    ];

    public function __construct(
        private PdfRenderer $renderer,
        private ViewFactory $views,
    ) {}

    public function handle(GeneratedDocument $document): string
    {
        return $this->renderer->render($this->html($document));
    }

    /** The HTML handed to the renderer; public so the templates can be tested without rendering a PDF. */
    public function html(GeneratedDocument $document): string
    {
        $document->loadMissing('generation.application.user.candidateProfile');
        $view = $document->type->pdfView() ?? throw new InvalidArgumentException("[{$document->type->value}] documents have no PDF.");
        $generation = $document->generation;
        $application = $generation->application;
        $profile = $application->user->candidateProfile;

        return $this->views->make($view, [
            'lang' => $generation->language->htmlLang(),
            'title' => Str::beforeLast($document->pdfFilename($profile->full_name ?? $application->user->name), '.pdf'),
            'fontFaces' => $this->fontFaces(),
            ...match ($document->type) {
                DocumentType::Cv => [
                    'body' => Str::markdown($document->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
                ],
                default => [
                    'name' => $profile->full_name ?? $application->user->name,
                    'contact' => array_values(array_filter([
                        $profile?->location,
                        $application->user->email,
                        ...array_values($profile->links ?? []),
                    ])),
                    'date' => ($generation->finished_at ?? CarbonImmutable::now())->settings(['locale' => $generation->language->laravelLocale()])->isoFormat('LL'),
                    'recipient' => $application->company_name.' · '.$application->role_title,
                    'paragraphs' => array_values(array_filter(
                        array_map(trim(...), preg_split('/\R\s*\R/u', $document->content) ?: []),
                        static fn (string $paragraph): bool => $paragraph !== '',
                    )),
                ],
            },
        ])->render();
    }

    /** `@font-face` rules with the TTFs inlined as data URIs (built once per process). */
    private function fontFaces(): string
    {
        static $css = null;

        return $css ??= implode("\n", array_map(
            static fn (string $file, array $font): string => sprintf(
                "@font-face { font-family: 'Inter'; font-weight: %d; font-style: %s; src: url(data:font/ttf;base64,%s) format('truetype'); }",
                $font['weight'],
                $font['style'],
                base64_encode((string) file_get_contents(resource_path('fonts/'.$file))),
            ),
            array_keys(self::FONTS),
            self::FONTS,
        ));
    }
}
