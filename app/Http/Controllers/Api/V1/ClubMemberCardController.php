<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubMemberCardToken;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Support\ClubPermissions;
use App\Support\ClubRoles;
use App\Support\EventAttendance;
use App\Support\UploadStorage;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClubMemberCardController extends Controller
{
    private const TOKEN_TTL_MINUTES = 10;

    public function show(Request $request, Club $club)
    {
        $member = $this->activeMember($request, $club);
        $token = $this->rotateToken($club, (int) $request->user()->id);

        return response()->json([
            'data' => $this->cardPayload($club, $member, $token),
        ]);
    }

    public function rotate(Request $request, Club $club)
    {
        return $this->show($request, $club);
    }

    public function updateDesign(Request $request, Club $club): JsonResponse
    {
        $member = $this->activeMember($request, $club);
        $data = $request->validate([
            'accent_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'style' => ['required', 'string', Rule::in(['classic', 'sport', 'minimal'])],
            'show_profile_photo' => ['required', 'boolean'],
            'show_member_number' => ['required', 'boolean'],
        ]);

        DB::table('club_user')
            ->where('club_id', $club->id)
            ->where('user_id', $request->user()->id)
            ->update([
                'member_card_design' => json_encode($this->normalizeDesign($data)),
                'updated_at' => now(),
            ]);

        $member->pivot->member_card_design = $this->normalizeDesign($data);
        $token = $this->rotateToken($club, (int) $request->user()->id);

        return response()->json([
            'data' => $this->cardPayload($club, $member, $token),
        ]);
    }

    public function verify(Request $request, Club $club)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:24', 'max:160'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
        ]);

        $event = null;
        if (! empty($data['event_id'])) {
            $event = Event::query()->with('team')->findOrFail((int) $data['event_id']);
            abort_unless((int) $event->resolvedClub()?->id === (int) $club->id, 404);
            abort_unless(EventAttendance::canManage($request->user(), $event), 403);
        } else {
            $isOwner = (int) $club->owner_id === (int) $request->user()->id;
            abort_unless(
                $isOwner
                    || ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_MANAGE)
                    || ClubPermissions::allows($club, $request->user(), ClubPermissions::EVENTS_EDIT),
                403
            );
        }

        $cardToken = ClubMemberCardToken::query()
            ->with('user:id,name,email')
            ->where('club_id', $club->id)
            ->where('token_hash', hash('sha256', $data['token']))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        abort_unless($cardToken, 422, 'Der Mitgliedskarten-Code ist ungültig oder abgelaufen.');

        $member = $this->activeMemberForUser($club, (int) $cardToken->user_id);
        abort_unless($member, 422, 'Die Mitgliedschaft ist nicht aktiv.');

        $checkIn = null;
        if ($event) {
            $checkIn = EventParticipant::query()->updateOrCreate(
                [
                    'event_id' => $event->id,
                    'user_id' => $cardToken->user_id,
                ],
                [
                    'status' => 'yes',
                    'response_mode' => 'member_card',
                    'responded_at' => now(),
                    'checked_in_at' => now(),
                    'check_in_method' => 'member_card',
                ],
            );
        }

        $cardToken->forceFill(['last_used_at' => now()])->save();

        return response()->json([
            'data' => [
                'verified' => true,
                'member' => [
                    'id' => $cardToken->user->id,
                    'name' => $cardToken->user->name,
                    'member_number' => $member->pivot?->member_number,
                    'role' => ClubRoles::primary(ClubRoles::normalize($member->pivot?->role, $member->pivot?->roles ?? [])),
                    'membership_status' => $member->pivot?->membership_status,
                ],
                'event' => $event ? [
                    'id' => $event->id,
                    'title' => $event->title,
                    'checked_in_at' => $checkIn?->checked_in_at?->toIso8601String(),
                ] : null,
                'token_expires_at' => $cardToken->expires_at->toIso8601String(),
            ],
        ]);
    }

    private function activeMember(Request $request, Club $club)
    {
        $member = $club->users()->where('users.id', $request->user()->id)->first();
        abort_unless($member && $this->memberIsActive($club, $member), 403);

        return $member;
    }

    private function activeMemberForUser(Club $club, int $userId)
    {
        $member = $club->users()->where('users.id', $userId)->first();

        return $member && $this->memberIsActive($club, $member) ? $member : null;
    }

    private function memberIsActive(Club $club, $member): bool
    {
        $roles = ClubRoles::normalize($member->pivot?->role, $member->pivot?->roles ?? []);
        $status = (string) ($member->pivot?->membership_status ?? '');

        return $status === 'active'
            || ($status === '' && $club->owner_id === $member->id)
            || ($status === '' && array_intersect($roles, ClubRoles::ELEVATED) !== []);
    }

    private function rotateToken(Club $club, int $userId): ClubMemberCardToken
    {
        return DB::transaction(function () use ($club, $userId): ClubMemberCardToken {
            ClubMemberCardToken::query()
                ->where('club_id', $club->id)
                ->where('user_id', $userId)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return ClubMemberCardToken::query()->create([
                'club_id' => $club->id,
                'user_id' => $userId,
                'token_hash' => hash('sha256', $plain = bin2hex(random_bytes(32))),
                'expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
            ])->setAttribute('plain_token', $plain);
        });
    }

    private function cardPayload(Club $club, $member, ClubMemberCardToken $token): array
    {
        $plainToken = (string) $token->getAttribute('plain_token');
        $qrSvg = (new Writer(new ImageRenderer(
            new RendererStyle(256, 4),
            new SvgImageBackEnd,
        )))->writeString($plainToken);

        return [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'logo_url' => UploadStorage::url($club->logo),
            ],
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'member_number' => $member->pivot?->member_number,
                'role' => ClubRoles::primary(ClubRoles::normalize($member->pivot?->role, $member->pivot?->roles ?? [])),
                'membership_status' => $member->pivot?->membership_status ?: 'active',
            ],
            'design' => $this->normalizeDesign($member->pivot?->member_card_design),
            'token' => [
                'value' => $plainToken,
                'expires_at' => $token->expires_at->toIso8601String(),
                'expires_in_seconds' => max(0, now()->diffInSeconds($token->expires_at, false)),
                'qr_svg_data_uri' => 'data:image/svg+xml;base64,'.base64_encode($qrSvg),
            ],
            'visible_claims' => ['name', 'club', 'member_number', 'role', 'membership_status', 'expires_at'],
            'hidden_claims' => ['email', 'address', 'payment_status', 'guardian_contact', 'medical_notes'],
        ];
    }

    private function normalizeDesign(mixed $design): array
    {
        $design = is_array($design) ? $design : [];

        return [
            'accent_color' => $this->hexColor($design['accent_color'] ?? null, '#60A5FA'),
            'background_color' => $this->hexColor($design['background_color'] ?? null, '#17253A'),
            'text_color' => $this->hexColor($design['text_color'] ?? null, '#FFFFFF'),
            'style' => in_array($design['style'] ?? null, ['classic', 'sport', 'minimal'], true)
                ? $design['style']
                : 'classic',
            'show_profile_photo' => array_key_exists('show_profile_photo', $design)
                ? (bool) $design['show_profile_photo']
                : true,
            'show_member_number' => array_key_exists('show_member_number', $design)
                ? (bool) $design['show_member_number']
                : true,
        ];
    }

    private function hexColor(mixed $value, string $fallback): string
    {
        $value = is_string($value) ? strtoupper($value) : '';

        return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : $fallback;
    }
}
