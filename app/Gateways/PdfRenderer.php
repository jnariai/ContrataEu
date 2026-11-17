<?php

namespace App\Gateways;

/**
 * Turns a complete, self-contained HTML document (fonts inlined, no remote assets) into PDF bytes.
 * The driver is chosen by `config('pdf.driver')` in AppServiceProvider.
 */
interface PdfRenderer
{
    /** @throws GatewayException when a remote renderer is unavailable or refuses the document */
    public function render(string $html): string;
}
