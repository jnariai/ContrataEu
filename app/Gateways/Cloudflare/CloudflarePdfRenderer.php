<?php

namespace App\Gateways\Cloudflare;

use App\Gateways\PdfRenderer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Renders PDFs with Cloudflare Browser Rendering (headless Chromium), so the small VPS never runs a browser.
 * Retries twice on 429, 5xx and connection errors; anything else becomes a CloudflareException.
 */
class CloudflarePdfRenderer implements PdfRenderer
{
    private const string ENDPOINT = 'https://api.cloudflare.com/client/v4/accounts/%s/browser-rendering/pdf';

    private const int CONNECT_TIMEOUT_SECONDS = 5;

    /**
     * Waits before each of the two retries (three tries in all). Browser Rendering limits bursts per account and sends no Retry-After, so the waits
     * are long enough to get past a short burst window without holding the download request too long.
     *
     * @var list<int>
     */
    private const array RETRY_DELAYS_MS = [2_000, 5_000];

    public function __construct(
        private Http $http,
        private string $accountId,
        private string $token,
        private int $timeoutSeconds = 30,
    ) {}

    public function render(string $html): string
    {
        try {
            $response = $this->http
                ->withToken($this->token)
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->retry(
                    self::RETRY_DELAYS_MS,
                    0,
                    fn (Throwable $exception): bool => $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && ($exception->response->status() === 429 || $exception->response->serverError())),
                )
                ->post(sprintf(self::ENDPOINT, rawurlencode($this->accountId)), [
                    'html' => $html,
                    'pdfOptions' => [
                        'format' => 'a4',
                        'printBackground' => true,
                        'preferCSSPageSize' => true,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw CloudflareException::unreachable($exception);
        } catch (RequestException $exception) {
            throw $this->failure($exception->response, $exception);
        }

        $pdf = $response->body();

        if (! str_starts_with($pdf, '%PDF-')) {
            throw CloudflareException::badResponse($response->status());
        }

        return $pdf;
    }

    private function failure(Response $response, RequestException $exception): CloudflareException
    {
        return $response->status() === 429
            ? CloudflareException::rateLimited((int) $response->header('Retry-After') ?: null, $exception)
            : CloudflareException::badResponse($response->status(), $exception);
    }
}
