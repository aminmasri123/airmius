<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class ProtectedDocumentDownload
{
    public const PURPOSE_CERTIFICATE = 'certificate_download';
    public const PURPOSE_INVOICE = 'invoice_download';
    public const PURPOSE_POLICY_DOCUMENT = 'policy_document_download';
    public const PURPOSE_CLUB_FILE = 'club_file_download';

    private const PURPOSES = [
        self::PURPOSE_CERTIFICATE,
        self::PURPOSE_INVOICE,
        self::PURPOSE_POLICY_DOCUMENT,
        self::PURPOSE_CLUB_FILE,
    ];

    public static function temporaryUrl(string $route, array $parameters, string $purpose, int $minutes = 5): string
    {
        self::assertKnownPurpose($purpose);

        return URL::temporarySignedRoute(
            $route,
            now()->addMinutes($minutes),
            array_merge($parameters, ['purpose' => $purpose]),
        );
    }

    public static function assertAuthorized(Request $request, string $purpose): void
    {
        self::assertKnownPurpose($purpose);

        abort_unless(
            $request->hasValidSignature() && $request->query('purpose') === $purpose,
            403,
        );
    }

    public static function audit(
        ?Club $club,
        ?User $actor,
        string $purpose,
        string $documentType,
        ?Model $subject = null,
        array $context = [],
    ): Activity {
        self::assertKnownPurpose($purpose);

        $payload = array_filter([
            'entity_type' => 'protected_document_download',
            'purpose' => $purpose,
            'document_type' => $documentType,
            'expires_at' => $context['expires_at'] ?? request()->query('expires'),
            'route' => $context['route'] ?? request()->route()?->getName(),
        ], fn ($value) => $value !== null && $value !== '');

        if ($club) {
            return ClubAuditLog::record($club, $actor, 'club.document.downloaded', $subject, $payload);
        }

        return Activity::query()->create([
            'user_id' => $actor?->id,
            'club_id' => null,
            'team_id' => null,
            'type' => 'document.downloaded',
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'data' => $payload,
        ]);
    }

    private static function assertKnownPurpose(string $purpose): void
    {
        if (! in_array($purpose, self::PURPOSES, true)) {
            throw ValidationException::withMessages(['purpose' => 'Unsupported document download purpose.']);
        }
    }
}
