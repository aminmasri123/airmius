<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Services\ClubDeletionIdentityService;
use App\Services\ClubDeletionService;
use App\Services\ClubFinancialClosingExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use ZipArchive;

class ClubDeletionController extends Controller
{
    public function settings(Club $club): Response
    {
        Gate::authorize('delete', $club);
        abort_unless((int) $club->owner_id === (int) auth()->id(), 403);

        return Inertia::render('Auth/Dashboard/Clubs/DeletionSettings', [
            'club' => ['id' => $club->id, 'name' => $club->name],
        ]);
    }

    public function export(Club $club, ClubFinancialClosingExportService $export)
    {
        Gate::authorize('delete', $club);
        abort_unless((int) $club->owner_id === (int) auth()->id(), 403);

        $path = null;
        try {
            [$path, $complete] = DB::transaction(function () use ($club, $export, &$path): array {
                $club = Club::query()->lockForUpdate()->findOrFail($club->id);
                Gate::authorize('delete', $club);
                abort_unless((int) $club->owner_id === (int) auth()->id(), 403);
                try {
                    $before = $export->fingerprint($club);
                } catch (RuntimeException) {
                    $before = null;
                }
                $path = $export->create($club);
                $zip = new ZipArchive;
                if ($zip->open($path) !== true) {
                    throw new RuntimeException('Could not inspect financial export.');
                }
                $manifest = json_decode($zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
                $zip->close();
                $complete = $manifest['missing_receipt_file_ids'] === [];
                if ($complete) {
                    if ($before === null) {
                        throw new RuntimeException('Financial export changed during creation.');
                    }
                    app(ClubDeletionService::class)->recordFinancialExport($club, auth()->user(), $before);
                }

                return [$path, $complete];
            });
        } catch (\Throwable $exception) {
            if ($path) {
                @unlink($path);
            }
            throw $exception;
        }

        return response()->download(
            $path,
            'airmius-verein-'.$club->id.'-finanzdaten-'.now()->format('Ymd-His').'.zip',
            [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'private, no-store',
                'X-Airmius-Export-Complete' => $complete ? 'true' : 'false',
            ],
        )->deleteFileAfterSend(true);
    }

    public function show(Request $request, Club $club, ClubDeletionService $service)
    {
        Gate::authorize('delete', $club);
        abort_unless((int) $club->owner_id === (int) $request->user()->id, 403);

        return response()->json(['data' => $service->status($club, $request->user())]);
    }

    public function sendCode(Request $request, Club $club, ClubDeletionIdentityService $identity)
    {
        $data = $request->validate(['identity' => ['required', 'string', 'max:255']]);
        $identity->sendCode($club, $request->user(), $data['identity']);

        return response()->json(['message' => __('club_deletion.code_sent')]);
    }

    public function requestReview(Request $request, Club $club, ClubDeletionService $service)
    {
        $ticket = $service->requestReview($club, $request->user());

        return response()->json([
            'message' => __('club_deletion.review_requested'),
            'data' => $service->status($club, $request->user()),
            'ticket_id' => $ticket->id,
        ]);
    }

    public function confirmArchive(Request $request, Club $club, ClubDeletionService $service)
    {
        $request->validate(['archive_confirmed' => ['required', 'accepted']]);
        $service->confirmExternalArchive($club, $request->user());

        return response()->json(['data' => $service->status($club, $request->user())]);
    }

    public function decideReview(Request $request, Club $club, ClubDeletionService $service)
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['required', 'string', 'min:20', 'max:2000'],
            'reviewed_contract_and_retention' => ['required', 'accepted'],
            'reviewed_authority' => ['required', 'accepted'],
        ]);
        $service->decideFinancialReview($club, $request->user(), $data['decision'] === 'approve', $data['note']);

        return response()->json(['data' => $service->status($club)]);
    }

    public function reviewStatus(Request $request, Club $club, ClubDeletionService $service)
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);

        return response()->json(['data' => $service->status($club)]);
    }

    public function requestDeletion(Request $request, Club $club, ClubDeletionService $service)
    {
        $data = $request->validate([
            'confirmation' => ['required', 'string', 'max:100'],
            'code' => ['required', 'digits:6'],
        ]);
        $club = $service->request($club, $request->user(), $data['confirmation'], $data['code']);

        return response()->json([
            'message' => __('club_deletion.requested_title'),
            'data' => $service->status($club, $request->user()),
        ], 202);
    }

    public function destroy(Request $request, Club $club, ClubDeletionService $service)
    {
        $club = $service->cancel($club, $request->user());

        return response()->json(['data' => $service->status($club, $request->user()), 'message' => __('club_deletion.cancelled_title')]);
    }
}
