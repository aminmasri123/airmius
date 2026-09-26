<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubAccessHandoverReview;
use App\Models\User;
use App\Services\ClubAccessHandoverService;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubAccessHandoverController extends Controller
{
    public function __construct(private readonly ClubAccessHandoverService $handovers) {}

    public function index(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'proposed', 'approved', 'applied', 'stale'])],
        ]);

        $reviews = ClubAccessHandoverReview::query()
            ->where('club_id', $club->id)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->with(['departingUser:id,name', 'successor:id,name', 'proposer:id,name', 'approver:id,name'])
            ->orderBy('due_on')
            ->orderBy('id')
            ->limit(200)
            ->get();

        return response()->json(['data' => [
            'reviews' => $reviews->map(fn (ClubAccessHandoverReview $review) => $this->payload($review))->values(),
            'eligible_successors' => $club->users()
                ->where(function ($query) {
                    $query->whereNull('club_user.membership_status')
                        ->orWhere('club_user.membership_status', 'active');
                })
                ->orderBy('users.name')
                ->get(['users.id', 'users.name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                ->values(),
        ]]);
    }

    public function propose(Request $request, Club $club, ClubAccessHandoverReview $review)
    {
        $this->authorizeReview($request, $club, $review);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['remove', 'assign_successor'])],
            'successor_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($data['decision'] === 'assign_successor' && empty($data['successor_user_id'])) {
            abort(422, __('validation.access_handover_successor'));
        }
        $successor = isset($data['successor_user_id'])
            ? User::query()->find((int) $data['successor_user_id'])
            : null;
        $review = $this->handovers->propose(
            $review,
            $request->user(),
            $data['decision'],
            $successor,
            $data['note'] ?? null,
        );

        return response()->json(['data' => $this->payload($review->load(['departingUser:id,name', 'successor:id,name', 'proposer:id,name', 'approver:id,name']))]);
    }

    public function approve(Request $request, Club $club, ClubAccessHandoverReview $review)
    {
        $this->authorizeReview($request, $club, $review);
        $review = $this->handovers->approve($review, $request->user());

        return response()->json(['data' => $this->payload($review->load(['departingUser:id,name', 'successor:id,name', 'proposer:id,name', 'approver:id,name']))]);
    }

    private function authorizeManage(Request $request, Club $club): void
    {
        abort_unless($request->user() instanceof User && ClubPermissions::editableBy($club, $request->user()), 403);
    }

    private function authorizeReview(Request $request, Club $club, ClubAccessHandoverReview $review): void
    {
        $this->authorizeManage($request, $club);
        abort_unless((int) $review->club_id === (int) $club->id, 404);
    }

    private function payload(ClubAccessHandoverReview $review): array
    {
        return [
            'id' => $review->id,
            'status' => $review->status,
            'decision' => $review->decision,
            'due_on' => $review->due_on?->toDateString(),
            'assignment_count' => count($review->assignment_snapshot ?? []),
            'delegation_count' => $review->delegation_count,
            'inventory_loan_count' => count($review->inventory_loan_snapshot ?? []),
            'inventory_loans' => $review->inventory_loan_snapshot ?? [],
            'departing_user' => $this->person($review->departingUser),
            'successor' => $this->person($review->successor),
            'proposer' => $this->person($review->proposer),
            'approver' => $this->person($review->approver),
            'proposal_note' => $review->proposal_note,
            'proposed_at' => $review->proposed_at?->toJSON(),
            'approved_at' => $review->approved_at?->toJSON(),
            'applied_at' => $review->applied_at?->toJSON(),
        ];
    }

    private function person(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name] : null;
    }
}
