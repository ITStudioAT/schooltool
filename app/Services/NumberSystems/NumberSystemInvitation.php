<?php

namespace App\Services\NumberSystems;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class NumberSystemInvitation
{
    /** @return array{url: string, qr_data_url: string, local_only: bool} */
    public function make(string $courseCode, ?string $teamCode = null): array
    {
        $parameters = ['code' => $courseCode];
        if ($teamCode !== null) {
            $parameters['team_code'] = $teamCode;
        }
        $url = route('number-systems.entry', $parameters);

        return $this->forUrl($url);
    }

    /** @return array{url: string, qr_data_url: string, local_only: bool} */
    public function forUrl(string $url): array
    {
        $url = preg_replace('~^(https?://)%5B([a-f0-9:]+)%5D(?=[:/])~i', '$1[$2]', $url) ?? $url;
        $writer = new Writer(new ImageRenderer(new RendererStyle(512, 4), new SvgImageBackEnd));
        $svg = $writer->writeString($url, 'UTF-8', ErrorCorrectionLevel::M());
        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));

        return ['url' => $url, 'qr_data_url' => 'data:image/svg+xml;base64,'.base64_encode($svg),
            'local_only' => $host === 'localhost' || str_ends_with($host, '.localhost') || str_starts_with($host, '127.') || in_array($host, ['::1', '0.0.0.0'], true)];
    }
}
