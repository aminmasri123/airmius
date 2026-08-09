<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Tests\TestCase;

class OperationalLocalizationContractTest extends TestCase
{
    public function test_operational_catalogs_have_key_and_placeholder_parity(): void
    {
        foreach ([
            'account_security',
            'admin_operations',
            'editorial',
            'guardian',
            'invoices',
            'media_guidelines',
            'member_lifecycle',
            'nutrition',
            'platform',
            'support',
            'workspace',
        ] as $catalogName) {
            $reference = Arr::dot(require lang_path("de/{$catalogName}.php"));

            foreach (['de', 'en', 'fr', 'ar'] as $locale) {
                $catalog = Arr::dot(require lang_path("{$locale}/{$catalogName}.php"));

                $this->assertSame(
                    array_keys($reference),
                    array_keys($catalog),
                    "{$catalogName}:{$locale} key parity",
                );

                foreach ($reference as $key => $source) {
                    $this->assertSame(
                        $this->placeholders((string) $source),
                        $this->placeholders((string) $catalog[$key]),
                        "{$catalogName}:{$locale} placeholder parity for {$key}",
                    );
                }

                $this->assertSame(
                    require lang_path("{$locale}/{$catalogName}.php"),
                    require resource_path("lang/{$locale}/{$catalogName}.php"),
                    "{$catalogName}:{$locale} resource wrapper parity",
                );
            }
        }
    }

    /** @return list<string> */
    private function placeholders(string $value): array
    {
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $value, $matches);
        $placeholders = array_values(array_unique($matches[0] ?? []));
        sort($placeholders);

        return $placeholders;
    }
}
