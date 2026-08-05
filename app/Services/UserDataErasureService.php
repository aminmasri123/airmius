<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\UploadStorage;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class UserDataErasureService
{
    private const CATEGORIES = [
        'profile' => [
            'label' => 'Profil- und Kontaktdaten',
            'description' => 'Name, Foto, Telefonnummer, Adresse, Bio und weitere Profilangaben werden entfernt. E-Mail-Adresse und Zugangsdaten bleiben für dein Konto erhalten.',
        ],
        'content' => [
            'label' => 'Beiträge, Kommentare und Storys',
            'description' => 'Eigene Beiträge, Kommentare, Storys und die dazugehörigen persönlichen Medien werden dauerhaft entfernt.',
        ],
        'messages' => [
            'label' => 'Eigene Chat-Nachrichten',
            'description' => 'Der Inhalt eigener Nachrichten und Anhänge wird entfernt. In Unterhaltungen bleibt nur ein neutraler Löschhinweis bestehen.',
        ],
        'files' => [
            'label' => 'Dateien und Ordner',
            'description' => 'Eigene hochgeladene Dateien, Vorschaubilder und persönliche Ordner werden dauerhaft entfernt.',
        ],
        'sport_and_health' => [
            'label' => 'Sport-, Standort- und Gesundheitsdaten',
            'description' => 'Importierte Aktivitäten, Routen, Tracks, Sportprofile, Trainingsprotokolle sowie Ernährungsdaten werden entfernt.',
        ],
        'social_and_integrations' => [
            'label' => 'Soziale Verbindungen und Integrationen',
            'description' => 'Freundschaften, Follows, Benachrichtigungen, Gerätekennungen und externe Sport-Verknüpfungen werden entfernt. Die technische Zuordnung für Google- oder Microsoft-Anmeldung bleibt erhalten.',
        ],
        'commerce' => [
            'label' => 'Shop- und Bestelldaten',
            'description' => 'Warenkörbe, Lieferadressen, Merkliste und Bewertungen werden entfernt. Nicht aufbewahrungspflichtige, abgeschlossene Bestellungen werden anonymisiert.',
        ],
    ];

    /**
     * @return array<int, array{key: string, label: string, description: string}>
     */
    public function categories(): array
    {
        return collect(self::CATEGORIES)
            ->map(fn (array $definition, string $key) => ['key' => $key, ...$definition])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function categoryKeys(): array
    {
        return array_keys(self::CATEGORIES);
    }

    /**
     * @param  array<int, mixed>  $categories
     * @return array<int, string>
     */
    public function normalizeCategories(array $categories): array
    {
        $requested = array_values(array_unique(array_filter(array_map(
            fn ($category) => is_string($category) ? trim($category) : '',
            $categories,
        ))));

        $unknown = array_diff($requested, $this->categoryKeys());

        if ($requested === [] || $unknown !== []) {
            throw new InvalidArgumentException('Mindestens eine gültige Datenkategorie muss ausgewählt werden.');
        }

        return array_values(array_filter(
            $this->categoryKeys(),
            fn (string $key) => in_array($key, $requested, true),
        ));
    }

    /**
     * @param  array<int, string>  $categories
     * @return array{categories: array<int, string>, deleted: array<string, int>, retained: array<int, string>}
     */
    public function erase(User $user, array $categories): array
    {
        $categories = $this->normalizeCategories($categories);
        $storagePaths = [];
        $summary = [
            'categories' => $categories,
            'deleted' => [],
            'retained' => [
                'Dein Konto, deine E-Mail-Adresse und deine Zugangsdaten bleiben erhalten.',
                'Vereins-, Team- und Berechtigungszuordnungen werden nicht automatisch gelöscht, damit keine Daten anderer Personen oder Organisationen verloren gehen.',
            ],
        ];

        DB::transaction(function () use ($user, $categories, &$storagePaths, &$summary): void {
            foreach ($categories as $category) {
                match ($category) {
                    'profile' => $this->eraseProfile($user, $storagePaths, $summary),
                    'content' => $this->eraseContent($user, $storagePaths, $summary),
                    'messages' => $this->eraseMessages($user, $storagePaths, $summary),
                    'files' => $this->eraseFiles($user, $storagePaths, $summary),
                    'sport_and_health' => $this->eraseSportAndHealth($user, $storagePaths, $summary),
                    'social_and_integrations' => $this->eraseSocialAndIntegrations($user, $summary),
                    'commerce' => $this->eraseCommerce($user, $summary),
                };
            }
        }, 3);

        $this->deleteStoredPaths($storagePaths);

        return $summary;
    }

    private function eraseProfile(User $user, array &$storagePaths, array &$summary): void
    {
        if ($this->hasColumn('users', 'profile_photo_path')) {
            $this->rememberPath(
                $storagePaths,
                (string) config('jetstream.profile_photo_disk', 'public'),
                (string) $user->profile_photo_path,
            );
        }

        $updates = [
            'name' => 'Airmius Nutzer #'.$user->id,
            'first_name' => null,
            'last_name' => null,
            'phone' => null,
            'profile_photo_path' => null,
            'country' => null,
            'athlete_license_number' => null,
            'street' => null,
            'house_number' => null,
            'postal_code' => null,
            'city' => null,
            'state' => null,
            'birth_date' => null,
            'gender' => null,
            'guardian_email' => null,
            'guardian_user_id' => null,
            'guardian_consent_requested_at' => null,
            'guardian_consent_at' => null,
            'guardian_consent_rejected_at' => null,
            'guardian_consent_revoked_at' => null,
            'guardian_consent_revoked_by_email' => null,
            'guardian_consent_token' => null,
            'bio' => null,
            'profile_visibility' => 'private',
            'direct_message_privacy' => 'friends',
            'friend_request_privacy' => 'friends',
            'ads_personalization_consent' => false,
            'ads_measurement_consent' => false,
            'event_radius_km' => null,
            'event_default_sport_ids' => null,
            'event_default_filters' => null,
            'dashboard_widget_keys' => null,
            'enabled_navigation_modules' => null,
            'notification_channels' => null,
            'notification_quiet_time' => null,
            'last_seen_at' => null,
            'last_login_at' => null,
            'language' => null,
            'timezone' => null,
            'theme' => null,
            'status' => 'offline',
        ];

        $count = $this->updateWhereIn('users', 'id', [(int) $user->id], $updates);
        $this->record($summary, 'Profil- und Kontaktdaten', max(1, $count));
    }

    private function eraseContent(User $user, array &$storagePaths, array &$summary): void
    {
        $userId = (int) $user->id;
        $postRows = $this->tableRowsForUser('posts', $userId, ['id', 'image']);
        $postIds = $this->ids($postRows);

        foreach ($postRows as $post) {
            $this->rememberPath($storagePaths, UploadStorage::disk(), $post->image ?? null);
        }

        $postFileIds = $this->attachedFileIds('post_attachments', 'post_id', $postIds, $userId);
        $deletedFiles = $this->deleteFilesByIds($userId, $postFileIds, $storagePaths);

        $commentRows = $this->tableRowsForUser('comments', $userId, ['id']);
        $commentIds = $this->ids($commentRows);
        $this->deleteMorphedRows('likes', 'likeable_type', 'likeable_id', Comment::class, $commentIds);
        $this->deleteIn('comments', 'id', $commentIds);

        $this->deleteMorphedRows('likes', 'likeable_type', 'likeable_id', Post::class, $postIds);
        $this->deleteMorphedRows('content_reports', 'reportable_type', 'reportable_id', Post::class, $postIds);
        $this->deleteIn('post_helpfuls', 'post_id', $postIds);
        $this->deleteIn('posts', 'id', $postIds);

        $storyRows = $this->tableRowsForUser('stories', $userId, ['id', 'media_path', 'media_thumbnail_path']);
        $storyIds = $this->ids($storyRows);
        foreach ($storyRows as $story) {
            $this->rememberPath($storagePaths, UploadStorage::disk(), $story->media_path ?? null);
            $this->rememberPath($storagePaths, UploadStorage::disk(), $story->media_thumbnail_path ?? null);
        }
        $this->deleteIn('story_views', 'story_id', $storyIds);
        $this->deleteIn('story_reactions', 'story_id', $storyIds);
        $this->deleteIn('stories', 'id', $storyIds);

        $otherComments = $this->deleteTablesForUser($userId, ['event_comments', 'learning_lesson_comments']);
        $this->record($summary, 'Beiträge, Kommentare und Storys', count($postIds) + count($commentIds) + count($storyIds) + $otherComments);
        $this->record($summary, 'Medien aus Beiträgen und Storys', $deletedFiles);
    }

    private function eraseMessages(User $user, array &$storagePaths, array &$summary): void
    {
        $userId = (int) $user->id;
        $messageRows = $this->tableRowsForColumn('messages', 'sender_id', $userId, ['id']);
        $messageIds = $this->ids($messageRows);

        $fileIds = $this->attachedFileIds('message_attachments', 'message_id', $messageIds, $userId);
        $deletedFiles = $this->deleteFilesByIds($userId, $fileIds, $storagePaths);
        $this->deleteIn('message_attachments', 'message_id', $messageIds);
        $this->deleteIn('message_reactions', 'message_id', $messageIds);
        $this->deleteIn('message_hides', 'message_id', $messageIds);

        $updated = $this->updateWhereIn('messages', 'id', $messageIds, [
            'message' => '[Nachricht gelöscht]',
            'kind' => 'text',
            'metadata' => null,
            'status' => 'deleted',
            'read_at' => null,
            'deleted_at' => now(),
        ]);

        $this->record($summary, 'Eigene Chat-Nachrichten', $updated);
        $this->record($summary, 'Chat-Anhänge', $deletedFiles);
    }

    private function eraseFiles(User $user, array &$storagePaths, array &$summary): void
    {
        $userId = (int) $user->id;
        $fileIds = $this->ids($this->tableRowsForUser('files', $userId, ['id']));
        $deletedFiles = $this->deleteFilesByIds($userId, $fileIds, $storagePaths);
        $deletedFolders = $this->deleteForUser('folders', $userId);

        $this->record($summary, 'Dateien', $deletedFiles);
        $this->record($summary, 'Ordner', $deletedFolders);
    }

    private function eraseSportAndHealth(User $user, array &$storagePaths, array &$summary): void
    {
        $userId = (int) $user->id;
        $deleted = $this->deleteTablesForUser($userId, [
            'connected_sport_activities',
            'sport_route_tracks',
            'sport_routes',
            'sport_places',
            'nutrition_meals',
            'nutrition_goals',
            'training_logs',
            'training_plan_assignments',
            'user_sport_skills',
            'user_sports',
            'gamification_xp_events',
            'training_availability_statuses',
            'training_check_ins',
            'sport_matching_applications',
        ]);

        $planIds = $this->ids($this->tableRowsForColumnWithNull('training_plans', 'created_by', $userId, 'team_id', ['id']));
        if ($planIds !== [] && $this->hasTable('training_plan_items')) {
            foreach (DB::table('training_plan_items')->whereIn('training_plan_id', $planIds)->get(['image_path']) as $item) {
                $this->rememberPath($storagePaths, UploadStorage::disk(), $item->image_path ?? null);
            }
        }
        $deleted += $this->deleteIn('training_plans', 'id', $planIds);

        $this->record($summary, 'Sport-, Standort- und Gesundheitsdaten', $deleted);
    }

    private function eraseSocialAndIntegrations(User $user, array &$summary): void
    {
        $userId = (int) $user->id;

        // Provider- und Provider-User-ID müssen für die Anmeldung erhalten bleiben. Alle
        // zusätzlichen Profildaten und OAuth-Tokens werden jedoch entfernt.
        $this->updateForUser('social_accounts', $userId, [
            'email' => null,
            'name' => null,
            'avatar_url' => null,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
        ]);

        $deleted = $this->deleteForUser('connected_sport_accounts', $userId);
        $deleted += $this->deleteWhereAny('friendships', ['user_id', 'friend_id'], $userId);
        $deleted += $this->deleteWhereAny('friend_invitations', ['sender_id', 'recipient_id'], $userId);
        $deleted += $this->deleteWhereAny('follows', ['follower_id', 'followed_id'], $userId);
        $deleted += $this->deleteWhereAny('user_blocks', ['user_id', 'blocked_user_id'], $userId);
        $deleted += $this->deleteWhereAny('profile_recommendations', ['profile_user_id', 'author_id'], $userId);
        $deleted += $this->deleteTablesForUser($userId, [
            'notifications',
            'mobile_push_deliveries',
            'mobile_device_tokens',
            'likes',
            'post_helpfuls',
            'message_reactions',
            'message_hides',
            'story_views',
            'story_reactions',
            'activities',
        ]);

        $this->updateForUser('ad_events', $userId, [
            'user_id' => null,
            'session_hash' => null,
            'ip_hash' => null,
            'user_agent_hash' => null,
            'metadata' => null,
        ]);
        $this->updateForUser('external_provider_usage_events', $userId, [
            'user_id' => null,
            'metadata' => null,
        ]);

        $this->record($summary, 'Soziale Verbindungen, Benachrichtigungen und Integrationen', $deleted);
    }

    private function eraseCommerce(User $user, array &$summary): void
    {
        $userId = (int) $user->id;
        $deleted = $this->deleteTablesForUser($userId, [
            'commerce_carts',
            'commerce_shipping_addresses',
            'marketplace_product_wishlists',
            'marketplace_product_reviews',
        ]);

        $allOrderIds = $this->ids($this->tableRowsForUser('commerce_orders', $userId, ['id']));
        $anonymizedOrderIds = $this->anonymizableOrderIds($userId);

        if ($anonymizedOrderIds !== []) {
            $updated = $this->updateWhereIn('commerce_orders', 'id', $anonymizedOrderIds, [
                'user_id' => null,
                'guest_name' => null,
                'guest_email' => null,
                'access_token' => null,
                'customer_type' => 'consumer',
                'customer_company' => null,
                'customer_vat_id' => null,
                'customer_vat_is_valid' => null,
                'customer_vat_validated_at' => null,
                'shipping_label_url' => null,
                'tracking_number' => null,
                'tracking_url' => null,
                'checkout_url' => null,
                'payload' => json_encode([
                    'anonymized_at' => now()->toIso8601String(),
                    'retention' => 'financial-record-without-personal-order-data',
                ], JSON_THROW_ON_ERROR),
            ]);
            $deleted += $updated;

            if ($this->hasTable('commerce_return_requests')) {
                $returnQuery = DB::table('commerce_return_requests')->whereIn('commerce_order_id', $anonymizedOrderIds);
                if ($this->hasColumn('commerce_return_requests', 'status')) {
                    $returnQuery->whereIn('status', ['closed', 'resolved', 'refunded', 'rejected', 'cancelled']);
                }
                $returnIds = $returnQuery->pluck('id')->all();
                $deleted += $this->updateWhereIn('commerce_return_requests', 'id', $returnIds, [
                    'user_id' => null,
                    'guest_email' => null,
                    'reason' => '[Personenbezogene Angaben entfernt]',
                    'resolution_note' => '[Personenbezogene Angaben entfernt]',
                ]);
            }
        }

        $this->record($summary, 'Warenkörbe, Lieferadressen, Merklisten und Bewertungen', $deleted);

        $retainedOrders = max(0, count($allOrderIds) - count($anonymizedOrderIds));
        if ($retainedOrders > 0) {
            $summary['retained'][] = "{$retainedOrders} Bestellvorgang/-vorgänge bleiben vorerst erhalten, weil sie einen Zahlungs-, Rechnungs-, Liefer- oder offenen Klärungsbezug haben können.";
        }

        $summary['retained'][] = 'Rechnungen, Zahlungen, Abonnements und andere gesetzlich oder vertraglich erforderliche Nachweise werden nicht durch diese Funktion gelöscht.';
    }

    /**
     * @return array<int, int>
     */
    private function anonymizableOrderIds(int $userId): array
    {
        if (! $this->hasTable('commerce_orders') || ! $this->hasColumn('commerce_orders', 'user_id')) {
            return [];
        }

        $query = DB::table('commerce_orders')->where('user_id', $userId);

        if ($this->hasColumn('commerce_orders', 'status')) {
            $query->whereIn('status', ['completed', 'cancelled', 'refunded', 'failed']);
        }

        // Nur Vorgänge ohne Rechnungs-/Zahlungsbeleg werden direkt anonymisiert. Bei
        // Belegen oder offenen Fällen muss zunächst die gesetzliche Aufbewahrung enden.
        foreach (['invoice_number', 'credit_note_number', 'payment_reference'] as $column) {
            if ($this->hasColumn('commerce_orders', $column)) {
                $query->whereNull($column);
            }
        }

        if ($this->hasColumn('commerce_orders', 'issue_status')) {
            $query->where(function ($query): void {
                $query->whereNull('issue_status')->orWhereIn('issue_status', ['none', 'resolved', 'closed']);
            });
        }

        return array_map('intval', $query->pluck('id')->all());
    }

    /**
     * @param  array<string, array<int, string>>  $storagePaths
     */
    private function deleteStoredPaths(array $storagePaths): void
    {
        foreach ($storagePaths as $disk => $paths) {
            $paths = array_values(array_unique($paths));

            if ($paths === []) {
                continue;
            }

            try {
                Storage::disk($disk)->delete($paths);
            } catch (\Throwable $exception) {
                Log::warning('A user data-erasure cleanup could not remove stored files.', [
                    'disk' => $disk,
                    'paths' => count($paths),
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, array<int, string>>  $storagePaths
     */
    private function rememberPath(array &$storagePaths, string $disk, ?string $path): void
    {
        $path = trim((string) $path);

        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        $storagePaths[$disk] ??= [];
        $storagePaths[$disk][] = $path;
    }

    /**
     * @param  array<int, int>  $ids
     * @param  array<string, array<int, string>>  $storagePaths
     */
    private function deleteFilesByIds(int $userId, array $ids, array &$storagePaths): int
    {
        if ($ids === [] || ! $this->hasTable('files')) {
            return 0;
        }

        $rows = DB::table('files')
            ->where('user_id', $userId)
            ->whereIn('id', $ids)
            ->get($this->existingColumns('files', ['id', 'path', 'thumbnail_path']));

        $ownedIds = $this->ids($rows);
        foreach ($rows as $file) {
            $this->rememberPath($storagePaths, UploadStorage::disk(), $file->path ?? null);
            $this->rememberPath($storagePaths, UploadStorage::disk(), $file->thumbnail_path ?? null);
        }

        return $this->deleteIn('files', 'id', $ownedIds);
    }

    /**
     * @param  array<int, int>  $parentIds
     * @return array<int, int>
     */
    private function attachedFileIds(string $table, string $parentColumn, array $parentIds, int $userId): array
    {
        if ($parentIds === []
            || ! $this->hasTable($table)
            || ! $this->hasColumn($table, $parentColumn)
            || ! $this->hasColumn($table, 'file_id')
            || ! $this->hasTable('files')
        ) {
            return [];
        }

        return array_map('intval', DB::table($table)
            ->join('files', 'files.id', '=', $table.'.file_id')
            ->whereIn($table.'.'.$parentColumn, $parentIds)
            ->where('files.user_id', $userId)
            ->pluck('files.id')
            ->all());
    }

    /**
     * @param  array<int, object>  $rows
     * @return array<int, int>
     */
    private function ids(array|Collection $rows): array
    {
        return collect($rows)
            ->pluck('id')
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $columns
     * @return Collection<int, object>
     */
    private function tableRowsForUser(string $table, int $userId, array $columns)
    {
        return $this->tableRowsForColumn($table, 'user_id', $userId, $columns);
    }

    /**
     * @param  array<int, string>  $columns
     * @return Collection<int, object>
     */
    private function tableRowsForColumn(string $table, string $column, int $value, array $columns)
    {
        if (! $this->hasTable($table) || ! $this->hasColumn($table, $column)) {
            return collect();
        }

        $columns = $this->existingColumns($table, $columns);

        return $columns === []
            ? collect()
            : DB::table($table)->where($column, $value)->get($columns);
    }

    /**
     * @param  array<int, string>  $columns
     * @return Collection<int, object>
     */
    private function tableRowsForColumnWithNull(string $table, string $column, int $value, string $nullColumn, array $columns)
    {
        if (! $this->hasTable($table)
            || ! $this->hasColumn($table, $column)
            || ! $this->hasColumn($table, $nullColumn)
        ) {
            return collect();
        }

        $columns = $this->existingColumns($table, $columns);

        return $columns === []
            ? collect()
            : DB::table($table)->where($column, $value)->whereNull($nullColumn)->get($columns);
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function deleteTablesForUser(int $userId, array $tables): int
    {
        return array_sum(array_map(fn (string $table) => $this->deleteForUser($table, $userId), $tables));
    }

    private function deleteForUser(string $table, int $userId): int
    {
        if (! $this->hasTable($table) || ! $this->hasColumn($table, 'user_id')) {
            return 0;
        }

        return DB::table($table)->where('user_id', $userId)->delete();
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function deleteWhereAny(string $table, array $columns, int $userId): int
    {
        $columns = array_values(array_filter($columns, fn (string $column) => $this->hasColumn($table, $column)));

        if (! $this->hasTable($table) || $columns === []) {
            return 0;
        }

        return DB::table($table)
            ->where(function ($query) use ($columns, $userId): void {
                foreach ($columns as $index => $column) {
                    $index === 0 ? $query->where($column, $userId) : $query->orWhere($column, $userId);
                }
            })
            ->delete();
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function deleteIn(string $table, string $column, array $ids): int
    {
        if ($ids === [] || ! $this->hasTable($table) || ! $this->hasColumn($table, $column)) {
            return 0;
        }

        return collect($ids)
            ->chunk(500)
            ->sum(fn ($chunk) => DB::table($table)->whereIn($column, $chunk->all())->delete());
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function deleteMorphedRows(string $table, string $typeColumn, string $idColumn, string $type, array $ids): int
    {
        if ($ids === []
            || ! $this->hasTable($table)
            || ! $this->hasColumn($table, $typeColumn)
            || ! $this->hasColumn($table, $idColumn)
        ) {
            return 0;
        }

        return collect($ids)
            ->chunk(500)
            ->sum(fn ($chunk) => DB::table($table)
                ->where($typeColumn, $type)
                ->whereIn($idColumn, $chunk->all())
                ->delete());
    }

    /**
     * @param  array<string, mixed>  $updates
     */
    private function updateForUser(string $table, int $userId, array $updates): int
    {
        if (! $this->hasTable($table) || ! $this->hasColumn($table, 'user_id')) {
            return 0;
        }

        return $this->updateQuery(DB::table($table)->where('user_id', $userId), $table, $updates);
    }

    /**
     * @param  array<int, int|string>  $ids
     * @param  array<string, mixed>  $updates
     */
    private function updateWhereIn(string $table, string $column, array $ids, array $updates): int
    {
        if ($ids === [] || ! $this->hasTable($table) || ! $this->hasColumn($table, $column)) {
            return 0;
        }

        return collect($ids)
            ->chunk(500)
            ->sum(fn ($chunk) => $this->updateQuery(
                DB::table($table)->whereIn($column, $chunk->all()),
                $table,
                $updates,
            ));
    }

    /**
     * @param  Builder  $query
     * @param  array<string, mixed>  $updates
     */
    private function updateQuery($query, string $table, array $updates): int
    {
        $updates = array_filter(
            $updates,
            fn ($value, string $column) => $this->hasColumn($table, $column),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($updates === []) {
            return 0;
        }

        if ($this->hasColumn($table, 'updated_at')) {
            $updates['updated_at'] = now();
        }

        return $query->update($updates);
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    private function existingColumns(string $table, array $columns): array
    {
        return array_values(array_filter($columns, fn (string $column) => $this->hasColumn($table, $column)));
    }

    private function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->hasTable($table) && Schema::hasColumn($table, $column);
    }

    /**
     * @param  array{categories: array<int, string>, deleted: array<string, int>, retained: array<int, string>}  $summary
     */
    private function record(array &$summary, string $label, int $count): void
    {
        if ($count > 0) {
            $summary['deleted'][$label] = ($summary['deleted'][$label] ?? 0) + $count;
        }
    }
}
