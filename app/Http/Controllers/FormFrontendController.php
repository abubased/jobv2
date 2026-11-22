<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Forms\FormDefinition;
use App\Modules\Forms\FormRegistry;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class FormFrontendController extends Controller
{
    public function __construct(private readonly FormRegistry $forms) {}

    public function show(string $slug): RedirectResponse|Response|BinaryFileResponse
    {
        $form = $this->forms->find($slug);
        abort_unless($form instanceof FormDefinition, 404);

        $frontend = $form->config()['frontend'] ?? null;

        if (is_string($frontend) && $frontend !== '') {
            return $this->redirectToFrontend($frontend);
        }

        if (! is_array($frontend)) {
            abort(404);
        }

        $type = (string) ($frontend['type'] ?? '');

        if ($type === 'external') {
            return $this->redirectToFrontend((string) ($frontend['url'] ?? ''));
        }

        if ($type !== 'local') {
            abort(404);
        }

        $file = $this->resolveLocalEntry($frontend);

        if ($file === null) {
            abort(404);
        }

        return $this->serveFile($file);
    }

    public function asset(string $slug, ?string $path = null): Response|BinaryFileResponse
    {
        $form = $this->forms->find($slug);
        abort_unless($form instanceof FormDefinition, 404);

        $frontend = $form->config()['frontend'] ?? null;

        if (! is_array($frontend) || ($frontend['type'] ?? null) !== 'local') {
            abort(404);
        }

        $file = $path === null
            ? $this->resolveLocalEntry($frontend)
            : $this->resolveLocalAsset($frontend, $path);

        if ($file === null) {
            abort(404);
        }

        return $this->serveFile($file);
    }

    private function redirectToFrontend(string $url): RedirectResponse
    {
        $url = trim($url);

        if ($url === '') {
            abort(404);
        }

        if (str_starts_with($url, '/')) {
            if (str_starts_with($url, '//')) {
                abort(403);
            }

            return redirect($url);
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            abort(403);
        }

        if (app()->environment('production') && strtolower((string) $parts['scheme']) !== 'https') {
            abort(403);
        }

        return redirect()->away($url);
    }

    private function resolveLocalEntry(array $frontend): ?string
    {
        $directory = (string) ($frontend['directory'] ?? '');
        $entry = (string) ($frontend['entry'] ?? 'index.html');

        if (! $this->safeRelativePath($directory) || ! $this->safeRelativePath($entry)) {
            abort(403);
        }

        $root = $this->approvedFrontendRoot();
        $directoryReal = realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory));

        if ($directoryReal === false || ! $this->isWithin($root, $directoryReal)) {
            abort(403);
        }

        $entryReal = realpath($directoryReal.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $entry));

        if ($entryReal === false || ! $this->isWithin($directoryReal, $entryReal)) {
            return null;
        }

        if (! $this->allowedAssetExtension($entryReal, true)) {
            abort(403);
        }

        return $entryReal;
    }

    private function resolveLocalAsset(array $frontend, string $path): ?string
    {
        if (! $this->safeRelativePath($path)) {
            abort(403);
        }

        $directory = (string) ($frontend['directory'] ?? '');

        if (! $this->safeRelativePath($directory)) {
            abort(403);
        }

        $root = $this->approvedFrontendRoot();
        $directoryReal = realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory));

        if ($directoryReal === false || ! $this->isWithin($root, $directoryReal)) {
            abort(403);
        }

        $assetReal = realpath($directoryReal.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));

        if ($assetReal === false) {
            return null;
        }

        if (! $this->isWithin($directoryReal, $assetReal)) {
            abort(403);
        }

        if (! $this->allowedAssetExtension($assetReal, false)) {
            abort(403);
        }

        return is_file($assetReal) ? $assetReal : null;
    }

    private function approvedFrontendRoot(): string
    {
        $root = (string) config('forms.frontend_root', public_path());
        $real = realpath($root);

        if ($real === false) {
            abort(404);
        }

        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private function safeRelativePath(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        return $path !== ''
            && ! str_contains($path, "\0")
            && ! str_starts_with($path, '/')
            && ! str_starts_with($path, '../')
            && ! str_starts_with($path, './')
            && ! str_contains($path, '/../')
            && ! str_ends_with($path, '/..')
            && ! preg_match('/^[a-zA-Z]:/', $path);
    }

    private function isWithin(string $root, string $target): bool
    {
        $root = rtrim(realpath($root) ?: $root, DIRECTORY_SEPARATOR);
        $target = realpath($target) ?: $target;

        return $target === $root || str_starts_with($target, $root.DIRECTORY_SEPARATOR);
    }

    private function allowedAssetExtension(string $file, bool $entry): bool
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if ($entry) {
            return in_array($extension, ['html', 'htm'], true);
        }

        return in_array($extension, [
            'html', 'htm', 'css', 'js', 'mjs', 'json', 'map',
            'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'jfif', 'ico',
            'woff', 'woff2', 'ttf', 'eot', 'txt', 'xml', 'pdf',
        ], true);
    }

    private function serveFile(string $file): BinaryFileResponse
    {
        $response = response()->file($file);

        $mimeTypes = [
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'mjs' => 'application/javascript; charset=UTF-8',
            'html' => 'text/html; charset=UTF-8',
            'htm' => 'text/html; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'jfif' => 'image/jpeg',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'pdf' => 'application/pdf',
        ];

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $response->headers->set('Content-Type', $mimeTypes[$extension] ?? 'application/octet-stream');

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'public, max-age=3600');

        return $response;
    }
}
