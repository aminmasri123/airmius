<?php

namespace App\Support;

use App\Services\RolloutDecisionService;
use Illuminate\Support\Facades\Route;
use JsonException;

final class StagedRolloutReadinessReport
{
    public function __construct(private readonly RolloutDecisionService $rollouts) {}

    /** @return array<string, mixed> */
    public function make(): array
    {
        $registry = RolloutAcceptanceRegistry::definitions();
        $checks = [
            $this->contractCheck($registry),
            $this->configurationCheck($registry),
            $this->assignmentCheck(),
            $this->routingCheck($registry),
            $this->guestSafetyCheck($registry),
            $this->artifactCheck($registry),
            $this->externalGateCheck($registry),
        ];
        $summary = array_fill_keys(['pass', 'pending', 'fail'], 0);
        foreach ($checks as $check) {
            $summary[$check['status']]++;
        }

        return [
            'contract' => RolloutAcceptanceRegistry::CONTRACT,
            'generated_at' => now()->utc()->toIso8601String(),
            'decision' => $summary['fail'] === 0 && $summary['pending'] === 0 ? 'go' : 'no_go',
            'automated_checks_passed' => $summary['fail'] === 0,
            'summary' => $summary,
            'checks' => $checks,
            'privacy' => [
                'stores_assignments' => false,
                'stores_personal_data' => false,
                'stores_secrets' => false,
                'outputs_actor_identifiers' => false,
                'outputs_buckets' => false,
                'adds_guest_tracking' => false,
            ],
        ];
    }

    /** @param array<string, mixed> $registry */
    private function contractCheck(array $registry): array
    {
        $passes = ($registry['contract'] ?? null) === RolloutAcceptanceRegistry::CONTRACT
            && ($registry['stages'] ?? null) === RolloutAcceptanceRegistry::STAGES
            && count($registry['features'] ?? []) === 4
            && data_get($registry, 'guest_safety.shared_cache_preserved') === true
            && data_get($registry, 'guest_safety.tracking_added') === false
            && data_get($registry, 'rollback.preserves_user_data') === true;

        return $this->check(
            'repository.contract',
            $passes ? 'pass' : 'fail',
            $passes ? 'Four authenticated workspace flags, 0/5/25/100 stages, kill switches, RACI, guest isolation, and data-preserving rollback are defined.' : 'The staged-rollout contract is incomplete.',
        );
    }

    /** @param array<string, mixed> $registry */
    private function configurationCheck(array $registry): array
    {
        $configured = config('airmius_rollout.features', []);
        $errors = [];
        foreach (array_keys($registry['features']) as $feature) {
            $stage = data_get($configured, "{$feature}.stage");
            if (! in_array($stage, RolloutAcceptanceRegistry::STAGES, true)) {
                $errors[] = $feature;
            }
            if (! is_bool(data_get($configured, "{$feature}.kill_switch"))) {
                $errors[] = "{$feature}_kill_switch";
            }
        }
        if (trim((string) config('airmius_rollout.salt')) === '') {
            $errors[] = 'rollout_salt';
        }

        return $this->check(
            'repository.configuration',
            $errors === [] ? 'pass' : 'fail',
            $errors === [] ? 'All feature stages and kill switches are valid; the configured secret is present and is not emitted.' : 'Invalid setting names: '.implode(', ', $errors).'. Values are not emitted.',
        );
    }

    private function assignmentCheck(): array
    {
        $first = $this->rollouts->bucketFor('club_operating_system', 'readiness-probe');
        $second = $this->rollouts->bucketFor('club_operating_system', 'readiness-probe');
        $passes = $first === $second && $first >= 0 && $first <= 99;

        return $this->check(
            'repository.assignment',
            $passes ? 'pass' : 'fail',
            $passes ? 'Assignment is deterministic and in range; the probe key and resulting bucket are not included in this report.' : 'Deterministic assignment failed.',
        );
    }

