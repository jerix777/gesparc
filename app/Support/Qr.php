<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class Qr
{
    /** Code QR en SVG (sans en-tête XML), à insérer dans une page imprimable. */
    public static function svg(string $text, int $size = 120): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        return preg_replace('/^<\?xml[^>]*>\s*/', '', $writer->writeString($text));
    }
}
