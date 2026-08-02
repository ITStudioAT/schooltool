<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $document['title'] }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        @page {
            size: A4 {{ $document['orientation'] }};
            margin: {{ $document['layout']['top_margin_millimetres'] }}mm {{ $document['layout']['right_margin_millimetres'] }}mm {{ $document['layout']['bottom_margin_millimetres'] }}mm {{ $document['layout']['left_margin_millimetres'] }}mm;
        }

        html {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            margin: 0;
            background: #ffffff;
            color: #20302f;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            line-height: 1.4;
        }

        .title-page {
            position: relative;
            height: {{ $document['orientation'] === 'landscape' ? '170mm' : '260mm' }};
            page-break-after: always;
            overflow: hidden;
        }

        .title-page-only {
            page-break-after: auto;
        }

        .title-page-accent {
            width: 18mm;
            height: 1.5mm;
            margin-top: 8mm;
            background: #16a394;
        }

        .title-page-content {
            padding-top: 48mm;
        }

        .title-page-label {
            margin-bottom: 4mm;
            color: #11776d;
            font-size: 8pt;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .title-page-title {
            max-width: 155mm;
            margin: 0;
            color: #173a38;
            font-size: 31pt;
            font-weight: 700;
            letter-spacing: -0.025em;
            line-height: 1.08;
        }

        .title-page-subtitle {
            max-width: 140mm;
            margin: 6mm 0 0;
            color: #607572;
            font-size: 13pt;
            line-height: 1.4;
        }

        .title-page-comments {
            max-width: 145mm;
            margin-top: 13mm;
            padding: 4mm 5mm;
            border-left: 1mm solid #a9c8c4;
            background: #f3f8f7;
            color: #475d5a;
            font-size: 9pt;
        }

        .title-page-comments-label,
        .title-page-detail-label {
            display: block;
            margin-bottom: 1.5mm;
            color: #71817f;
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .title-page-details {
            position: absolute;
            right: 0;
            bottom: 8mm;
            left: 0;
            padding-top: 5mm;
            border-top: 0.25mm solid #cad9d7;
        }

        .title-page-detail {
            display: inline-block;
            min-width: 62mm;
            margin-right: 10mm;
            vertical-align: top;
        }

        .title-page-detail-value {
            color: #20302f;
            font-size: 10pt;
            font-weight: 700;
        }

        .pdf-table-generator {
            width: 100%;
        }

        .document-table-page + .document-table-page {
            page-break-before: always;
        }

        .document-table-page {
            page-break-inside: avoid;
        }

        .document-metadata {
            padding: 2.5mm 3mm;
            border: 0.25mm solid #d8e4e2;
            background: #f3f8f7;
        }

        .metadata-item {
            display: inline-block;
            margin-right: 7mm;
            white-space: nowrap;
        }

        .metadata-label {
            color: #71817f;
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .metadata-value {
            margin-left: 1.5mm;
            color: #20302f;
            font-weight: 700;
        }

        .document-table {
            width: 100%;
            border-collapse: collapse;
        }

        .document-table thead {
            display: table-header-group;
        }

        .document-table tr {
            page-break-inside: avoid;
        }

        .document-table th,
        .document-table td {
            padding: 2.6mm 2.8mm;
            border-bottom: 0.25mm solid #dce5e4;
            overflow-wrap: break-word;
            text-align: left;
            vertical-align: top;
        }

        .document-table th {
            padding: 1.4mm 2.8mm;
            border-bottom: 0.6mm solid #16a394;
            background: #e4f3f1;
            color: #173a38;
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .document-table thead tr:last-child {
            border-bottom: 0.6mm solid #16a394;
        }

        .document-table td {
            padding: 1.2mm 2.8mm;
            font-size: 10pt;
            line-height: 1.2;
        }

        .document-table th:first-child,
        .document-table td:first-child {
            padding-left: 0;
            border-right: {{ $document['layout']['first_column_divider_width_millimetres'] }}mm solid #b9cecb;
        }

        .document-table th:last-child,
        .document-table td:last-child {
            padding-right: 0;
        }

        .document-table .running-header-space th {
            height: {{ $document['layout']['running_header_height_millimetres'] }}mm;
            padding: 0;
            border: 0;
            background: transparent;
            font-size: 0;
            line-height: 0;
        }

        .document-table .document-metadata-row th {
            padding: 0 0 5mm;
            border: 0;
            background: transparent;
            font-size: 9pt;
            font-weight: 400;
            letter-spacing: normal;
            text-transform: none;
        }

        .table-cell-line {
            display: block;
        }

        .table-cell-line + .table-cell-line {
            margin-top: 0.2mm;
            font-weight: 400;
        }

        .document-table tbody tr:nth-child(even) td {
            background: #f8fbfa;
        }

        .document-table th.spacer-column {
            padding: 0;
            border-right: 0;
            border-bottom: 0.6mm solid #16a394 !important;
            border-left: 0;
            background: #e4f3f1 !important;
        }

        .document-table td.spacer-column {
            padding: 0;
            border: 0;
            background: #ffffff !important;
        }

        .document-table td.row-spanning-column-cell {
            position: relative;
            overflow: visible;
            background: #d9f0df !important;
            text-align: center;
            vertical-align: middle;
        }

        .rotated-table-cell {
            display: inline-block;
            width: 38mm;
            margin-right: -14mm;
            margin-left: -14mm;
            line-height: 1.15;
            overflow: visible;
            text-align: center;
            transform-origin: center center;
            white-space: normal;
        }

        .document-table tbody td:first-child {
            color: #173a38;
            font-size: 10pt;
            font-weight: 700;
        }

        .document-table .align-center {
            text-align: center;
        }

        .document-table .align-right {
            text-align: right;
        }

        .empty-row td {
            padding: 10mm 4mm;
            color: #71817f;
            font-weight: 400 !important;
            text-align: center;
        }

        .document-note {
            margin-top: 5mm;
            padding-left: 3mm;
            border-left: 0.7mm solid #a9c8c4;
            color: #647674;
            font-size: 8pt;
        }
    </style>
</head>
<body>
    @if(isset($document['title_page']))
        @php($titlePage = array_merge([
            'label' => 'Document',
            'title' => $document['title'],
            'subtitle' => $document['subtitle'] ?? '',
            'user_name' => $document['user_name'] ?? '',
            'date_time' => $document['print_date_time'] ?? '',
            'comments' => '',
        ], $document['title_page']))

        <section @class([
            'title-page',
            'title-page-only' => $document['title_page_only'] ?? false,
        ])>
            <div class="title-page-accent"></div>

            <div class="title-page-content">
                <div class="title-page-label">{{ $titlePage['label'] }}</div>
                <h1 class="title-page-title">{{ $titlePage['title'] }}</h1>

                @if($titlePage['subtitle'] !== '')
                    <p class="title-page-subtitle">{{ $titlePage['subtitle'] }}</p>
                @endif

                @if($titlePage['comments'] !== '')
                    <div class="title-page-comments">
                        <span class="title-page-comments-label">Comments</span>
                        {{ $titlePage['comments'] }}
                    </div>
                @endif
            </div>

            @if($titlePage['user_name'] !== '' || $titlePage['date_time'] !== '')
                <div class="title-page-details">
                    @if($titlePage['user_name'] !== '')
                        <div class="title-page-detail">
                            <span class="title-page-detail-label">Prepared for</span>
                            <span class="title-page-detail-value">{{ $titlePage['user_name'] }}</span>
                        </div>
                    @endif

                    @if($titlePage['date_time'] !== '')
                        <div class="title-page-detail">
                            <span class="title-page-detail-label">Created</span>
                            <span class="title-page-detail-value">{{ $titlePage['date_time'] }}</span>
                        </div>
                    @endif
                </div>
            @endif
        </section>
    @endif

    @unless($document['title_page_only'] ?? false)
        <main class="pdf-table-generator">
            @foreach($document['table_pages'] as $tablePage)
                <section class="document-table-page">
                    <table
                        class="document-table"
                        @if($tablePage['table_layout_width'] !== null) style="width: {{ $tablePage['table_layout_width'] }};" @endif>
                        <colgroup>
                            @foreach($tablePage['columns'] as $column)
                                @php($layoutWidth = $tablePage['column_layout_widths'][$loop->index] ?? $column['width'] ?? null)
                                <col @if($layoutWidth !== null) style="width: {{ $layoutWidth }};" @endif>
                            @endforeach
                        </colgroup>
                        <thead>
                            <tr class="running-header-space" aria-hidden="true">
                                @foreach($tablePage['columns'] as $column)
                                    @php($layoutWidth = $tablePage['column_layout_widths'][$loop->index] ?? $column['width'] ?? null)
                                    <th @if($layoutWidth !== null) style="width: {{ $layoutWidth }};" @endif></th>
                                @endforeach
                            </tr>
                            @if(! empty($document['metadata']))
                                <tr class="document-metadata-row">
                                    <th colspan="{{ count($tablePage['columns']) }}">
                                        <div class="document-metadata">
                                            @foreach($document['metadata'] as $label => $value)
                                                <span class="metadata-item">
                                                    <span class="metadata-label">{{ $label }}</span>
                                                    <span class="metadata-value">{{ $value }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    </th>
                                </tr>
                            @endif
                            <tr>
                                @foreach($tablePage['columns'] as $column)
                                    @php($alignment = $column['align'] ?? 'left')
                                    @php($alignment = in_array($alignment, ['left', 'center', 'right'], true) ? $alignment : 'left')
                                    @php($layoutWidth = $tablePage['column_layout_widths'][$loop->index] ?? $column['width'] ?? null)
                                    @php($headerPadding = $column['header_padding'] ?? null)
                                    @php($headerStyles = array_filter([
                                        $layoutWidth !== null ? "width: {$layoutWidth};" : null,
                                        'border-bottom: 0.6mm solid #16a394 !important;',
                                        isset($column['header_font_size']) ? "font-size: {$column['header_font_size']}pt;" : null,
                                        $headerPadding !== null
                                            ? "padding: {$headerPadding['top']}mm {$headerPadding['right']}mm {$headerPadding['bottom']}mm {$headerPadding['left']}mm;"
                                            : null,
                                    ]))
                                    <th
                                        @class([
                                            "align-{$alignment}",
                                            'spacer-column' => $column['is_spacer'] ?? false,
                                        ])
                                        @if($headerStyles !== []) style="{{ implode(' ', $headerStyles) }}" @endif>
                                        {{ $column['label'] }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tablePage['rows'] as $row)
                                <tr>
                                    @foreach($tablePage['columns'] as $column)
                                        @php($rowSpanValue = isset($column['row_span_value']) ? (string) $column['row_span_value'] : null)
                                        @continue($rowSpanValue !== null && ! $loop->parent->first)
                                        @php($alignment = $column['align'] ?? 'left')
                                        @php($alignment = in_array($alignment, ['left', 'center', 'right'], true) ? $alignment : 'left')
                                        @php($cellValue = data_get($row, $column['key'], '—'))
                                        @php($layoutWidth = $tablePage['column_layout_widths'][$loop->index] ?? $column['width'] ?? null)
                                        @php($cellPadding = $column['cell_padding'] ?? null)
                                        @php($cellStyles = array_filter([
                                            $layoutWidth !== null ? "width: {$layoutWidth};" : null,
                                            isset($column['font_size']) ? "font-size: {$column['font_size']}pt;" : null,
                                            isset($column['cell_background']) ? "background-color: {$column['cell_background']};" : null,
                                            $cellPadding !== null
                                                ? "padding: {$cellPadding['top']}mm {$cellPadding['right']}mm {$cellPadding['bottom']}mm {$cellPadding['left']}mm;"
                                                : null,
                                        ]))
                                        <td
                                            @class([
                                                "align-{$alignment}",
                                                'spacer-column' => $column['is_spacer'] ?? false,
                                                'row-spanning-column-cell' => $rowSpanValue !== null,
                                            ])
                                            @if($rowSpanValue !== null) rowspan="{{ count($tablePage['rows']) }}" @endif
                                            @if($cellStyles !== []) style="{{ implode(' ', $cellStyles) }}" @endif>
                                            @if($rowSpanValue !== null)
                                                @php($rowSpanRotation = isset($column['row_span_rotation']) && is_numeric($column['row_span_rotation']) ? (float) $column['row_span_rotation'] : 0)
                                                @php($rowSpanStyles = array_filter([
                                                    "transform: rotate({$rowSpanRotation}deg);",
                                                    isset($column['row_span_font_size']) ? "font-size: {$column['row_span_font_size']}pt;" : null,
                                                ]))
                                                <span
                                                    class="rotated-table-cell"
                                                    style="{{ implode(' ', $rowSpanStyles) }}">
                                                    {{ $rowSpanValue }}
                                                </span>
                                            @elseif(is_array($cellValue))
                                                @foreach($cellValue as $line)
                                                    <span class="table-cell-line">{{ $line }}</span>
                                                @endforeach
                                            @else
                                                {{ $cellValue }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr class="empty-row">
                                    <td colspan="{{ count($tablePage['columns']) }}">
                                        {{ $tablePage['empty_message'] ?? $document['empty_message'] ?? 'No table data available.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            @endforeach

            @if(($document['note'] ?? '') !== '')
                <footer class="document-note">{{ $document['note'] }}</footer>
            @endif
        </main>
    @endunless
</body>
</html>
