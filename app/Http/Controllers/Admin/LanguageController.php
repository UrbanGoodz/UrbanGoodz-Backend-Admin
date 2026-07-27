<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\Translations\RuntimeTranslationRepository;

class LanguageController extends Controller
{
    public function __construct(private readonly RuntimeTranslationRepository $runtimeTranslations)
    {
    }

    public function index()
    {       $language = BusinessSetting::where('key', 'system_language')->exists();
        if(!$language){
            Helpers::insert_business_settings_key('system_language','[{"id":1,"direction":"ltr","code":"en","status":1,"default":true}]');
        }
        return view('admin-views.business-settings.language.index');
    }

    public function store(Request $request)
    {
        $language = BusinessSetting::where('key', 'system_language')->first();
        $existingLanguages = json_decode($language?->value, true) ?? [];

        foreach ($existingLanguages as $data) {
            if ($data['code'] === $request->code) {
                Toastr::error('Language already exists!');
                return back();
            }
        }
        $lang_array = [];
        $codes = [];
        foreach (json_decode($language?->value, true) as $key => $data) {
            if ($data['code'] != $request['code']) {
                if (!array_key_exists('default', $data)) {
                    $default = array('default' => ($data['code'] == 'en') ? true : false);
                    $data = array_merge($data, $default);
                }
                array_push($lang_array, $data);
                array_push($codes, $data['code']);
            }
        }
        array_push($codes, $request['code']);

        $lang_array[] = [
            'id' => $request['code'].count(json_decode($language['value'], true)) + 1,
            'code' => $request['code'],
            'direction' => $request['direction'],
            'status' => 0,
            'default' => false,
        ];
        Helpers::businessUpdateOrInsert(['key' => 'system_language'], [
            'value' => $lang_array
        ]);

        Helpers::businessUpdateOrInsert(['key' => 'language'], [
            'value' => json_encode($codes),
        ]);

        Toastr::success('Language Added!');
        return back();
    }

    public function update_status(Request $request)
    {
        $language = BusinessSetting::where('key', 'system_language')->first();
        $lang_array = [];
        foreach (json_decode($language?->value, true) as $key => $data) {

            if ($data['code'] == $request['code']) {
                if( array_key_exists('default', $data) && $data['default'] == true ){
                    return response()->json(['error' => 403]);
                }
                $lang = [
                    'id' => $data['id'],
                    'direction' => $data['direction'] ?? 'ltr',
                    'code' => $data['code'],
                    'status' => $data['status'] == 1 ? 0 : 1,
                    'default' => (array_key_exists('default', $data) ? $data['default'] : (($data['code'] == 'en') ? true : false)),
                ];
                $lang_array[] = $lang;
            } else {
                $lang = [
                    'id' => $data['id'],
                    'direction' => $data['direction'] ?? 'ltr',
                    'code' => $data['code'],
                    'status' => $data['status'],
                    'default' => (array_key_exists('default', $data) ? $data['default'] : (($data['code'] == 'en') ? true : false)),
                ];
                $lang_array[] = $lang;
            }
        }
        $businessSetting = Helpers::businessUpdateOrInsert(['key' => 'system_language'], [
            'value' => $lang_array
        ]);
        return $businessSetting;
    }

    public function update_default_status(Request $request)
    {
        $language = BusinessSetting::where('key', 'system_language')->first();
        // dd($language);


        $lang_array = [];
        foreach (json_decode($language?->value, true) as $key => $data) {
            if ($data['code'] == $request['code']) {

               if($data['default'] == true){
                Toastr::warning(translate('messages.You_can_not_change_the_default_status_of_this_language'));
                return back();
               }

                $lang = [
                    'id' => $data['id'],
                    'direction' => $data['direction'] ?? 'ltr',
                    'code' => $data['code'],
                    'status' => 1,
                    'default' => true,
                ];
                $lang_array[] = $lang;
            } else {
                $lang = [
                    'id' => $data['id'],
                    'direction' => $data['direction'] ?? 'ltr',
                    'code' => $data['code'],
                    'status' => $data['status'],
                    'default' => false,
                ];
                $lang_array[] = $lang;
            }
        }

        Helpers::businessUpdateOrInsert(['key' => 'system_language'], [
            'value' => $lang_array
        ]);

        $direction = BusinessSetting::where('key', 'site_direction')->first();
        $direction = $direction->value ?? 'ltr';
        $language = BusinessSetting::where('key', 'system_language')->first();
        foreach (json_decode($language?->value, true) as $key => $data) {
            if ($data['code'] == $request['code']) {
                $direction = isset($data['direction']) ? $data['direction'] : 'ltr';
            }
        }
        session()->forget('language_settings');
        Helpers::language_load();
        session()->put('local', $request['code']);
        session()->put('site_direction', $direction);
        Toastr::success('Default Language Changed!');
        return back();
    }

