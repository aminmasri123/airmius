<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubYearPeriod;
use App\Services\ClubYearPeriodReportService;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubYearPeriodController extends Controller
{
    public function report(Request $request, Club $club, ClubYearPeriodReportService $reports)
    {
        $user = $request->user();
        abort_unless($user && ClubPermissions::allows($club, $user, ClubPermissions::FINANCE_VIEW), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(ClubYearPeriod::TYPES)],
            'period_id' => ['required'],
        ]);

        $period = null;
        if ((string) $data['period_id'] !== 'unassigned') {
            abort_unless(ctype_digit((string) $data['period_id']), 422);
            $period = $club->yearPeriods()
                ->whereKey((int) $data['period_id'])
                ->where('type', $data['type'])
                ->firstOrFail();
        }

        return response()->json(['data' => $reports->report($club, $data['type'], $period)]);
    }

    public function index(Request $request, Club $club)
    {
        $abilities = $this->authorizeView($request, $club);
        $periods = $club->yearPeriods()
            ->orderByRaw("CASE type WHEN 'business' THEN 1 WHEN 'contribution' THEN 2 ELSE 3 END")
            ->orderByDesc('starts_on')
            ->get();

        return response()->json(['data' => [
            'periods' => $periods->map(fn (ClubYearPeriod $period) => $this->payload($period)),
            'can_manage' => $abilities['edit'] || $abilities['delete'],
            'can_edit' => $abilities['edit'],
            'can_delete' => $abilities['delete'],
            'can_view_reports' => (bool) $request->user()
                && ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_VIEW),
        ]]);
    }

    public function store(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club, ClubPermissions::YEAR_PERIODS_EDIT);
        $period = $club->yearPeriods()->create($this->validatedData($request, $club));
        $this->audit($club, $request, 'club.year_period.created', $period);

        return response()->json(['data' => $this->payload($period)], 201);
    }

    public function update(Request $request, Club $club, ClubYearPeriod $yearPeriod)
    {
        $this->authorizePeriod($request, $club, $yearPeriod, ClubPermissions::YEAR_PERIODS_EDIT);
        $yearPeriod->update($this->validatedData($request, $club, $yearPeriod));
        $this->audit($club, $request, 'club.year_period.updated', $yearPeriod);

        return response()->json(['data' => $this->payload($yearPeriod->refresh())]);
    }

    public function destroy(Request $request, Club $club, ClubYearPeriod $yearPeriod)
    {
        $this->authorizePeriod($request, $club, $yearPeriod, ClubPermissions::YEAR_PERIODS_DELETE);
        if ($yearPeriod->isInUse()) {
            throw ValidationException::withMessages(['year_period' => __('validation.year_period_in_use')]);
        }
        $this->audit($club, $request, 'club.year_period.deleted', $yearPeriod);
        $yearPeriod->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /** @return array{edit: bool, delete: bool} */
    private function authorizeView(Request $request, Club $club): array
    {
        $user = $request->user();
        abort_unless($user && ClubPermissions::allows($club, $user, ClubPermissions::YEAR_PERIODS_VIEW), 403);

        return [
            'edit' => ClubPermissions::allows($club, $user, ClubPermissions::YEAR_PERIODS_EDIT),
            'delete' => ClubPermissions::allows($club, $user, ClubPermissions::YEAR_PERIODS_DELETE),
        ];
    }

    private function authorizeManage(Request $request, Club $club, string $permission): void
    {
        abort_unless($request->user() && ClubPermissions::allows($club, $request->user(), $permission), 403);
    }

    private function authorizePeriod(Request $request, Club $club, ClubYearPeriod $period, string $permission): void
    {
        $this->authorizeManage($request, $club, $permission);
        abort_unless((int) $period->club_id === (int) $club->id, 404);
    }

    private function validatedData(Request $request, Club $club, ?ClubYearPeriod $period = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'type' => ['required', Rule::in(ClubYearPeriod::TYPES)],
            'name' => [
                'required', 'string', 'max:160',
                Rule::unique('club_year_periods')->where(fn ($query) => $query
                    ->where('club_id', $club->id)
                    ->where('type', $request->input('type')))->ignore($period),
            ],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ]);

        $overlaps = $club->yearPeriods()
            ->where('type', $data['type'])
            ->when($period, fn ($query) => $query->whereKeyNot($period->id))
            ->whereDate('starts_on', '<=', $data['ends_on'])
            ->whereDate('ends_on', '>=', $data['starts_on'])
            ->exists();
        if ($overlaps) {
            throw ValidationException::withMessages(['starts_on' => __('validation.year_period_overlap')]);
        }

        return $data;
    }

    private function payload(ClubYearPeriod $period): array
    {
        $today = now()->startOfDay();
        $status = $today->lt($period->starts_on)
            ? 'upcoming'
            : ($today->gt($period->ends_on) ? 'past' : 'current');

        return [
            'id' => $period->id,
            'type' => $period->type,
            'name' => $period->name,
            'starts_on' => $period->starts_on->format('Y-m-d'),
            'ends_on' => $period->ends_on->format('Y-m-d'),
            'status' => $status,
        ];
    }

    private function audit(Club $club, Request $request, string $type, ClubYearPeriod $period): void
    {
        ClubAuditLog::record($club, $request->user(), $type, $period, ['entity_type' => 'year_period']);
    }
}
