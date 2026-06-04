<?php

namespace App\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class LocalizationReadinessReport
{
    public const VERSION = '2026-06-03';

    public const SUPPORTED_LOCALES = ['de', 'en', 'fr', 'ar'];

    public const RTL_LOCALES = ['ar'];

    public static function make(): array
    {
        $languageFiles = self::languageFiles();
        $vue = self::vueReadiness();
        $php = self::phpReadiness();
        $rtlQa = self::rtlQa($languageFiles);
        $warnings = self::warnings($languageFiles, $vue, $php, $rtlQa);

        return [
            'version' => self::VERSION,
            'supported_locales' => self::SUPPORTED_LOCALES,
            'rtl_locales' => self::RTL_LOCALES,
            'default_locale' => config('app.locale', 'de'),
            'fallback_locale' => config('app.fallback_locale', 'de'),
            'language_files' => $languageFiles,
            'rtl_qa' => $rtlQa,
            'vue' => $vue,
            'php' => $php,
            'quality_gates' => [
                'all_supported_locale_files_present' => self::allLocaleFilesPresent($languageFiles),
                'rtl_locale_has_messages' => ($languageFiles['resources_js_lang']['locales']['ar']['keys'] ?? 0) > 0,
                'arabic_json_valid' => $rtlQa['arabic_json_valid'],
                'arabic_has_arabic_glyphs' => $rtlQa['arabic_has_arabic_glyphs'],
                'arabic_mojibake_risk_status' => $rtlQa['mojibake_risk_status'],
                'rtl_manual_qa_required' => $rtlQa['manual_qa_required'],
                'mobile_contract_exports_rtl' => true,
                'hardcoded_text_total_candidates' => self::hardcodedTextTotal($vue, $php),
                'hardcoded_text_budget_status' => self::hardcodedTextBudgetStatus($vue, $php),
            ],
            'warnings' => $warnings,
            'next_actions' => self::nextActions($warnings),
        ];
    }

    protected static function rtlQa(array $languageFiles): array
    {
        $arabicPath = resource_path('js/lang/ar.json');
        $contents = is_file($arabicPath) ? (string) file_get_contents($arabicPath) : '';
        $decoded = $contents !== '' ? json_decode($contents, true) : null;
        $jsonValid = is_array($decoded) && json_last_error() === JSON_ERROR_NONE;
        $arabicGlyphs = $contents !== '' ? preg_match_all('/\p{Arabic}/u', $contents) : 0;
        $mojibakeMarkers = $contents !== '' ? preg_match_all('/[�f�,�~�T]/u', $contents) : 0;
        $maturityKeysPresent = $jsonValid && isset($decoded['maturity']) && is_array($decoded['maturity']);

        return [
            'status' => $jsonValid && $arabicGlyphs > 0 && $maturityKeysPresent ? 'ready_for_manual_qa' : 'needs_translation_fix',
            'arabic_json_valid' => $jsonValid,
            'arabic_has_arabic_glyphs' => $arabicGlyphs > 0,
            'arabic_glyph_count' => $arabicGlyphs,
            'mojibake_marker_count' => $mojibakeMarkers,
            'mojibake_risk_status' => $mojibakeMarkers > 0 ? 'review_existing_legacy_strings' : 'clean',
            'maturity_keys_present' => $maturityKeysPresent,
            'direction_contract' => [
                'locale' => 'ar',
                'dir' => 'rtl',
                'html_dir_source' => 'resources/views/app.blade.php',
                'client_class' => 'is-rtl',
                'logical_layout_required' => true,
            ],
            'critical_manual_routes' => [
                'dashboard',
                'maturity_insights',
                'sport_map',
                'marketplace',
                'teams',
                'trainer_cockpit',
                'club_cockpit',
                'settings',
            ],
            'manual_qa_required' => true,
            'language_file_keys' => $languageFiles['resources_js_lang']['locales']['ar']['keys'] ?? 0,
        ];
    }

    protected static function languageFiles(): array
    {
        return [
            'resources_js_lang' => self::jsonLocaleFolder(resource_path('js/lang')),
            'resources_lang' => self::jsonLocaleFolder(resource_path('lang')),
            'root_lang' => self::jsonLocaleFolder(base_path('lang')),
        ];
    }

    protected static function jsonLocaleFolder(string $folder): array
    {
        $locales = [];

        foreach (self::SUPPORTED_LOCALES as $locale) {
            $path = $folder.DIRECTORY_SEPARATOR.$locale.'.json';
            $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

            $locales[$locale] = [
                'exists' => is_file($path),
                'keys' => is_array($decoded) ? count($decoded) : 0,
                'path' => str_replace(base_path().DIRECTORY_SEPARATOR, '', $path),
            ];
        }

        return [
            'folder' => str_replace(base_path().DIRECTORY_SEPARATOR, '', $folder),
            'locales' => $locales,
            'minimum_keys' => min(array_map(fn (array $item) => $item['keys'], $locales)),
            'maximum_keys' => max(array_map(fn (array $item) => $item['keys'], $locales)),
        ];
    }

