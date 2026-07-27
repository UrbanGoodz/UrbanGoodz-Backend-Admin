<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use App\Services\Translations\RuntimeTranslationRepository;

if (!function_exists('translate')) {
    function translate($key): string
    {
        $local = getDefaultLanguage();
        App::setLocale($local);

        $key = removeSpecialCharacters($key);
        $processed_key = ucfirst(str_replace('_', ' ', $key));
        $translation_key = 'messages.'.$key;

        try {
            $translator = app('translator');
            if (! $translator->hasForLocale($translation_key, $local)) {
                app(RuntimeTranslationRepository::class)->put($local, 'messages', $key, $processed_key);
                $translator->addLines([$translation_key => $processed_key], $local);
            }

            $result = $translator->get($translation_key, [], $local, false);
        } catch (\Throwable $exception) {
            report($exception);
            $result = $processed_key;
        }

        return $result;
    }
}


if (!function_exists('removeSpecialCharacters')) {

    function removeSpecialCharacters(string $text): string
    {
        return str_ireplace(['\'', '"', ',', ';', '<', '>', '?'], ' ', preg_replace('/\s\s+/', ' ', $text));
    }
}

if (!function_exists('getDefaultLanguage')) {
    function getDefaultLanguage(): string
    {
        if (strpos(url()->current(), '/api')) {
            $lang = App::getLocale();
        } elseif (session()->has('local')) {
            $lang = session('local');
        } else {
            $data = getWebConfig('language');
            $code = 'en';
            $direction = 'ltr';
            foreach ($data as $ln) {
                if (array_key_exists('default', $ln) && $ln['default']) {
                    $code = $ln['code'];
                    if (array_key_exists('direction', $ln)) {
                        $direction = $ln['direction'];
                    }
                }
            }
            session()->put('local', $code);
            Session::put('direction', $direction);
            $lang = $code;
        }
        return $lang;
    }
}
