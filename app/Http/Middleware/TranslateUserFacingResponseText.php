<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TranslateUserFacingResponseText
{
    private const TRANSLATABLE_KEYS = [
        'message',
        'title',
        'subtitle',
        'description',
        'body',
        'text',
        'label',
        'reason',
        'error',
        'success',
        'status',
        'hint',
        'empty',
    ];

    /** @var array<string, array<string, string>> */
    private array $catalogs = [];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $locale = app()->getLocale();

        if ($locale === 'de') {
            return $response;
        }

        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            $response->setData($this->translateUserText($data));
        }

        if (method_exists($response, 'getSession')) {
            $session = $response->getSession();
            if ($session) {
                foreach (['status', 'message', 'success', 'error', 'warning'] as $flashKey) {
                    $raw = $session->get($flashKey);
                    if (is_string($raw) && $raw !== '') {
                        $session->flash($flashKey, $this->translateString($raw, $locale));
                    }
                }
            }
        }

        return $response;
    }

    /**
     * @param mixed $value
     * @param mixed $key
     * @return mixed
     */
    private function translateUserText(mixed $value, mixed $key = null): mixed
    {
        if (is_array($value)) {
            foreach ($value as $itemKey => $item) {
                if (is_string($itemKey)) {
                    $value[$itemKey] = $this->translateUserText($item, $itemKey);
                    continue;
                }
                $value[$itemKey] = $this->translateUserText($item);
            }

            return $value;
        }

        if (! is_string($value) || ! is_string($key) || ! in_array($key, self::TRANSLATABLE_KEYS, true)) {
            return $value;
        }

        $locale = app()->getLocale();
        return $this->translateString($value, $locale);
    }

    private function translateString(string $value, string $locale): string
    {
        if ($locale === 'de') {
            return $value;
        }

        $catalog = $this->loadCatalog($locale);
        if (array_key_exists($value, $catalog)) {
            return $catalog[$value];
        }

        $translated = __($value);
        if ($translated !== $value && $translated !== '' && ! str_starts_with($translated, '[')) {
            return $translated;
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private function loadCatalog(string $locale): array
    {
        if (! array_key_exists($locale, $this->catalogs)) {
            $path = resource_path("js/lang/{$locale}.json");
            if (! is_file($path)) {
                $this->catalogs[$locale] = [];
            } else {
                $raw = (string) file_get_contents($path);
                $decoded = json_decode($raw, true);
                $auto = is_array($decoded['auto'] ?? null) ? $decoded['auto'] : [];
                $this->catalogs[$locale] = array_filter(
                    is_array($auto) ? $auto : [],
                    fn (mixed $candidate) => is_string($candidate),
                );
            }
        }

        return $this->catalogs[$locale];
    }
}
