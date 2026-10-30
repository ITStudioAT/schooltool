<!doctype html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ $label }} · {{ $title }}</title>
    <style>
        @page { margin: 16mm 14mm 20mm; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #263835; font-size: 10pt; line-height: 1.45; overflow-wrap: break-word; }
        .document-header { background: #2e6655; color: white; padding: 18pt; margin-bottom: 18pt; }
        .document-header h1 { color: white; font-size: 23pt; margin: 4pt 0 8pt; }
        .document-label { font-size: 9pt; letter-spacing: 1pt; }
        h1, h2, h3, h4 { color: #173a40; page-break-after: avoid; }
        h1 { font-size: 17pt; margin-top: 19pt; }
        h2 { font-size: 13pt; margin-top: 16pt; }
        h3, h4 { font-size: 11pt; }
        p, ul, ol { margin: 7pt 0; }
        table { width: 100%; border-collapse: collapse; font-size: 9pt; table-layout: fixed; }
        thead { display: table-header-group; }
        th, td { border: 0.5pt solid #cbd8cf; padding: 6pt; vertical-align: top; overflow-wrap: break-word; }
        th { background: #e9f3ec; }
        tr { page-break-inside: avoid; }
        pre { white-space: pre-wrap; word-wrap: break-word; background: #e9f3ec; padding: 8pt; }
        code { font-family: 'DejaVu Sans Mono', monospace; font-size: 9pt; }
        a { color: #2e6655; }
        .footer { position: fixed; bottom: -12mm; color: #57655d; font-size: 8pt; border-top: 0.5pt solid #cbd8cf; padding-top: 4pt; width: 100%; }
    </style>
</head>
<body>
    <div class="footer">{{ $label }} · {{ $course }}</div>
    <div class="document-header"><div class="document-label">{{ mb_strtoupper($label) }}</div><h1>{{ $title }}</h1><div>{{ $course }}</div></div>
    {!! $html !!}
</body>
</html>