    /** @param array<string, mixed> $registry */
    private function routingCheck(array $registry): array
    {
        $errors = [];
        foreach ($registry['features'] as $feature => $definition) {
            foreach ($definition['routes'] as $name) {
                $route = Route::getRoutes()->getByName($name);
                if ($route === null) {
                    $errors[] = "missing:{$name}";

                    continue;
                }
                $middleware = $route->gatherMiddleware();
                if (! in_array("rollout:{$feature}", $middleware, true)) {
                    $errors[] = "ungated:{$name}";
                }
                if (collect($middleware)->doesntContain(static fn (string $item): bool => str_contains($item, 'auth'))) {
                    $errors[] = "public:{$name}";
                }
            }
        }

        return $this->check(
            'repository.routing',
            $errors === [] ? 'pass' : 'fail',
            $errors === [] ? 'All declared web/API workspace routes are authenticated and use their assigned rollout middleware.' : 'Route errors: '.implode(', ', $errors),
        );
    }

    /** @param array<string, mixed> $registry */
    private function guestSafetyCheck(array $registry): array
    {
        $errors = [];
        $count = 0;
        $declaredRoutes = collect($registry['features'])
            ->flatMap(static fn (array $feature): array => $feature['routes'])
            ->all();
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            $rolloutMiddleware = collect($route->gatherMiddleware())
                ->filter(static fn (string $middleware): bool => str_starts_with($middleware, 'rollout:'));
            if ($rolloutMiddleware->isNotEmpty() && ! in_array($name, $declaredRoutes, true)) {
                $errors[] = $name !== '' ? $name : 'unnamed_route';
            }
            if (! collect($registry['guest_safety']['route_prefixes'])->contains(static fn (string $prefix): bool => str_starts_with($name, $prefix))) {
                continue;
            }
            $count++;
            if ($rolloutMiddleware->isNotEmpty()) {
                $errors[] = $name;
            }
        }

        $passes = $count > 0 && $errors === [];

        return $this->check(
            'repository.guest_safety',
            $passes ? 'pass' : 'fail',
            $passes ? "{$count} named guest/public routes remain outside actor rollout assignment; no cookie, event table, or tracking SDK is introduced." : 'Guest route isolation failed: '.implode(', ', $errors),
        );
    }

    /** @param array<string, mixed> $registry */
    private function artifactCheck(array $registry): array
    {
        $files = [
            'config/airmius_rollout.php',
            'app/Services/RolloutDecisionService.php',
            'app/Http/Middleware/EnsureFeatureRollout.php',
            'docs/STAGED_ROLLOUT_RUNBOOK.md',
            'tests/Feature/StagedRolloutAcceptanceContractTest.php',
            $registry['evidence']['template'],
        ];
        foreach (['de', 'en', 'fr', 'ar'] as $locale) {
            $files[] = "lang/{$locale}/rollout.php";
        }
        $missing = array_values(array_filter($files, static fn (string $file): bool => ! is_file(base_path($file))));

        return $this->check(
            'repository.artifacts',
            $missing === [] ? 'pass' : 'fail',
            $missing === [] ? count($files).' rollout, fallback, multilingual, test, runbook, and evidence artifacts are present.' : 'Missing: '.implode(', ', $missing),
        );
    }

    /** @param array<string, mixed> $registry */
    private function externalGateCheck(array $registry): array
    {
        $manifest = $this->json(base_path('resources/release/platform_release_gates.json'));
        $gate = collect($manifest['data']['gates'] ?? [])->firstWhere('id', $registry['evidence']['external_gate']);
        $status = data_get($gate, 'status', 'missing');
        $mapped = match ($status) {
            'passed', 'waived' => 'pass',
            'failed', 'missing' => 'fail',
            default => 'pending',
        };

        return $this->check(
            'external.staged_rollout',
            $mapped,
            $mapped === 'pass' ? 'The owner-bound 5/25/100 progression gate has review evidence.' : 'Real stage observation and owner approval remain external; repository checks cannot approve them.',
        );
    }

    /** @return array{data:array<string,mixed>,errors:array<int,string>} */
    private function json(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return ['data' => [], 'errors' => ['file_missing']];
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

            return is_array($data) ? ['data' => $data, 'errors' => []] : ['data' => [], 'errors' => ['root_not_object']];
        } catch (JsonException) {
            return ['data' => [], 'errors' => ['invalid_json']];
        }
    }

    /** @return array{id:string,status:string,detail:string} */
    private function check(string $id, string $status, string $detail): array
    {
        return compact('id', 'status', 'detail');
    }
}