    public function update(Request $request)
    {
        $language = BusinessSetting::where('key', 'system_language')->first();
        $lang_array = [];
        foreach (json_decode($language?->value, true) as $key => $data) {
            if ($data['code'] == $request['old_code']) {
                $lang = [
                    'id' => $data['id'],
                    'direction' => $request['direction'] ?? 'ltr',
                    'code' => $request['code'],
                    'status' => $data['status'],
                    'default' => (array_key_exists('default', $data) ? $data['default'] : (($data['code'] == 'en') ? true : false)),
                ];
                $lang_array[] = $lang;
            } else {
                $lang = [
                    'id' => $data['id'],
                    'direction' => $data['direction'] ?? 'ltr',
                    'code' => $data['code'],
                    'status' => $data['status'],
                    'default' => (array_key_exists('default', $data) ? $data['default'] : (($data['code'] == 'en') ? true : false)),
                ];
                $lang_array[] = $lang;
            }
        }

        Helpers::businessUpdateOrInsert(['key' => 'system_language'], [
            'value' => $lang_array
        ]);

        if($request->code != $request->old_code){
            $this->runtimeTranslations->renameLocale($request['old_code'], $request['code']);
            $codes = [];
            foreach ($lang_array as $key => $data) {
                array_push($codes, $data['code']);
            }
            Helpers::businessUpdateOrInsert(['key' => 'language'], [
                'value' => json_encode($codes),
            ]);
        }

    Toastr::success('Language updated!');
    return back();
    }

    public function convertArrayToCollection($lang, $items, $perPage = null, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        $options = [
        "path" => route('admin.business-settings.language.translate',[$lang]),
        "pageName" => "page"
        ];
        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }

    public function translate(Request $request,$lang)
    {
        $searchTerm =$request['search'];
        $full_data = $this->messageCatalog($lang);
        $full_data = array_filter($full_data, fn($value) => !is_null($value) && $value !== '');

        if (!empty($searchTerm)) {
            $full_data = array_filter($full_data, function ($value, $key) use ($searchTerm) {
                return (stripos($value, $searchTerm) !== false) || (stripos(ucfirst(str_replace('_', ' ', Helpers::remove_invalid_charcaters($key))), $searchTerm) !== false);
            }, ARRAY_FILTER_USE_BOTH);
        }


        ksort($full_data);
        $full_data = $this->convertArrayToCollection($lang,$full_data,config('default_pagination'));

        return view('admin-views.business-settings.language.translate', compact('lang', 'full_data'));
    }

    public function translate_key_remove(Request $request, $lang)
    {
        $this->runtimeTranslations->remove($lang, 'messages', (string) $request['key']);
    }

    public function translate_submit(Request $request, $lang)
    {
        $request->validate(['key' => 'required|string|max:191', 'value' => 'required|string|max:10000']);
        $this->runtimeTranslations->put($lang, 'messages', $request['key'], $request['value']);
    }

