<?php

namespace App\Modules\Payment\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Streams the module's own CSS/JS/image files straight from its resources
 * directory. Avoids requiring a vendor:publish step so copying the module
 * folder into a project is enough for assets to work immediately.
 */
class AssetController extends Controller
{
    private const BASE_DIR = __DIR__.'/../../resources';

    private const MIME_TYPES = [
        'css' => 'text/css',
        'js' => 'text/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'ttf' => 'font/ttf',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    public function show(string $path): Response
    {
        $base = realpath(self::BASE_DIR);
        $file = realpath(self::BASE_DIR.'/'.$path);

        if ($base === false || $file === false || $file === $base) {
            abort(404);
        }

        if (! str_starts_with($file, $base.DIRECTORY_SEPARATOR) || ! is_file($file)) {
            abort(404);
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        abort_unless(isset(self::MIME_TYPES[$extension]), 404);

        return response(file_get_contents($file), 200, [
            'Content-Type' => self::MIME_TYPES[$extension],
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
