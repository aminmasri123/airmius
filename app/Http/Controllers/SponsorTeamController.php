<?php

namespace App\Http\Controllers;

use App\Models\Sponsor;
use App\Models\SponsorMembership;
use App\Services\SponsorOrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SponsorTeamController extends Controller
{
    public function __construct(private SponsorOrganizationService $organizations) {}

    public function invite(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'sponsor_id' => ['required', 'integer', 'exists:sponsors,id'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(array_diff(SponsorMembership::ROLES, [SponsorMembership::ROLE_OWNER]))],
        ]);
        $sponsor = Sponsor::query()->findOrFail($data['sponsor_id']);
        $membership = $this->organizations->invite($request->user(), $sponsor, $data['email'], $data['role']);

        return $this->response($request, 'Einladung wurde versendet.', ['id' => $membership->id]);
    }

    public function accept(Request $request, SponsorMembership $membership): JsonResponse|RedirectResponse
    {
        $this->organizations->answerInvitation($request->user(), $membership, true);

        return $this->response($request, 'Einladung wurde angenommen.');
    }

    public function decline(Request $request, SponsorMembership $membership): JsonResponse|RedirectResponse
    {
        $this->organizations->answerInvitation($request->user(), $membership, false);

        return $this->response($request, 'Einladung wurde abgelehnt.');
    }

    public function update(Request $request, SponsorMembership $membership): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(array_diff(SponsorMembership::ROLES, [SponsorMembership::ROLE_OWNER]))],
            'confirmed' => ['required', 'accepted'],
        ]);
        $this->organizations->updateRole($request->user(), $membership, $data['role']);

        return $this->response($request, 'Zugriffsrolle wurde aktualisiert.');
    }

    public function destroy(Request $request, SponsorMembership $membership): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['confirmed' => ['required', 'accepted']]);
        $this->organizations->remove($request->user(), $membership, (bool) $data['confirmed']);

        return $this->response($request, 'Mitarbeiterzugang wurde entfernt.');
    }

    public function transferOwnership(Request $request, SponsorMembership $membership): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['confirmed' => ['required', 'accepted']]);
        $this->organizations->transferOwnership($request->user(), $membership, (bool) $data['confirmed']);

        return $this->response($request, 'Die Inhaberschaft wurde übertragen. Dein Zugang bleibt als Administrator aktiv.');
    }

    private function response(Request $request, string $message, array $data = []): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'data' => $data]);
        }

        return back()->with('success', $message);
    }
}
