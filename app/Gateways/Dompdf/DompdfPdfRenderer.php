<?php

namespace App\Gateways\Dompdf;

use App\Gateways\PdfRenderer;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Filesystem\Filesystem;

/**
 * Pure-PHP fallback (and the test driver): no network, no browser. Remote loading stays off, so the HTML must
 * inline everything, fonts included; dompdf caches the font metrics it builds in `$fontCacheDirectory`.
 */
class DompdfPdfRenderer implements PdfRenderer
{
    public function __construct(
        private Filesystem $files,
        private string $fontCacheDirectory,
    ) {}

    public function render(string $html): string
    {
        $this->files->ensureDirectoryExists($this->fontCacheDirectory);

        $dompdf = new Dompdf(new Options([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'fontDir' => $this->fontCacheDirectory,
            'fontCache' => $this->fontCacheDirectory,
            'tempDir' => sys_get_temp_dir(),
            'chroot' => [$this->fontCacheDirectory],
            'defaultPaperSize' => 'a4',
            'isFontSubsettingEnabled' => true,
        ]));

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
