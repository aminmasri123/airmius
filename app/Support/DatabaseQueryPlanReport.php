<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class DatabaseQueryPlanReport
{
    public const CONTRACT = 'query-plan-readiness.v1';

    private const AUDIT_PAST = '2025-01-01 00:00:00';

    private const AUDIT_NOW = '2026-08-09 00:00:00';

    /**
     * @return array<string, mixed>
     */
    public function make(
        ?string $connectionName = null,
        bool $analyze = false,
        ?string $onlineDdlReference = null,
    ): array {
        $connectionName = trim((string) ($connectionName ?: config('database.default')));
        $configuration = $this->configurationCheck($connectionName, $analyze);
        if ($configuration['status'] === 'fail') {
            return $this->report([$configuration], false, $analyze);
        }

        try {
            $connection = DB::connection($connectionName);
            $driver = strtolower($connection->getDriverName());
        } catch (Throwable) {
            return $this->report([
                $this->check('input.configuration', 'fail', 'The selected database connection is unavailable; its name and error details are not emitted.'),
            ], false, $analyze);
        }

        $mysqlCompatible = $driver === 'mysql';
        $checks = [
            $configuration,
            $this->check(
                'database.production_like_dialect',
                $mysqlCompatible ? 'pass' : 'pending',
                $mysqlCompatible
                    ? 'The target uses the MySQL-compatible production query-plan path.'
                    : 'Repository plan validation is available, but authoritative evidence requires release-equivalent MySQL or MariaDB staging.',
            ),
            $this->onlineDdlCheck($onlineDdlReference),
        ];

        $contracts = $this->contracts($connection);
        $indexCheck = $this->indexContractCheck($connectionName, $contracts);
        $checks[] = $indexCheck;

        if ($indexCheck['status'] === 'fail') {
            $checks[] = $this->check(
                'query.execution',
                'fail',
                'Plan execution was skipped because the exact ordered index baseline is incomplete or unavailable.',
            );

            return $this->report($checks, $mysqlCompatible, $analyze);
        }

        foreach ($contracts as $contract) {
            $checks[] = $this->planCheck($connection, $driver, $contract, $analyze);
        }

        return $this->report($checks, $mysqlCompatible, $analyze);
    }

    /** @return array{id:string,status:string,detail:string} */
    private function configurationCheck(string $connectionName, bool $analyze): array
    {
        $validName = preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $connectionName) === 1;
        $safeEnvironment = ! $analyze || app()->environment(['staging', 'testing']);
        $passes = $validName && $safeEnvironment;

        return $this->check(
            'input.configuration',
            $passes ? 'pass' : 'fail',
            $passes
                ? 'Connection selection, read-only query registry, environment guard, bounded result sets, and data-minimized output are valid.'
                : 'Use a configured connection name; analyzed plans run only in staging or testing. Input values are not emitted.',
        );
    }

    /** @return array{id:string,status:string,detail:string} */
    private function onlineDdlCheck(?string $reference): array
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return $this->check('evidence.online_ddl', 'pending', 'Attach a non-sensitive, version-bound DBA approval or online-DDL runbook reference.');
        }

        $valid = preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:\/-]{2,119}\z/', $reference) === 1;

        return $this->check(
            'evidence.online_ddl',
            $valid ? 'pass' : 'fail',
            $valid
                ? 'A syntactically valid online-DDL evidence reference is present; its value is not emitted.'
                : 'The online-DDL reference must be a short non-sensitive artifact identifier without whitespace, query data, or credentials.',
        );
    }

    /**
     * @param  array<int, array{id:string,table:string,index:string,columns:array<int,string>,query:Builder}>  $contracts
     * @return array{id:string,status:string,detail:string}
     */
    private function indexContractCheck(string $connectionName, array $contracts): array
    {
        try {
            $schema = Schema::connection($connectionName);
            $missing = collect($contracts)->filter(function (array $contract) use ($schema): bool {
                $index = collect($schema->getIndexes($contract['table']))
                    ->firstWhere('name', $contract['index']);

                return ! is_array($index) || array_values($index['columns'] ?? []) !== $contract['columns'];
            });
        } catch (Throwable) {
            return $this->check('database.index_contracts', 'fail', 'Required query indexes could not be inspected; database details are not emitted.');
        }

        return $this->check(
            'database.index_contracts',
            $missing->isEmpty() ? 'pass' : 'fail',
            $missing->isEmpty()
                ? count($contracts).' critical query families have their exact ordered index contract.'
                : $missing->count().' critical query families are missing an exact ordered index contract; names and schema details remain in source control, not evidence output.',
        );
    }

    /**
     * @param  array{id:string,table:string,index:string,columns:array<int,string>,query:Builder}  $contract
     * @return array{id:string,status:string,detail:string}
     */
    private function planCheck(Connection $connection, string $driver, array $contract, bool $analyze): array
    {
        try {
            $query = $contract['query'];
            $rows = $driver === 'mysql'
                ? $this->mysqlPlan($connection, $query, $analyze)
                : $connection->select('EXPLAIN QUERY PLAN '.$query->toSql(), $query->getBindings());
            $plan = strtolower(json_encode($rows, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
            $usesExpectedIndex = str_contains($plan, strtolower($contract['index']));
        } catch (Throwable) {
            return $this->check(
                'query.'.$contract['id'],
                'fail',
                'The bounded read-only plan could not be compiled or inspected; SQL, bindings, values, and database errors are not emitted.',
            );
        }

        if ($usesExpectedIndex) {
            return $this->check(
                'query.'.$contract['id'],
                'pass',
                $analyze && $driver === 'mysql'
                    ? 'The bounded analyzed plan selected the expected access path.'
                    : 'The bounded structural plan selected the expected access path.',
            );
        }

        return $this->check(
            'query.'.$contract['id'],
            $driver === 'mysql' ? 'warn' : 'pass',
            $driver === 'mysql'
                ? 'The plan completed but selected another access path; DBA review is required before approval.'
                : 'The repository plan compiled and its exact index contract exists; production optimizer selection remains a staging concern.',
        );
    }

    /** @return array<int, object> */
    private function mysqlPlan(Connection $connection, Builder $query, bool $analyze): array
    {
        if (! $analyze) {
            return $connection->select('EXPLAIN FORMAT=JSON '.$query->toSql(), $query->getBindings());
        }

        $versionRows = $connection->select('SELECT VERSION() AS version');
        $version = strtolower((string) ($versionRows[0]->version ?? ''));
        $prefix = str_contains($version, 'mariadb')
            ? 'ANALYZE FORMAT=JSON '
            : 'EXPLAIN ANALYZE ';

        return $connection->select($prefix.$query->toSql(), $query->getBindings());
    }

    /**
     * @return array<int, array{id:string,table:string,index:string,columns:array<int,string>,query:Builder}>
     */
    private function contracts(Connection $connection): array
    {
        $table = fn (string $name): Builder => $connection->table($name)->select($name.'.id');

        return [
            $this->contract('guest.club_city', 'clubs', 'clubs_public_city_idx', ['verification_status', 'is_listed', 'city'], $table('clubs')->where('verification_status', 'verified')->where('is_listed', true)->where('city', 'Airmius Audit City')->limit(50)),
            $this->contract('guest.club_sport', 'clubs', 'clubs_public_sport_idx', ['verification_status', 'is_listed', 'sport_type'], $table('clubs')->where('verification_status', 'verified')->where('is_listed', true)->where('sport_type', 'airmius-audit')->limit(50)),
            $this->contract('guest.event_city', 'events', 'events_public_city_start_idx', ['visibility', 'status', 'location_city', 'start_time'], $table('events')->where('visibility', 'public')->where('status', 'scheduled')->where('location_city', 'Airmius Audit City')->where('start_time', '>=', self::AUDIT_PAST)->orderBy('start_time')->limit(50)),
            $this->contract('guest.team_sport', 'teams', 'teams_sport_club_idx', ['sport_type', 'club_id'], $table('teams')->where('sport_type', 'airmius-audit')->orderBy('club_id')->limit(50)),
            $this->contract('guest.marketplace_catalog', 'marketplace_products', 'marketplace_public_catalog_idx', ['status', 'moderation_status', 'category', 'id'], $table('marketplace_products')->where('status', 'published')->where('moderation_status', 'approved')->where('category', 'product')->orderByDesc('id')->limit(24)),
            $this->contract('guest.marketplace_price', 'marketplace_products', 'marketplace_public_price_idx', ['status', 'moderation_status', 'price_cents', 'id'], $table('marketplace_products')->where('status', 'published')->where('moderation_status', 'approved')->orderBy('price_cents')->orderBy('id')->limit(24)),
            $this->contract('guest.learning_catalog', 'learning_courses', 'learning_public_catalog_idx', ['status', 'is_public', 'featured_at', 'published_at', 'id'], $table('learning_courses')->where('status', 'published')->where('is_public', true)->orderByDesc('featured_at')->orderByDesc('published_at')->orderByDesc('id')->limit(24)),
            $this->contract('guest.learning_filter', 'learning_courses', 'learning_public_filter_idx', ['status', 'is_public', 'category', 'level', 'is_free'], $table('learning_courses')->where('status', 'published')->where('is_public', true)->where('category', 'training')->where('level', 'beginner')->where('is_free', true)->limit(24)),
            $this->contract('workspace.notifications_recent', 'notifications', 'notifications_user_created_idx', ['user_id', 'created_at'], $table('notifications')->where('user_id', 0)->orderByDesc('created_at')->limit(50)),
            $this->contract('workspace.notifications_unread', 'notifications', 'notifications_user_unread_type_created_idx', ['user_id', 'read', 'type', 'created_at'], $table('notifications')->where('user_id', 0)->where('read', false)->where('type', 'system')->orderByDesc('created_at')->limit(50)),
            $this->contract('workspace.conversation_membership', 'conversation_users', 'conversation_users_user_conversation_idx', ['user_id', 'conversation_id'], $table('conversation_users')->where('user_id', 0)->orderByDesc('conversation_id')->limit(50)),
            $this->contract('workspace.events_schedule', 'events', 'events_start_status_idx', ['start_time', 'status'], $table('events')->where('start_time', '>=', self::AUDIT_PAST)->where('status', 'scheduled')->orderBy('start_time')->limit(50)),
            $this->contract('workspace.team_events', 'events', 'events_team_start_idx', ['team_id', 'start_time'], $table('events')->where('team_id', 0)->where('start_time', '>=', self::AUDIT_PAST)->orderBy('start_time')->limit(50)),
            $this->contract('workspace.club_events', 'events', 'events_club_start_idx', ['club_id', 'start_time'], $table('events')->where('club_id', 0)->where('start_time', '>=', self::AUDIT_PAST)->orderBy('start_time')->limit(50)),
            $this->contract('scheduler.event_reminders', 'events', 'events_reminder_due_idx', ['status', 'reminder_sent_at', 'reminder_at', 'start_time'], $table('events')->where('status', 'scheduled')->whereNull('reminder_sent_at')->where('reminder_at', '<=', self::AUDIT_NOW)->where('start_time', '>=', self::AUDIT_NOW)->limit(50)),
            $this->contract('scheduler.mobile_push_retry', 'mobile_push_deliveries', 'mobile_push_status_retry_queued_idx', ['status', 'next_attempt_at', 'queued_at'], $table('mobile_push_deliveries')->where('status', 'retry')->where('next_attempt_at', '<=', self::AUDIT_NOW)->orderBy('queued_at')->limit(50)),
            $this->contract('scheduler.domain_outbox', 'domain_outbox_events', 'domain_outbox_dispatch_idx', ['published_at', 'available_at', 'processing_at', 'occurred_at'], $table('domain_outbox_events')->whereNull('published_at')->where('available_at', '<=', self::AUDIT_NOW)->whereNull('processing_at')->orderBy('occurred_at')->limit(50)),
            $this->contract('training.athlete_daily', 'training_logs', 'training_logs_user_id_performed_at_index', ['user_id', 'performed_at'], $table('training_logs')->where('user_id', 0)->where('performed_at', '>=', self::AUDIT_PAST)->orderByDesc('performed_at')->limit(50)),
            $this->contract('files.event_workspace', 'files', 'files_club_id_team_id_event_id_folder_id_index', ['club_id', 'team_id', 'event_id', 'folder_id'], $table('files')->whereNull('club_id')->whereNull('team_id')->where('event_id', 0)->whereNull('folder_id')->limit(50)),
            $this->contract('privacy.analytics_consent', 'users', 'users_product_analytics_cohort_index', ['product_analytics_consent', 'birth_date', 'last_seen_at'], $table('users')->where('product_analytics_consent', true)->where('birth_date', '<=', '2008-01-01')->where('last_seen_at', '>=', self::AUDIT_PAST)->limit(50)),
            $this->contract('recruiting.opportunity_match', 'organization_jobs', 'org_jobs_sport_experience_idx', ['sport_id', 'minimum_experience_level'], $table('organization_jobs')->where('sport_id', 0)->where('minimum_experience_level', 'beginner')->limit(50)),
            $this->contract('commerce.refund_ledger', 'commerce_refunds', 'commerce_refunds_commerce_order_id_status_index', ['commerce_order_id', 'status'], $table('commerce_refunds')->where('commerce_order_id', 0)->where('status', 'processing')->limit(50)),
            $this->contract('commerce.payout_queue', 'marketplace_payouts', 'marketplace_payouts_queue_idx', ['status', 'created_at'], $table('marketplace_payouts')->where('status', 'prepared')->orderBy('created_at')->limit(50)),
            $this->contract('sponsor.public_trust', 'sponsors', 'sponsors_public_trust_idx', ['verification_status', 'ends_at'], $table('sponsors')->where('verification_status', 'verified')->where('ends_at', '>=', self::AUDIT_NOW)->limit(50)),
            $this->contract('sponsor.outcome_window', 'ad_events', 'ad_events_ad_campaign_id_event_type_occurred_at_index', ['ad_campaign_id', 'event_type', 'occurred_at'], $table('ad_events')->where('ad_campaign_id', 0)->where('event_type', 'conversion')->whereBetween('occurred_at', [self::AUDIT_PAST, self::AUDIT_NOW])->limit(50)),
        ];
    }

    /**
     * @param  array<int, string>  $columns
     * @return array{id:string,table:string,index:string,columns:array<int,string>,query:Builder}
     */
    private function contract(string $id, string $table, string $index, array $columns, Builder $query): array
    {
        return compact('id', 'table', 'index', 'columns', 'query');
    }

    /** @param array<int, array{id:string,status:string,detail:string}> $checks */
    private function report(array $checks, bool $mysqlCompatible, bool $analyze): array
    {
        $summary = [
            'pass' => collect($checks)->where('status', 'pass')->count(),
            'warn' => collect($checks)->where('status', 'warn')->count(),
            'pending' => collect($checks)->where('status', 'pending')->count(),
            'fail' => collect($checks)->where('status', 'fail')->count(),
        ];
        $automatedChecksPassed = $summary['fail'] === 0;
        $evidenceComplete = $automatedChecksPassed
            && $summary['warn'] === 0
            && $summary['pending'] === 0
            && $mysqlCompatible
            && $analyze;

        return [
            'contract' => self::CONTRACT,
            'generated_at' => now()->toIso8601String(),
            'decision' => $evidenceComplete ? 'go' : 'no_go',
            'automated_checks_passed' => $automatedChecksPassed,
            'evidence_complete' => $evidenceComplete,
            'mode' => $mysqlCompatible ? ($analyze ? 'analyzed' : 'structural') : 'repository',
            'summary' => $summary,
            'checks' => $checks,
            'privacy' => [
                'stores_sql' => false,
                'stores_bindings' => false,
                'stores_query_results' => false,
                'stores_plan_bodies' => false,
                'stores_connection_details' => false,
                'stores_personal_data' => false,
            ],
        ];
    }

    /** @return array{id:string,status:string,detail:string} */
    private function check(string $id, string $status, string $detail): array
    {
        return compact('id', 'status', 'detail');
    }
}
