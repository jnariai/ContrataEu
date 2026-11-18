<?php

namespace App\Actions;

use App\Enums\DocumentType;
use App\Enums\Language;
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
    public const array FONTS = [
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

    /**
     * The HTML handed to the renderer; also the on-screen preview. `$content` previews unsaved text; `$forScreen`
     * loads the fonts by URL instead of inlining them and lays the page out on a grey background.
     */
    public function html(GeneratedDocument $document, ?string $content = null, bool $forScreen = false): string
    {
        $document->loadMissing('generation.application.user.candidateProfile');
        $view = $document->type->pdfView() ?? throw new InvalidArgumentException("[{$document->type->value}] documents have no PDF.");
        $content ??= $document->content;
        $generation = $document->generation;
        $application = $generation->application;
        $profile = $application->user->candidateProfile;
        $title = Str::beforeLast($document->pdfFilename($application->user->nameOnFiles()), '.pdf');

        if ($document->type === DocumentType::Cv) {
            return $this->cvHtml($content, $generation->language, $title, $forScreen);
        }

        return $this->views->make($view, [
            ...$this->shell($generation->language, $title, $forScreen),
            'name' => $profile->full_name ?? $application->user->name,
            'contact' => array_values(array_filter([
                $profile?->location,
                $application->user->email,
                ...array_values($profile->links ?? []),
            ])),
            'date' => ($generation->finished_at ?? CarbonImmutable::now())->settings(['locale' => $generation->language->laravelLocale()])->isoFormat('LL'),
            'recipient' => $application->company_name.' · '.$application->role_title,
            'paragraphs' => array_values(array_filter(
                array_map(trim(...), preg_split('/\R\s*\R/u', $content) ?: []),
                static fn (string $paragraph): bool => $paragraph !== '',
            )),
        ])->render();
    }

    /** A CV in the PDF template, from any Markdown (a generated CV or the master CV). Raw HTML and unsafe links are dropped. */
    public function cvHtml(string $markdown, Language $language, string $title, bool $forScreen = false): string
    {
        return $this->views->make('pdf.cv', [
            ...$this->shell($language, $title, $forScreen),
            'body' => Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
        ])->render();
    }

    /** @return array{lang: string, title: string, fontFaces: string, forScreen: bool} */
    private function shell(Language $language, string $title, bool $forScreen): array
    {
        return [
            'lang' => $language->htmlLang(),
            'title' => $title,
            'fontFaces' => $forScreen ? $this->fontFacesByUrl() : $this->fontFaces(),
            'forScreen' => $forScreen,
        ];
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

    /** The same fonts loaded from the app (PdfFontController), so a preview stays small. */
    private function fontFacesByUrl(): string
    {
        return implode("\n", array_map(
            static fn (string $file, array $font): string => sprintf(
                "@font-face { font-family: 'Inter'; font-weight: %d; font-style: %s; src: url('%s') format('truetype'); }",
                $font['weight'],
                $font['style'],
                route('pdf-fonts.show', $file),
            ),
            array_keys(self::FONTS),
            self::FONTS,
        ));
    }
}
