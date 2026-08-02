<?php

namespace App\Services;

use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class PdfTableGenerator
{
    public const TOP_MARGIN_MILLIMETRES = 10.0;

    public const RIGHT_MARGIN_MILLIMETRES = 10.0;

    public const BOTTOM_MARGIN_MILLIMETRES = 10.0;

    public const LEFT_MARGIN_MILLIMETRES = 20.0;

    public const RUNNING_HEADER_HEIGHT_MILLIMETRES = 10.0;

    public const FIRST_COLUMN_DIVIDER_WIDTH_MILLIMETRES = 0.35;

    /**
     * @param  array{
     *     title: string,
     *     columns?: array<int, array{
     *         key: string,
     *         label: string,
     *         width?: string,
     *         align?: string,
     *         font_size?: int|float,
     *         cell_padding?: array{top: int|float, right: int|float, bottom: int|float, left: int|float},
     *         header_font_size?: int|float,
     *         header_padding?: array{top: int|float, right: int|float, bottom: int|float, left: int|float},
     *         is_spacer?: bool,
     *         row_span_value?: string,
     *         row_span_rotation?: int|float,
     *         row_span_font_size?: int|float,
     *         cell_background?: string,
     *     }>,
     *     rows?: array<int, array<string, mixed>>,
     *     table_pages?: array<int, array{
     *         columns: array<int, array{
     *             key: string,
     *             label: string,
     *             width?: string,
     *             align?: string,
     *             font_size?: int|float,
     *             cell_padding?: array{top: int|float, right: int|float, bottom: int|float, left: int|float},
     *             header_font_size?: int|float,
     *             header_padding?: array{top: int|float, right: int|float, bottom: int|float, left: int|float},
     *             is_spacer?: bool,
     *             row_span_value?: string,
     *             row_span_rotation?: int|float,
     *             row_span_font_size?: int|float,
     *             cell_background?: string,
     *         }>,
     *         rows: array<int, array<string, mixed>>,
     *         empty_message?: string,
     *     }>,
     *     eyebrow?: string,
     *     subtitle?: string,
     *     user_name: string,
     *     print_date_time: string,
     *     orientation?: 'portrait'|'landscape',
     *     title_page_only?: bool,
     *     metadata?: array<string, string|int|float>,
     *     note?: string,
     *     empty_message?: string,
     *     title_page?: array{
     *         label?: string,
     *         title?: string,
     *         subtitle?: string,
     *         user_name?: string,
     *         date_time?: string,
     *         comments?: string,
     *     },
     * }  $document
     */
    public function generate(array $document): PdfBuilder
    {
        $driverConfig = config('laravel-pdf.dompdf', []);
        $driverConfig = is_array($driverConfig) ? $driverConfig : [];
        $orientation = ($document['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
        $document['orientation'] = $orientation;
        $document['layout'] = [
            'top_margin_millimetres' => self::TOP_MARGIN_MILLIMETRES,
            'right_margin_millimetres' => self::RIGHT_MARGIN_MILLIMETRES,
            'bottom_margin_millimetres' => self::BOTTOM_MARGIN_MILLIMETRES,
            'left_margin_millimetres' => self::LEFT_MARGIN_MILLIMETRES,
            'running_header_height_millimetres' => self::RUNNING_HEADER_HEIGHT_MILLIMETRES,
            'first_column_divider_width_millimetres' => self::FIRST_COLUMN_DIVIDER_WIDTH_MILLIMETRES,
        ];
        $document['table_pages'] = $this->normalizeTablePages($document, $orientation);
        $document['columns'] = $document['table_pages'][0]['columns'];
        $document['rows'] = $document['table_pages'][0]['rows'];
        $document['column_layout_widths'] = $document['table_pages'][0]['column_layout_widths'];

        $pdf = Pdf::view('pdfs.pdfTableGenerator', [
            'document' => $document,
        ])
            ->setDriver(new PdfTableDompdfDriver(
                header: [
                    'title' => $document['title'],
                    'subtitle' => $document['subtitle'] ?? '',
                    'user_name' => $document['user_name'],
                    'print_date_time' => $document['print_date_time'],
                    'has_title_page' => isset($document['title_page']),
                ],
                config: $driverConfig,
            ))
            ->format(Format::A4)
            ->margins(
                top: self::TOP_MARGIN_MILLIMETRES,
                right: self::RIGHT_MARGIN_MILLIMETRES,
                bottom: self::BOTTOM_MARGIN_MILLIMETRES,
                left: self::LEFT_MARGIN_MILLIMETRES,
                unit: 'mm',
            );

        return $orientation === 'landscape'
            ? $pdf->landscape()
            : $pdf->portrait();
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<int, array{
     *     columns: array<int, array<string, mixed>>,
     *     rows: array<int, array<string, mixed>>,
     *     column_layout_widths: array<int, string|null>,
     *     table_layout_width: string|null,
     *     empty_message?: string,
     * }>
     */
    private function normalizeTablePages(array $document, string $orientation): array
    {
        $tablePages = $document['table_pages'] ?? [];

        if (! is_array($tablePages) || $tablePages === []) {
            $tablePages = [[
                'columns' => $document['columns'] ?? [],
                'rows' => $document['rows'] ?? [],
            ]];
        }

        return array_map(function (array $tablePage) use ($orientation): array {
            $columns = $this->normalizeColumns($tablePage['columns']);

            return [
                ...$tablePage,
                'columns' => $columns,
                'rows' => $tablePage['rows'] ?? [],
                'column_layout_widths' => $this->resolveColumnLayoutWidths($columns, $orientation),
                'table_layout_width' => $this->resolveTableLayoutWidth($columns),
            ];
        }, $tablePages);
    }

    /**
     * @param  array<int, array{
     *     key: string,
     *     label: string,
     *     width?: string,
     *     align?: string,
     *     font_size?: int|float,
     *     cell_padding?: array{top: int|float, right: int|float, bottom: int|float, left: int|float},
     *     header_font_size?: int|float,
     *     header_padding?: array{top: int|float, right: int|float, bottom: int|float, left: int|float},
     * }>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function normalizeColumns(array $columns): array
    {
        return array_map(function (array $column): array {
            foreach (['font_size', 'header_font_size', 'row_span_font_size'] as $fontSizeKey) {
                if (isset($column[$fontSizeKey]) && is_numeric($column[$fontSizeKey]) && (float) $column[$fontSizeKey] > 0) {
                    $column[$fontSizeKey] = (float) $column[$fontSizeKey];

                    continue;
                }

                unset($column[$fontSizeKey]);
            }

            $paddingSides = ['top', 'right', 'bottom', 'left'];

            foreach (['cell_padding', 'header_padding'] as $paddingKey) {
                $padding = $column[$paddingKey] ?? null;

                if (
                    is_array($padding)
                    && collect($paddingSides)->every(
                        fn (string $side): bool => isset($padding[$side])
                            && is_numeric($padding[$side])
                            && (float) $padding[$side] >= 0,
                    )
                ) {
                    $column[$paddingKey] = collect($paddingSides)
                        ->mapWithKeys(fn (string $side): array => [$side => (float) $padding[$side]])
                        ->all();

                    continue;
                }

                unset($column[$paddingKey]);
            }

            return $column;
        }, $columns);
    }

    /**
     * DOMPDF distributes absolute widths evenly in fixed-width tables. When all
     * millimetre widths fill the printable area, equivalent percentages preserve
     * the requested physical widths in the rendered PDF.
     *
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, string|null>
     */
    private function resolveColumnLayoutWidths(array $columns, string $orientation): array
    {
        $declaredWidths = array_map(
            fn (array $column): ?string => $column['width'] ?? null,
            $columns,
        );
        $millimetreWidths = [];

        foreach ($declaredWidths as $width) {
            if ($width === null || preg_match('/^(\d+(?:\.\d+)?)mm$/', $width, $matches) !== 1) {
                return $declaredWidths;
            }

            $millimetreWidths[] = (float) $matches[1];
        }

        $pageWidth = $orientation === 'landscape' ? 297.0 : 210.0;
        $printableWidth = $pageWidth - self::LEFT_MARGIN_MILLIMETRES - self::RIGHT_MARGIN_MILLIMETRES;
        $declaredWidth = array_sum($millimetreWidths);

        if (abs($declaredWidth - $printableWidth) > 0.01) {
            return $declaredWidths;
        }

        if (count($millimetreWidths) > 1) {
            $collapsedDividerAdjustment = self::FIRST_COLUMN_DIVIDER_WIDTH_MILLIMETRES / 2;
            $millimetreWidths[0] -= $collapsedDividerAdjustment;
            $millimetreWidths[1] += $collapsedDividerAdjustment;
        }

        return array_map(
            fn (float $width): string => number_format($width / $printableWidth * 100, 8, '.', '').'%',
            $millimetreWidths,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    private function resolveTableLayoutWidth(array $columns): ?string
    {
        $millimetreWidths = [];

        foreach ($columns as $column) {
            $width = $column['width'] ?? null;

            if (! is_string($width) || preg_match('/^(\d+(?:\.\d+)?)mm$/', $width, $matches) !== 1) {
                return null;
            }

            $millimetreWidths[] = (float) $matches[1];
        }

        return number_format(array_sum($millimetreWidths), 4, '.', '').'mm';
    }
}