    public function auto_translate(Request $request, $lang): \Illuminate\Http\JsonResponse
    {
        $lang_code = Helpers::getLanguageCode($lang);

        $translated=  str_replace('_', ' ', Helpers::remove_invalid_charcaters($request['key']));
        $translated = Helpers::auto_translator($translated, 'en', $lang);
        $this->runtimeTranslations->put($lang, 'messages', $request['key'], $translated);

        return response()->json([
            'translated_data' => $translated
        ]);
    }
    public function auto_translate_all(Request $request, $lang): \Illuminate\Http\JsonResponse
    {
        try {
            if($lang === 'en'){
                return response()->json([
                    'message' => translate('All_datas_are_translated') , 'data' => 'success'
                ]);
            }

            $source = $this->messageCatalog('en');
            $overrides = $this->runtimeTranslations->all($lang, 'messages');
            $pending = array_diff_key($source, $overrides);
            $batch = array_slice($pending, 0, 20, true);

            foreach ($batch as $key => $value) {
                $translated = Helpers::auto_translator((string) $value, 'en', $lang);
                $this->runtimeTranslations->put($lang, 'messages', $key, $translated);
            }

            $remaining = max(0, count($pending) - count($batch));
            $total = max(1, count($source));
            $percentage = round((($total - $remaining) / $total) * 100, 1);

            return response()->json($remaining > 0
                ? ['message' => translate('translating'), 'data' => 'translating', 'total' => $total, 'percentage' => $percentage, 'hours' => 0, 'minutes' => 1, 'seconds' => 0, 'status' => 'pending']
                : ['message' => translate('All_datas_are_translated'), 'data' => 'success', 'status' => 'done']);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => $exception->getMessage() , 'data' => 'error'
            ]);
        }
    }

    public function delete($lang)
    {
        $language = BusinessSetting::where('key', 'system_language')->first();

        $del_default = false;
        foreach (json_decode($language?->value, true) as $key => $data) {
            if ($data['code'] == $lang && array_key_exists('default', $data) && $data['default'] == true) {
                $del_default = true;
            }
        }

        $lang_array = [];
        foreach (json_decode($language?->value, true) as $key => $data) {
            if ($data['code'] != $lang) {
                $lang_data = [
                    'id' => $data['id'],
                    'direction' => $data['direction'] ?? 'ltr',
                    'code' => $data['code'],
                    'status' => ($del_default == true && $data['code'] == 'en') ? 1 : $data['status'],
                    'default' => ($del_default == true && $data['code'] == 'en') ? true : (array_key_exists('default', $data) ? $data['default'] : (($data['code'] == 'en') ? true : false)),
                ];
                array_push($lang_array, $lang_data);
            }
        }

        BusinessSetting::where('key', 'system_language')->update([
            'value' => $lang_array
        ]);

        $this->runtimeTranslations->deleteLocale($lang);


        $languages = array();
        $language = BusinessSetting::where('key', 'language')->first();
        foreach (json_decode($language?->value, true) as $key => $data) {
            if ($data != $lang) {
                array_push($languages, $data);
            }
        }
        if (in_array('en', $languages)) {
            unset($languages[array_search('en', $languages)]);
        }
        array_unshift($languages, 'en');

        Helpers::businessUpdateOrInsert(['key' => 'language'], [
            'value' => json_encode($languages),
        ]);

        Toastr::success('Removed Successfully!');
        return back();
    }

    public function lang($local)
    {
        $direction = BusinessSetting::where('key', 'site_direction')->first();
        $direction = $direction->value ?? 'ltr';
        $language = BusinessSetting::where('key', 'system_language')->first();
        foreach (json_decode($language?->value, true) as $key => $data) {
            if ($data['code'] == $local) {
                $direction = isset($data['direction']) ? $data['direction'] : 'ltr';
            }
        }
        session()->forget('language_settings');
        Helpers::language_load();
        session()->put('local', $local);
        session()->put('site_direction', $direction);
        return redirect()->back();
    }

    public function exportRuntime($lang)
    {
        $payload = json_encode(
            $this->runtimeTranslations->all($lang, 'messages'),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        return response()->streamDownload(
            fn () => print($payload.PHP_EOL),
            "urban-goodz-runtime-translations-{$lang}.json",
            ['Content-Type' => 'application/json']
        );
    }

    public function importRuntime(Request $request, $lang)
    {
        $request->validate(['catalog' => 'required|file|max:2048']);
        $decoded = json_decode((string) file_get_contents($request->file('catalog')->getRealPath()), true);
        if (! is_array($decoded)) {
            return back()->withErrors(['catalog' => 'The runtime translation catalog must be a JSON object.']);
        }

        $count = $this->runtimeTranslations->import($lang, 'messages', $decoded);
        Toastr::success("{$count} runtime translations imported.");

        return back();
    }

    private function messageCatalog(string $lang): array
    {
        $path = base_path("resources/lang/{$lang}/messages.php");
        if (! is_file($path)) {
            $path = base_path('resources/lang/en/messages.php');
        }

        $source = include $path;

        return $this->runtimeTranslations->merge(is_array($source) ? $source : [], $lang, 'messages');
    }
}
