<?php

namespace App\Services\Translations;

use Illuminate\Contracts\Translation\Loader;

class RuntimeTranslationLoader implements Loader
{
    public function __construct(
        private readonly Loader $loader,
        private readonly RuntimeTranslationRepository $translations,
    ) {
    }

    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->loader->load($locale, $group, $namespace);

        if ((is_null($namespace) || $namespace === '*')
            && in_array($group, (array) config('runtime_translations.groups', []), true)) {
            return $this->translations->merge($lines, $locale, $group);
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint)
    {
        $this->loader->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->loader->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->loader->namespaces();
    }
}
