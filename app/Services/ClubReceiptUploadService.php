<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubFinanceEntry;
use App\Models\ClubReceiptUpload;
use App\Models\File;
use App\Models\User;
use App\Support\ClubAuditLog;
use App\Support\UploadStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClubReceiptUploadService
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    private const DISALLOWED_EXTENSIONS = [
        'bat', 'cmd', 'com', 'dll', 'exe', 'hta', 'jar', 'js', 'msi', 'php', 'phtml',
        'ps1', 'scr', 'sh', 'svg', 'vbs', 'wsf',
    ];

    public function store(Club $club, User $user, UploadedFile $file): ClubReceiptUpload
    {
        $scan = $this->scan($file);
        $sha256 = hash_file('sha256', $file->getRealPath());

        return DB::transaction(function () use ($club, $user, $file, $scan, $sha256): ClubReceiptUpload {
            $duplicate = ClubReceiptUpload::query()
                ->where('club_id', $club->id)
                ->where('sha256', $sha256)
                ->with(['file', 'financeEntry'])
                ->lockForUpdate()
                ->first();

            if ($duplicate) {
                return $duplicate;
            }

            $storedPath = $file->storeAs(
                'clubs/'.$club->id.'/receipts/'.now()->format('Y/m'),
                Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension() ?: 'bin'),
                UploadStorage::disk(),
            );

            $stored = File::create([
                'club_id' => $club->id,
                'user_id' => $user->id,
                'display_name' => $this->safeDisplayName($file->getClientOriginalName()),
                'path' => $storedPath,
                'type' => $scan['mime_type'],
                'size' => $scan['size_bytes'],
            ]);

            $receipt = ClubReceiptUpload::create([
                'club_id' => $club->id,
                'uploaded_by' => $user->id,
                'file_id' => $stored->id,
                'status' => ClubReceiptUpload::STATUS_PENDING,
                'scan_status' => 'clean',
                'sha256' => $sha256,
                'mime_type' => $scan['mime_type'],
                'size_bytes' => $scan['size_bytes'],
                'ocr_suggestion' => $this->suggestFromFileName($file->getClientOriginalName()),
            ])->load(['file', 'financeEntry']);

            ClubAuditLog::record($club, $user, 'club.receipt.uploaded', $receipt, [
                'receipt_upload_id' => $receipt->id,
                'file_id' => $stored->id,
                'scan_status' => 'clean',
                'mime_type' => $scan['mime_type'],
                'size_bytes' => $scan['size_bytes'],
            ]);

            return $receipt;
        });
    }

    public function confirm(ClubReceiptUpload $receipt, User $user, array $data): ClubReceiptUpload
    {
        return DB::transaction(function () use ($receipt, $user, $data): ClubReceiptUpload {
            $locked = ClubReceiptUpload::query()
                ->whereKey($receipt->id)
                ->with(['club', 'file', 'financeEntry'])
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->club_id !== (int) $data['club_id']) {
                abort(404);
            }

            if ($locked->status === ClubReceiptUpload::STATUS_CONFIRMED) {
                return $locked;
            }

            $entry = null;

            if (! empty($data['finance_entry_id'])) {
                $entry = ClubFinanceEntry::query()
                    ->where('club_id', $locked->club_id)
                    ->lockForUpdate()
                    ->findOrFail($data['finance_entry_id']);
                $entry->forceFill(['receipt_file_id' => $locked->file_id])->save();
            } else {
                $entry = ClubFinanceEntry::create([
                    'club_id' => $locked->club_id,
                    'user_id' => $user->id,
                    'receipt_file_id' => $locked->file_id,
                    'type' => $data['type'],
                    'account' => $data['account'],
                    'category' => $data['category'] ?? null,
                    'title' => $data['title'],
                    'amount' => $data['amount'],
                    'booked_on' => $data['booked_on'],
                    'reference' => $data['reference'] ?? null,
                    'description' => $data['description'] ?? null,
                ]);
            }

            $locked->forceFill([
                'club_finance_entry_id' => $entry->id,
                'status' => ClubReceiptUpload::STATUS_CONFIRMED,
                'confirmed_payload' => collect($data)
                    ->except(['club_id'])
                    ->all(),
                'confirmed_at' => now(),
            ])->save();

            ClubAuditLog::record($locked->club, $user, 'club.receipt.confirmed', $locked, [
                'receipt_upload_id' => $locked->id,
                'file_id' => $locked->file_id,
                'finance_entry_id' => $entry->id,
            ]);

            return $locked->refresh()->load(['file', 'financeEntry']);
        });
    }

    private function scan(UploadedFile $file): array
    {
        if (! $file->isValid() || (int) $file->getSize() <= 0) {
            throw ValidationException::withMessages(['file' => 'Die Datei ist ungültig oder wurde fehlerhaft übertragen.']);
        }

        $name = $file->getClientOriginalName();
        $extensionParts = array_filter(explode('.', strtolower($name)));

        foreach ($extensionParts as $part) {
            if (in_array($part, self::DISALLOWED_EXTENSIONS, true)) {
                throw ValidationException::withMessages(['file' => 'Dieser Beleg-Dateityp ist aus Sicherheitsgründen nicht erlaubt.']);
            }
        }

        $mimeTypes = array_filter([
            strtolower((string) $file->getClientMimeType()),
            strtolower((string) $file->getMimeType()),
        ]);

        if ($mimeTypes === [] || collect($mimeTypes)->contains(fn (string $mime): bool => ! in_array($mime, self::ALLOWED_MIME_TYPES, true))) {
            throw ValidationException::withMessages(['file' => 'Nur PDF-, JPG-, PNG- und WebP-Belege sind erlaubt.']);
        }

        $sample = file_get_contents($file->getRealPath(), false, null, 0, 8192) ?: '';
        if (str_contains($sample, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE') || preg_match('/<script\b/i', $sample)) {
            throw ValidationException::withMessages(['file' => 'Die Sicherheitsprüfung hat die Datei abgelehnt.']);
        }

        return [
            'mime_type' => $mimeTypes[0],
            'size_bytes' => (int) $file->getSize(),
        ];
    }

    private function suggestFromFileName(string $name): array
    {
        $basename = pathinfo($name, PATHINFO_FILENAME) ?: $name;
        $suggestion = [
            'title' => trim(str_replace(['_', '-'], ' ', $basename)) ?: 'Beleg',
            'confidence' => 35,
            'source' => 'filename_ocr_fallback',
            'requires_manual_confirmation' => true,
        ];

        if (preg_match('/(?<!\d)(\d{1,6})[,.](\d{2})(?!\d)/', $basename, $amount)) {
            $suggestion['amount'] = $amount[1].'.'.$amount[2];
        }

        if (preg_match('/(20\d{2})[-_.](\d{2})[-_.](\d{2})/', $basename, $date)) {
            $suggestion['booked_on'] = $date[1].'-'.$date[2].'-'.$date[3];
        }

        return $suggestion;
    }

    private function safeDisplayName(string $name): string
    {
        $clean = trim(str_replace(["\0", '/', '\\'], '', $name));

        return $clean !== '' ? mb_substr($clean, 0, 180) : 'Beleg';
    }
}
