<?php

return [

    /*
    | How documents become PDFs: `cloudflare` (Browser Rendering API, keeps Chromium off the small VPS)
    | or `dompdf` (pure PHP fallback, no network).
    */

    'driver' => env('PDF_DRIVER', 'cloudflare'),

    'cloudflare' => [
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'token' => env('CLOUDFLARE_API_TOKEN'),
        'timeout' => (int) env('CLOUDFLARE_BROWSER_RENDERING_TIMEOUT', 30),
    ],

];
