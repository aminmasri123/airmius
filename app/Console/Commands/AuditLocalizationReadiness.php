<?php

namespace App\Console\Commands;

use App\Support\LocalizationReadinessReport;
use Illuminate\Console\Command;

class AuditLocalizationReadiness extends Command
{
    protected $signature = 'airmius:i18n-audit {--json} {--baseline=resources/localization-baseline.json} {--fail-on-regression}';

    protected $description = 'Report localization, RTL and hardcoded text readiness for web and mobile clients.';

    public function handle(): int
    {
        $report = LocalizationReadinessReport::make();
        $regressions = $this->regressions($report);

        if ($this->option('json')) {
            $report['regressions'] = $regressions;
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $this->exitCode($regressions);
        }

        $this->info('Airmius localization readiness');
        $this->line('Locales: '.implode(', ', $report['supported_locales']));
        $this->line('RTL: '.implode(', ', $report['rtl_locales']));
        $this->line(sprintf(
            'Vue: %d files, %.1f%% using i18n, %d visible text candidates.',
            $report['vue']['files_scanned'],
            $report['vue']['i18n_usage_ratio'] * 100,
            $report['vue']['visible_text_candidates'],
        ));
        $this->line(sprintf(
            'PHP: %d files, %d translation calls, %d response text candidates.',
            $report['php']['files_scanned'],
            $report['php']['translation_calls'],
            $report['php']['response_string_candidates'],
        ));

        foreach ($report['warnings'] as $warning) {
            $this->warn($warning);
        }

        foreach ($regressions as $regression) {
            $this->error($regression);
        }

        return $this->exitCode($regressions);
    }

    protected function regressions(array $report): array
    {
        $baselinePath = base_path((string) $this->option('baseline'));

        if (! is_file($baselinePath)) {
            return [];
        }

        $baseline = json_decode((string) file_get_contents($baselinePath), true);

        if (! is_array($baseline)) {
            return ['baseline:invalid_json'];
        }

        $checks = [
            'quality_gates.hardcoded_text_total_candidates',
            'vue.visible_text_candidates',
            'vue.attribute_text_candidates',
            'php.response_string_candidates',
        ];
        $regressions = [];

        foreach ($checks as $key) {
            $current = (int) data_get($report, $key, 0);
            $allowed = (int) data_get($baseline, $key, PHP_INT_MAX);

            if ($current > $allowed) {
                $regressions[] = "{$key}:{$current}>{$allowed}";
            }
        }

        return $regressions;
    }

    protected function exitCode(array $regressions): int
    {
        return $this->option('fail-on-regression') && $regressions !== []
            ? self::FAILURE
            : self::SUCCESS;
    }
}
