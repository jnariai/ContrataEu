<?php

use App\Gateways\Cloudflare\CloudflareException;
use App\Gateways\Cloudflare\CloudflarePdfRenderer;
use App\Gateways\Dompdf\DompdfPdfRenderer;
use App\Gateways\PdfRenderer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const BROWSER_RENDERING_URL = 'https://api.cloudflare.com/client/v4/accounts/test-account/browser-rendering/pdf';

function cloudflareRenderer(): CloudflarePdfRenderer
{
    config(['pdf.driver' => 'cloudflare']);

    return app(PdfRenderer::class);
}

it('sends the HTML with the API token and returns the PDF bytes', function (): void {
    Http::fake([BROWSER_RENDERING_URL => Http::response('%PDF-1.7 rendered', 200, ['Content-Type' => 'application/pdf'])]);

    expect(cloudflareRenderer()->render('<h1>Jane</h1>'))->toBe('%PDF-1.7 rendered');

    Http::assertSent(fn (Request $request): bool => $request->url() === BROWSER_RENDERING_URL
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer test-token')
        && $request['html'] === '<h1>Jane</h1>'
        && $request['pdfOptions']['format'] === 'a4'
        && $request['pdfOptions']['printBackground'] === true);
});

it('retries when Cloudflare rate-limits or fails, then succeeds', function (int $status): void {
    Sleep::fake();
    Http::fake([BROWSER_RENDERING_URL => Http::sequence()
        ->push(['success' => false], $status)
        ->push('%PDF-1.7 rendered', 200)]);

    expect(cloudflareRenderer()->render('<p>x</p>'))->toBe('%PDF-1.7 rendered');
    Http::assertSentCount(2);
})->with([429, 500, 503]);

it('gives up after two retries, waiting 2 s then 5 s', function (): void {
    Sleep::fake();
    Http::fake([BROWSER_RENDERING_URL => Http::response(['success' => false], 429)]);

    expect(fn () => cloudflareRenderer()->render('<p>x</p>'))->toThrow(CloudflareException::class);
    Http::assertSentCount(3);
    Sleep::assertSequence([Sleep::for(2_000)->milliseconds(), Sleep::for(5_000)->milliseconds()]);
});

it('does not retry a request Cloudflare refuses', function (int $status): void {
    Http::fake([BROWSER_RENDERING_URL => Http::response(['success' => false, 'errors' => [['message' => 'Authentication error']]], $status)]);

    expect(fn () => cloudflareRenderer()->render('<p>x</p>'))
        ->toThrow(fn (CloudflareException $e) => expect($e->getMessage())->not->toContain('Authentication error'));
    Http::assertSentCount(1);
})->with([400, 401, 403, 422]);

it('rejects a successful answer that is not a PDF', function (): void {
    Http::fake([BROWSER_RENDERING_URL => Http::response('<html>oops</html>', 200)]);

    expect(fn () => cloudflareRenderer()->render('<p>x</p>'))->toThrow(CloudflareException::class);
});

it('binds the renderer chosen by the pdf.driver config', function (string $driver, string $class): void {
    config(['pdf.driver' => $driver]);

    expect(app(PdfRenderer::class))->toBeInstanceOf($class);
})->with([
    'cloudflare' => ['cloudflare', CloudflarePdfRenderer::class],
    'dompdf' => ['dompdf', DompdfPdfRenderer::class],
]);

it('refuses an unknown driver', function (): void {
    config(['pdf.driver' => 'wkhtmltopdf']);

    expect(fn () => app(PdfRenderer::class))->toThrow(InvalidArgumentException::class);
});
