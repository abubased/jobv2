<?php

declare(strict_types=1);

namespace App\Modules\Forms;

use App\Modules\Forms\Exceptions\InvalidFormConfigurationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FormRegistry
{
    /** @var array<string, FormDefinition>|null */
    private ?array $resolved = null;

    public function __construct(
        private readonly FormConfigurationValidator $validator,
        private readonly ?string $path = null,
    ) {}

    /**
     * Enabled forms, keyed by slug.
     *
     * @return array<string, FormDefinition>
     */
    public function all(): array
    {
        return $this->definitions();
    }

    public function find(string $slug): ?FormDefinition
    {
        return $this->definitions()[$slug] ?? null;
    }

    /**
     * Rebuild the cache from the configurations directory.
     *
     * @return array<string, FormDefinition>
     */
    public function refresh(): array
    {
        $this->forget();
        $definitions = $this->scan();
        Cache::forever($this->cacheKey(), $this->serialize($definitions));
        $this->resolved = $definitions;

        return $definitions;
    }

    public function clear(): void
    {
        $this->forget();
    }

    /**
     * @return array<string, FormDefinition>
     */
    private function definitions(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        if ($this->usesCache() && Cache::has($this->cacheKey())) {
            $cached = Cache::get($this->cacheKey());

            if (is_array($cached)) {
                return $this->resolved = $this->hydrate($cached);
            }
        }

        $definitions = $this->scan();

        if ($this->usesCache()) {
            Cache::forever($this->cacheKey(), $this->serialize($definitions));
        }

        return $this->resolved = $definitions;
    }

    /**
     * @return array<string, FormDefinition>
     */
    private function scan(): array
    {
        $definitions = [];
        $directory = $this->directory();

        if (! is_dir($directory)) {
            return [];
        }

        $files = glob($directory.DIRECTORY_SEPARATOR.'*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $config = $this->loadFile($file);

            if ($config === null) {
                continue;
            }

            if (! array_key_exists('slug', $config) || ! is_string($config['slug']) || $config['slug'] === '') {
                $config['slug'] = Str::kebab(pathinfo($file, PATHINFO_FILENAME));
            }

            try {
                $this->validator->validate($file, $config);
            } catch (InvalidFormConfigurationException $exception) {
                $this->reportInvalid($exception);

                continue;
            }

            if (($config['enabled'] ?? false) !== true) {
                continue;
            }

            $slug = (string) $config['slug'];

            if (isset($definitions[$slug])) {
                $this->reportInvalid(new InvalidFormConfigurationException($file, [
                    "Duplicate form slug [{$slug}].",
                ]));

                continue;
            }

            $definitions[$slug] = new FormDefinition($config);
        }

        return $definitions;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadFile(string $file): ?array
    {
        try {
            $config = require $file;
        } catch (Throwable $exception) {
            $this->reportInvalid(new InvalidFormConfigurationException($file, [
                'Could not load configuration: '.$exception->getMessage(),
            ]));

            return null;
        }

        if (! is_array($config)) {
            $this->reportInvalid(new InvalidFormConfigurationException($file, [
                'Configuration file must return an array.',
            ]));

            return null;
        }

        return $config;
    }

    private function reportInvalid(InvalidFormConfigurationException $exception): void
    {
        $strict = (bool) config('app.debug') || app()->environment('local', 'testing');

        if ($strict) {
            throw $exception;
        }

        Log::error($exception->getMessage());
    }

    private function directory(): string
    {
        return $this->path ?? app_path('Modules/Forms/Configurations');
    }

    private function usesCache(): bool
    {
        return (bool) config('forms.cache');
    }

    private function cacheKey(): string
    {
        return (string) config('forms.cache_key', 'forms.registry');
    }

    private function forget(): void
    {
        Cache::forget($this->cacheKey());
        $this->resolved = null;
    }

    /**
     * @param  array<string, FormDefinition>  $definitions
     * @return array<string, array<string, mixed>>
     */
    private function serialize(array $definitions): array
    {
        $payload = [];

        foreach ($definitions as $slug => $definition) {
            $payload[$slug] = $definition->config();
        }

        return $payload;
    }

    /**
     * @param  array<string, array<string, mixed>>  $payload
     * @return array<string, FormDefinition>
     */
    private function hydrate(array $payload): array
    {
        $definitions = [];

        foreach ($payload as $slug => $config) {
            if (is_array($config)) {
                $definitions[(string) $slug] = new FormDefinition($config);
            }
        }

        return $definitions;
    }
}
