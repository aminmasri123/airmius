<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

class DeletionRecoveryLedger
{
    public function enabled(): bool
    {
        return (bool) config('airmius_backup.deletion_ledger_enabled');
    }

    public function prepare(string $subject, int $id, ?string $createdAt): ?string
    {
        if (! $this->enabled()) {
            return null;
        }
        if (! in_array($subject, ['club', 'user'], true) || $id < 1) {
            throw new RuntimeException('Invalid deletion recovery subject.');
        }
        $operationId = (string) Str::uuid();
        $this->write('pending', $operationId, [
            'operation_id' => $operationId,
            'subject' => $subject,
            'id' => $id,
            'created_at' => $createdAt,
            'prepared_at' => now()->toIso8601String(),
        ]);

        return $operationId;
    }

    public function complete(?string $operationId): void
    {
        if ($operationId === null) {
            return;
        }
        $pending = $this->read('pending', $operationId);
        $this->write('completed', $operationId, [
            'operation_id' => $operationId,
            'subject' => $pending['subject'],
            'id' => $pending['id'],
            'created_at' => $pending['created_at'],
            'completed_at' => now()->toIso8601String(),
        ]);
    }

    public function verifySqlite(string $path): void
    {
        $pdo = new PDO('sqlite:'.$path);
        $this->verify($pdo);
    }

    public function verify(PDO $pdo): void
    {
        if (! $this->enabled()) {
            return;
        }
        $disk = Storage::disk((string) config('airmius_backup.deletion_ledger_disk'));
        foreach ($disk->files($this->path('pending')) as $path) {
            $operationId = basename($path, '.json');
            $pending = $this->read('pending', $operationId);
            $completed = $this->read('completed', $operationId);
            foreach (['operation_id', 'subject', 'id', 'created_at'] as $field) {
                if (($completed[$field] ?? null) !== ($pending[$field] ?? null)) {
                    throw new RuntimeException('Deletion recovery records disagree for '.$operationId.'.');
                }
            }
            $table = match ($pending['subject'] ?? null) {
                'club' => 'clubs',
                'user' => 'users',
                default => throw new RuntimeException('Unknown deletion recovery subject.'),
            };
            $query = $pdo->prepare("SELECT created_at FROM {$table} WHERE id = ?");
            $query->execute([(int) $pending['id']]);
            $createdAt = $query->fetchColumn();
            if ($createdAt !== false && ($pending['created_at'] === null || $createdAt === $pending['created_at'])) {
                throw new RuntimeException("Restored {$pending['subject']} {$pending['id']} was previously deleted. Reconcile before cutover.");
            }
        }
    }

    private function write(string $stage, string $operationId, array $payload): void
    {
        $content = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $envelope = json_encode([
            'payload' => $payload,
            'mac' => hash_hmac('sha256', $content, $this->signingKey()),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (! Storage::disk((string) config('airmius_backup.deletion_ledger_disk'))
            ->put($this->path($stage).'/'.$operationId.'.json', $envelope, ['visibility' => 'private'])) {
            throw new RuntimeException('Could not write deletion recovery record.');
        }
    }

    private function read(string $stage, string $operationId): array
    {
        if (! Str::isUuid($operationId)) {
            throw new RuntimeException('Invalid deletion recovery record identifier.');
        }
        $disk = Storage::disk((string) config('airmius_backup.deletion_ledger_disk'));
        $path = $this->path($stage).'/'.$operationId.'.json';
        if (! $disk->exists($path)) {
            throw new RuntimeException("Deletion recovery {$stage} record missing for {$operationId}.");
        }
        $envelope = json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR);
        $payload = $envelope['payload'] ?? null;
        $mac = $envelope['mac'] ?? null;
        if (! is_array($payload) || ! is_string($mac) || ! hash_equals(
            hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $this->signingKey()),
            $mac,
        ) || ($payload['operation_id'] ?? null) !== $operationId) {
            throw new RuntimeException('Deletion recovery record integrity failed.');
        }

        return $payload;
    }

    private function path(string $stage): string
    {
        return trim((string) config('airmius_backup.deletion_ledger_path'), '/').'/'.$stage;
    }

    private function signingKey(): string
    {
        $key = (string) config('airmius_backup.deletion_ledger_signing_key');
        if ($key === '') {
            throw new RuntimeException('Deletion recovery signing key is missing.');
        }

        return $key;
    }
}
