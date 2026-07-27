<?php

namespace App\Services\Translations;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use InvalidArgumentException;
use RuntimeException;

class RuntimeTranslationRepository
{
    public function __construct(private readonly FilesystemFactory $filesystems)
    {
    }

    public function all(string $locale, string $group = 'messages'): array
    {
        [$disk, $path] = $this->location($locale, $group);

        if (! $disk->exists($path)) {
            return [];
        }

        $decoded = json_decode((string) $disk->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function merge(array $source, string $locale, string $group = 'messages'): array
    {
        foreach ($this->all($locale, $group) as $key => $value) {
            if ($value === null) {
                unset($source[$key]);
            } elseif (is_string($value)) {
                $source[$key] = $value;
            }
        }

        return $source;
    }

    public function put(string $locale, string $group, string $key, string $value): void
    {
        $this->mutate($locale, $group, function (array $catalog) use ($key, $value) {
            $catalog[$key] = $value;

            return $catalog;
        });
    }

    public function remove(string $locale, string $group, string $key): void
    {
        $this->mutate($locale, $group, function (array $catalog) use ($key) {
            $catalog[$key] = null;

            return $catalog;
        });
    }

    public function forget(string $locale, string $group, string $key): void
    {
        $this->mutate($locale, $group, function (array $catalog) use ($key) {
            unset($catalog[$key]);

            return $catalog;
        });
    }

    public function import(string $locale, string $group, array $translations, bool $replace = false): int
    {
        $normalized = [];
        foreach ($translations as $key => $value) {
            if (! is_string($key) || (! is_string($value) && $value !== null)) {
                throw new InvalidArgumentException('Runtime translations must contain string keys and string or null values.');
            }
            $normalized[$key] = $value;
        }

        $this->mutate($locale, $group, fn (array $catalog) => $replace
            ? $normalized
            : array_replace($catalog, $normalized));

        return count($normalized);
    }

    public function renameLocale(string $oldLocale, string $newLocale): void
    {
        foreach ($this->allowedGroups() as $group) {
            $existing = $this->all($oldLocale, $group);
            if ($existing !== []) {
                $this->import($newLocale, $group, $existing);
            }
        }

        $this->deleteLocale($oldLocale);
    }

    public function deleteLocale(string $locale): void
    {
        foreach ($this->allowedGroups() as $group) {
            [$disk, $path] = $this->location($locale, $group);
            $disk->delete([$path, $path.'.lock']);
        }
    }

    private function mutate(string $locale, string $group, callable $callback): void
    {
        [$disk, $path] = $this->location($locale, $group);
        $disk->makeDirectory(dirname($path));

        $lockPath = $disk->path($path.'.lock');
        $lock = fopen($lockPath, 'c+');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the runtime translation catalog.');
        }

        try {
            $catalog = $disk->exists($path)
                ? json_decode((string) $disk->get($path), true)
                : [];
            $catalog = is_array($catalog) ? $catalog : [];
            $catalog = $callback($catalog);
            ksort($catalog);

            $payload = json_encode(
                $catalog,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ).PHP_EOL;

            $temporaryPath = $disk->path($path.'.tmp.'.bin2hex(random_bytes(6)));
            file_put_contents($temporaryPath, $payload, LOCK_EX);
            if (PHP_OS_FAMILY === 'Windows' && is_file($disk->path($path))) {
                unlink($disk->path($path));
            }
            if (! rename($temporaryPath, $disk->path($path))) {
                @unlink($temporaryPath);
                throw new RuntimeException('Unable to replace the runtime translation catalog.');
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function location(string $locale, string $group): array
    {
        if (! preg_match('/^[A-Za-z0-9_-]{2,20}$/', $locale)) {
            throw new InvalidArgumentException('Invalid translation locale.');
        }

        if (! in_array($group, $this->allowedGroups(), true)) {
            throw new InvalidArgumentException('Invalid translation group.');
        }

        /** @var FilesystemAdapter $disk */
        $disk = $this->filesystems->disk((string) config('runtime_translations.disk', 'local'));
        $directory = trim((string) config('runtime_translations.directory', 'runtime-translations'), '/');

        return [$disk, "{$directory}/{$locale}/{$group}.json"];
    }

    private function allowedGroups(): array
    {
        return (array) config('runtime_translations.groups', ['messages', 'lang']);
    }
}
