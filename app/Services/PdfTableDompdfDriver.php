<?php

namespace App\Services;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Spatie\LaravelPdf\Drivers\DomPdfDriver;
use Spatie\LaravelPdf\PdfOptions;

class PdfTableDompdfDriver extends DomPdfDriver
{
    private const POINTS_PER_MILLIMETRE = 72 / 25.4;

    private const HEADER_DETAILS_OFFSET_MILLIMETRES = 4.0;

    private const HEADER_BORDER_BOTTOM_GAP_MILLIMETRES = 1.0;

    /**
     * @param  array{
     *     title: string,
     *     subtitle: string,
     *     user_name: string,
     *     print_date_time: string,
     *     has_title_page: bool,
     * }  $header
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private array $header,
        array $config = [],
    ) {
        parent::__construct($config);
    }

    public function generatePdf(
        string $html,
        ?string $headerHtml,
        ?string $footerHtml,
        PdfOptions $options,
    ): string {
        $dompdf = $this->buildDompdf($html, $headerHtml, $footerHtml, $options);
        $dompdf->render();

        $this->addRunningHeader($dompdf);

        return $dompdf->output();
    }

    private function addRunningHeader(Dompdf $dompdf): void
    {
        $dompdf->getCanvas()->page_script(function (
            int $pageNumber,
            int $pageCount,
            Canvas $canvas,
            FontMetrics $fontMetrics,
        ): void {
            $firstContentPage = $this->header['has_title_page'] ? 2 : 1;

            if ($pageNumber < $firstContentPage) {
                return;
            }

            $regularFont = $fontMetrics->getFont('DejaVu Sans');

            if ($regularFont === null) {
                return;
            }

            $boldFont = $fontMetrics->getFont('DejaVu Sans', 'bold') ?? $regularFont;

            $this->drawHeader(
                canvas: $canvas,
                fontMetrics: $fontMetrics,
                regularFont: $regularFont,
                boldFont: $boldFont,
                pageNumber: $pageNumber - $firstContentPage + 1,
                pageCount: $pageCount - $firstContentPage + 1,
            );
        });
    }

    private function drawHeader(
        Canvas $canvas,
        FontMetrics $fontMetrics,
        string $regularFont,
        string $boldFont,
        int $pageNumber,
        int $pageCount,
    ): void {
        $left = $this->millimetresToPoints(PdfTableGenerator::LEFT_MARGIN_MILLIMETRES);
        $right = $canvas->get_width() - $this->millimetresToPoints(PdfTableGenerator::RIGHT_MARGIN_MILLIMETRES);
        $titleTopMillimetres = PdfTableGenerator::TOP_MARGIN_MILLIMETRES;
        $detailsTopMillimetres = $titleTopMillimetres + self::HEADER_DETAILS_OFFSET_MILLIMETRES;
        $borderTopMillimetres = $titleTopMillimetres
            + PdfTableGenerator::RUNNING_HEADER_HEIGHT_MILLIMETRES
            - self::HEADER_BORDER_BOTTOM_GAP_MILLIMETRES;
        $titleTop = $this->millimetresToPoints($titleTopMillimetres);
        $detailsTop = $this->millimetresToPoints($detailsTopMillimetres);
        $borderTop = $this->millimetresToPoints($borderTopMillimetres);
        $columnWidth = ($right - $left) * 0.48;
        $titleSize = 8.5;
        $detailSize = 6.5;
        $titleColor = [0.09, 0.23, 0.22];
        $detailColor = [0.38, 0.46, 0.45];

        $title = $this->fitText($this->header['title'], $fontMetrics, $boldFont, $titleSize, $columnWidth);
        $subtitle = $this->fitText($this->header['subtitle'], $fontMetrics, $regularFont, $detailSize, $columnWidth);
        $pageLabel = "Page {$pageNumber} / {$pageCount}";
        $details = implode(' · ', array_filter([
            $this->header['user_name'],
            $this->header['print_date_time'],
        ], fn (string $value): bool => $value !== ''));
        $details = $this->fitText($details, $fontMetrics, $regularFont, $detailSize, $columnWidth);

        $canvas->text($left, $titleTop, $title, $boldFont, $titleSize, $titleColor);
        $canvas->text(
            $right - $fontMetrics->getTextWidth($pageLabel, $boldFont, $titleSize),
            $titleTop,
            $pageLabel,
            $boldFont,
            $titleSize,
            $titleColor,
        );

        if ($subtitle !== '') {
            $canvas->text($left, $detailsTop, $subtitle, $regularFont, $detailSize, $detailColor);
        }

        if ($details !== '') {
            $canvas->text(
                $right - $fontMetrics->getTextWidth($details, $regularFont, $detailSize),
                $detailsTop,
                $details,
                $regularFont,
                $detailSize,
                $detailColor,
            );
        }

        $canvas->line($left, $borderTop, $right, $borderTop, [0.65, 0.78, 0.76], 0.5);
    }

    private function millimetresToPoints(float $millimetres): float
    {
        return $millimetres * self::POINTS_PER_MILLIMETRE;
    }

    private function fitText(
        string $text,
        FontMetrics $fontMetrics,
        string $font,
        float $size,
        float $maximumWidth,
    ): string {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($fontMetrics->getTextWidth($text, $font, $size) <= $maximumWidth) {
            return $text;
        }

        $suffix = '…';
        $length = mb_strlen($text);

        while ($length > 0) {
            $candidate = rtrim(mb_substr($text, 0, --$length)).$suffix;

            if ($fontMetrics->getTextWidth($candidate, $font, $size) <= $maximumWidth) {
                return $candidate;
            }
        }

        return '';
    }
}