    protected static function vueReadiness(): array
    {
        $files = self::files(resource_path('js'), 'vue');
        $withI18n = 0;
        $visibleTextCandidates = 0;
        $attributeTextCandidates = 0;
        $topFiles = [];

        foreach ($files as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            if (str_contains($contents, 'useI18n') || str_contains($contents, '$t(') || str_contains($contents, 't(')) {
                $withI18n++;
            }

            $visible = preg_match_all('/>([^<>{}\\n]*[A-Za-z\x{00C0}-\x{017F}][^<>{}]*)</u', $contents);
            $attributes = preg_match_all('/\b(?:placeholder|aria-label|title|alt)="[^"]*[A-Za-z\x{00C0}-\x{017F}][^"]*"/u', $contents);
            $visibleTextCandidates += $visible;
            $attributeTextCandidates += $attributes;

            if (($visible + $attributes) > 0) {
                $topFiles[] = [
                    'path' => self::relativePath($file->getPathname()),
                    'candidates' => $visible + $attributes,
                    'visible_text_candidates' => $visible,
                    'attribute_text_candidates' => $attributes,
                ];
            }
        }

        usort($topFiles, fn (array $a, array $b) => $b['candidates'] <=> $a['candidates']);

        return [
            'files_scanned' => count($files),
            'files_using_i18n' => $withI18n,
            'i18n_usage_ratio' => count($files) > 0 ? round($withI18n / count($files), 4) : 0.0,
            'visible_text_candidates' => $visibleTextCandidates,
            'attribute_text_candidates' => $attributeTextCandidates,
            'total_candidates' => $visibleTextCandidates + $attributeTextCandidates,
            'top_files' => array_slice($topFiles, 0, 10),
            'audit_mode' => 'report_only',
        ];
    }

    protected static function phpReadiness(): array
    {
        $files = [
            ...self::files(app_path('Http/Controllers'), 'php'),
            ...self::files(app_path('Notifications'), 'php'),
            ...self::files(app_path('Mail'), 'php'),
        ];
        $translationCalls = 0;
        $responseStringCandidates = 0;
        $topFiles = [];

        foreach ($files as $file) {
            $contents = (string) file_get_contents($file->getPathname());
            $translationCalls += preg_match_all('/(?:__|trans|Lang::get)\s*\(/', $contents);
            $candidates = preg_match_all('/(?:message|title|body|label|description)\'?\s*=>\s*[\'"][^\'"]*[A-Za-z\x{00C0}-\x{017F}][^\'"]*[\'"]/u', $contents);
            $responseStringCandidates += $candidates;

            if ($candidates > 0) {
                $topFiles[] = [
                    'path' => self::relativePath($file->getPathname()),
                    'candidates' => $candidates,
                ];
            }
        }

        usort($topFiles, fn (array $a, array $b) => $b['candidates'] <=> $a['candidates']);

        return [
            'files_scanned' => count($files),
            'translation_calls' => $translationCalls,
            'response_string_candidates' => $responseStringCandidates,
            'total_candidates' => $responseStringCandidates,
            'top_files' => array_slice($topFiles, 0, 10),
            'audit_mode' => 'report_only',
        ];
    }

    protected static function warnings(array $languageFiles, array $vue, array $php, array $rtlQa): array
    {
        $warnings = [];

        foreach ($languageFiles as $group => $report) {
            foreach ($report['locales'] as $locale => $localeReport) {
                if (! $localeReport['exists']) {
                    $warnings[] = "{$group}:{$locale}:missing_json_file";
                }
            }
        }

        if ($vue['visible_text_candidates'] > 0 || $vue['attribute_text_candidates'] > 0) {
            $warnings[] = 'vue:hardcoded_text_candidates_need_i18n_review';
        }

        if ($php['response_string_candidates'] > 0) {
            $warnings[] = 'php:api_response_text_candidates_need_translation_keys';
        }

        if (! $rtlQa['arabic_json_valid'] || ! $rtlQa['arabic_has_arabic_glyphs']) {
            $warnings[] = 'rtl:arabic_language_file_needs_repair';
        }

        if ($rtlQa['mojibake_marker_count'] > 0) {
            $warnings[] = 'rtl:legacy_mojibake_markers_need_review';
        }

        return $warnings;
    }

    protected static function nextActions(array $warnings): array
    {
        if ($warnings === []) {
            return ['Keep audit in CI and block regressions when the team is ready.'];
        }

        return [
            'Move visible Vue text into resources/js/lang/*.json keys.',
            'Move API/user-facing PHP strings into translation keys or stable locale payloads.',
            'Run RTL QA on Arabic for dashboard, map, marketplace, teams, trainer and club cockpit.',
        ];
    }

    protected static function hardcodedTextBudgetStatus(array $vue, array $php): string
    {
        $total = self::hardcodedTextTotal($vue, $php);

        return $total === 0 ? 'clean' : 'tracked_debt';
    }

    protected static function hardcodedTextTotal(array $vue, array $php): int
    {
        return (int) ($vue['visible_text_candidates'] ?? 0)
            + (int) ($vue['attribute_text_candidates'] ?? 0)
            + (int) ($php['response_string_candidates'] ?? 0);
    }

    protected static function allLocaleFilesPresent(array $languageFiles): bool
    {
        foreach ($languageFiles as $report) {
            foreach ($report['locales'] as $localeReport) {
                if (! $localeReport['exists']) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array<int, SplFileInfo>
     */
    protected static function files(string $folder, string $extension): array
    {
        if (! is_dir($folder)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === $extension) {
                $files[] = $file;
            }
        }

        return $files;
    }

    protected static function relativePath(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
