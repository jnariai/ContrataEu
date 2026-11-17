<?php

namespace App\Enums;

/** Configuration only (`config('pdf.driver')`), never stored, so it has no lookup table. */
enum PdfDriver: string
{
    case Cloudflare = 'cloudflare';
    case Dompdf = 'dompdf';
}
