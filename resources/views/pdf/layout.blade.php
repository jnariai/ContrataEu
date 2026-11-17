{{--
    Shared PDF shell (see docs/defaults/cv-output-format.md): one column, Inter, accent #1F3A5F on section headings
    and dividers, grey meta lines. Headings keep their source case (no text-transform), so the extracted text matches. Nothing in page headers or footers, no tables, images or icons. Self-contained:
    fonts are inlined and nothing is fetched, so Cloudflare and dompdf render the same document.
--}}
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        {!! $fontFaces !!}

        @page { size: A4; margin: 15mm 16mm; }

        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #111827;
        }
        a { color: inherit; text-decoration: none; }
        strong, b { font-weight: 600; }
        p { margin: 0 0 5pt; }

        h1 { font-size: 20pt; font-weight: 700; line-height: 1.15; margin: 0 0 3pt; }
        .meta, h1 + p { color: #6B7280; font-size: 9.5pt; margin: 0 0 10pt; }

        h2 {
            color: #1F3A5F;
            font-size: 12pt;
            font-weight: 700;
            margin: 12pt 0 5pt;
            padding-bottom: 2pt;
            border-bottom: 0.75pt solid #1F3A5F;
        }
        h3 { font-size: 10.5pt; font-weight: 600; margin: 8pt 0 1pt; }
        h3 + p { color: #6B7280; font-size: 9.5pt; margin: 0 0 3pt; }
        h3 + p em { font-style: italic; }

        ul { margin: 2pt 0 5pt; padding-left: 13pt; }
        li { margin: 0 0 2pt; }

        @yield('styles')

        @if ($forScreen ?? false)
        /* On-screen preview only: an A4 sheet with the PDF's margins on a grey background. */
        html { background: #e5e7eb; }
        body { background: #fff; width: 210mm; min-height: 297mm; margin: 16px auto; padding: 15mm 16mm; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.15); }
        a { pointer-events: none; }
        @endif
    </style>
</head>
<body>
@yield('content')
</body>
</html>
