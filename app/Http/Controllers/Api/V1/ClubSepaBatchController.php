<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubFinanceEntry;
use App\Models\ClubSepaBatch;
use App\Models\ClubSepaBatchItem;
use App\Models\ClubSepaFeeCorrection;
use App\Models\ClubSepaFeeRecharge;
use App\Models\ClubSepaFeeRechargeCredit;
use App\Models\ClubSepaFeeRechargeVoid;
use App\Models\ClubSepaSettlement;
use App\Services\ClubSepaBatchService;
use App\Services\ClubSepaFeeRechargeCreditDocumentService;
use App\Services\ClubSepaFeeRechargeService;
use App\Services\ClubSepaFeeService;
use App\Services\ClubSepaNoticeService;
use App\Services\ClubSepaReturnImportService;
use App\Services\ClubSepaSettlementService;
use App\Services\PlanFeatureService;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class ClubSepaBatchController extends Controller
{
    public function index(Request $request, Club $club)
    {
        $this->access($request, $club, false);

        $manage = ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_MANAGE);
        $noticesAvailable = Schema::hasTable('club_sepa_notices');
        $resultsAvailable = Schema::hasTable('club_sepa_settlements');
        $feesAvailable = $resultsAvailable && Schema::hasColumn('club_sepa_settlements', 'fee_finance_entry_id');
        $correctionsAvailable = $feesAvailable && Schema::hasTable('club_sepa_fee_corrections');
        $rechargesAvailable = $correctionsAvailable && Schema::hasTable('club_sepa_fee_recharges');
        $voidsAvailable = $rechargesAvailable && Schema::hasColumn('club_sepa_fee_recharges', 'invoice_id') && Schema::hasTable('club_sepa_fee_recharge_voids');
        $creditsAvailable = $voidsAvailable && Schema::hasTable('club_sepa_fee_recharge_credits');
        $relations = ['items'];
        if ($creditsAvailable) {
            $relations[] = 'items.settlement.feeRecharges.creditRequests';
        }
        if ($voidsAvailable) {
            $relations[] = 'items.settlement.feeRecharges.voidRequests';
        }
        if ($rechargesAvailable) {
            $relations[] = 'items.settlement.feeRecharges.member:id,name';
            if (Schema::hasColumn('club_sepa_fee_recharges', 'invoice_id')) {
                $relations[] = 'items.settlement.feeRecharges.invoice:id,number,status,amount,due_date';
            }
        }
        if ($correctionsAvailable) {
            $relations[] = 'items.settlement.feeCorrections';
        }
        if ($noticesAvailable) {
            $relations[] = 'notices';
        }
        if ($resultsAvailable) {
            $relations = array_merge($relations, ['items.settlement', 'items.invoice.payments', 'items.invoice' => fn ($query) => $query->withSum('settledPayments', 'amount')]);
        }
        if ($feesAvailable) {
            $relations[] = 'items.settlement.feeEntry:id,club_id,type,account,amount,booked_on,reference';
        }

        return response()->json(['can_manage' => $manage, 'notices_available' => $noticesAvailable, 'results_available' => $resultsAvailable, 'fees_available' => $feesAvailable, 'fee_corrections_available' => $correctionsAvailable, 'fee_recharge_drafts_available' => $rechargesAvailable, 'fee_recharge_voids_available' => $voidsAvailable, 'fee_recharge_credits_available' => $creditsAvailable, 'fee_recharge_approvals_available' => $rechargesAvailable && Schema::hasColumn('club_sepa_fee_recharges', 'invoice_id'),
            'data' => ClubSepaBatch::where('club_id', $club->id)->with($relations)->latest('id')
                ->paginate(25)->through(fn ($batch) => $this->payload($batch, $manage))])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Club $club, ClubSepaBatchService $service)
    {
        $this->access($request, $club);
        $data = $request->validate([
            'invoice_ids' => ['required', 'array', 'min:1', 'max:200'],
            'invoice_ids.*' => ['required', 'integer', 'distinct'],
            'collection_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'notice_days' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        return response()->json(['data' => $this->payload($service->create($club, $request->user(), $data))], 201);
    }

    public function approve(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchService $service)
    {
        $this->access($request, $club, true, $batch, ClubPermissions::FINANCE_APPROVE);

        return response()->json(['data' => $this->payload($service->approve($batch, $request->user()))]);
    }

    public function notice(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchService $service)
    {
        $this->access($request, $club, true, $batch);
        $data = $request->validate([
            'confirmed' => ['required', 'accepted'],
            'sent_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'channel' => ['required', Rule::in(['email', 'letter', 'portal'])],
            'reference' => ['required', 'string', 'max:2000', 'regex:/\S/u'],
        ]);

        return response()->json(['data' => $this->payload($service->recordNotice($batch, $request->user(), $data))]);
    }

    public function prepareNotices(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaNoticeService $service)
    {
        $this->access($request, $club, true, $batch);
        abort_unless(Schema::hasTable('club_sepa_notices'), 503, __('sepa.unavailable'));

        return response()->json(['data' => $this->payload($service->prepare($batch, $request->user()), true)])->header('Cache-Control', 'private, no-store');
    }

    public function sendNotices(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaNoticeService $service)
    {
        $this->access($request, $club, true, $batch);
        abort_unless(Schema::hasTable('club_sepa_notices'), 503, __('sepa.unavailable'));
        $request->validate(['confirmed' => ['required', 'accepted']]);

        return response()->json(['data' => $this->payload($service->enqueue($batch, $request->user()), true)], 202)->header('Cache-Control', 'private, no-store');
    }

    public function cancel(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchService $service)
    {
        $this->access($request, $club, true, $batch);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000', 'regex:/\S/u']]);

        return response()->json(['data' => $this->payload($service->cancel($batch, $request->user(), $data['reason']))]);
    }

    public function export(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchService $service)
    {
        $this->access($request, $club, true, $batch, ClubPermissions::FINANCE_EXPORT);

        return response($service->export($batch, $request->user()), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$batch->reference.'.xml"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function settleItem(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item, ClubSepaSettlementService $service)
    {
        $this->resultAccess($request, $club, $batch, $item);
        $data = $request->validate([
            'confirmed' => ['required', 'accepted'], 'booked_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['required', 'string', 'max:180', 'regex:/\S/u'], 'payment_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json(['data' => $service->settle($item, $request->user(), $data)])->header('Cache-Control', 'private, no-store');
    }

    public function returnColumns(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaReturnImportService $service)
    {
        $this->access($request, $club, true, $batch);
        abort_unless(Schema::hasTable('club_sepa_settlements'), 503, __('sepa.unavailable'));
        $request->validate(['file' => ['required', 'file', 'max:2048']]);

        return response()->json(['data' => $service->columns($batch, $request->file('file')->getRealPath())])->header('Cache-Control', 'private, no-store');
    }

    public function previewReturns(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaReturnImportService $service)
    {
        $this->access($request, $club, true, $batch);
        abort_unless(Schema::hasTable('club_sepa_settlements'), 503, __('sepa.unavailable'));
        $request->validate([
            'file' => ['required', 'file', 'max:2048'],
            'mapping' => ['sometimes', 'array:end_to_end_id,booking_date,amount,currency,reference,reason,iban'],
            'mapping.*' => ['required', 'integer', 'min:0', 'max:49'],
            'ignored_columns' => ['sometimes', 'array', 'max:50'],
            'ignored_columns.*' => ['required', 'integer', 'min:0', 'max:49'],
        ]);

        return response()->json(['data' => $service->preview($batch, $request->user(), $request->file('file')->getRealPath(), $request->input('mapping'), $request->input('ignored_columns', []))])->header('Cache-Control', 'private, no-store');
    }

    public function importReturns(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaReturnImportService $service)
    {
        $this->access($request, $club, true, $batch);
        abort_unless(Schema::hasTable('club_sepa_settlements'), 503, __('sepa.unavailable'));
        $request->validate([
            'file' => ['required', 'file', 'max:2048'], 'preview_token' => ['required', 'string', 'max:4096'],
            'confirmed' => ['required', 'accepted'], 'confirm_unlinked' => ['sometimes', 'boolean'],
            'mapping' => ['prohibited'], 'ignored_columns' => ['prohibited'],
        ]);

        return response()->json(['data' => $service->import($batch, $request->user(), $request->file('file')->getRealPath(),
            $request->string('preview_token')->toString(), $request->boolean('confirm_unlinked'))])->header('Cache-Control', 'private, no-store');
    }

    public function returnItem(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item, ClubSepaSettlementService $service)
    {
        $this->resultAccess($request, $club, $batch, $item);
        $data = $request->validate([
            'confirmed' => ['required', 'accepted'], 'booked_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['required', 'string', 'max:180', 'regex:/\S/u'], 'reason' => ['required', 'string', 'max:2000', 'regex:/\S/u'],
            'fee_cents' => ['nullable', 'integer', 'min:0', 'max:99999999'],
        ]);

        return response()->json(['data' => $service->returnDebit($item, $request->user(), $data)])->header('Cache-Control', 'private, no-store');
    }

    public function feeOptions(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item)
    {
        $this->resultAccess($request, $club, $batch, $item);
        abort_unless(Schema::hasColumn('club_sepa_settlements', 'fee_finance_entry_id'), 503, __('sepa.unavailable'));
        app(PlanFeatureService::class)->ensureAllows($club, 'payment_tracking');
        $result = $item->settlement;
        abort_unless($batch->status === 'exported' && $result?->status === 'returned', 422, __('sepa.return_required'));
        $data = $request->validate(['q' => ['nullable', 'string', 'max:180'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $query = ClubFinanceEntry::where('club_id', $club->id)->where('type', 'expense')->where('account', 'bank')
            ->whereDate('booked_on', '>=', $result->returned_on->toDateString())->whereDate('booked_on', '<=', today()->toDateString())
            ->whereNotNull('reference')->where('reference', '!=', '')
            ->whereNotIn('id', ClubSepaSettlement::whereNotNull('fee_finance_entry_id')->select('fee_finance_entry_id'));
        if (Schema::hasTable('club_sepa_fee_corrections')) {
            $query->whereNotIn('id', ClubSepaFeeCorrection::select('finance_entry_id'));
        }
        if (filled($data['q'] ?? null)) {
            $query->where('reference', 'like', '%'.trim($data['q']).'%');
        }

        return response()->json(['data' => $query->select(['id', 'amount', 'booked_on', 'reference'])
            ->orderByDesc('id')->paginate(25)])->header('Cache-Control', 'private, no-store');
    }

    public function proposeFeeRecharge(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item, ClubSepaFeeRechargeService $service)
    {
        $this->resultAccess($request, $club, $batch, $item);
        abort_unless(Schema::hasTable('club_sepa_fee_recharges') && Schema::hasTable('club_sepa_fee_corrections'), 503, __('sepa.unavailable'));
        app(PlanFeatureService::class)->ensureAllows($club, 'payment_tracking');
        app(PlanFeatureService::class)->ensureAllows($club, 'invoices');
        $data = $request->validate([
            'confirmed' => ['required', 'accepted'], 'request_id' => ['required', 'uuid'],
            'expected_revision' => ['required', 'integer', 'min:0'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:99999999'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'basis' => ['required', 'string', 'max:2000', 'regex:/\S/u'],
            'reason' => ['required', 'string', 'max:2000', 'regex:/\S/u'],
        ]);

        return response()->json(['data' => $service->propose($item, $request->user(), $data)])->header('Cache-Control', 'private, no-store');
    }

    public function approveFeeRecharge(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item, string $proposal, ClubSepaFeeRechargeService $service)
    {
        $this->resultAccess($request, $club, $batch, $item);
        abort_unless(Schema::hasColumn('club_sepa_fee_recharges', 'invoice_id'), 503, __('sepa.unavailable'));
        app(PlanFeatureService::class)->ensureAllows($club, 'payment_tracking');
        app(PlanFeatureService::class)->ensureAllows($club, 'invoices');
        $data = $request->validate([
            'confirmed' => ['required', 'accepted'], 'basis_confirmed' => ['required', 'accepted'],
            'revenue_account' => ['required', 'string', 'regex:/^[0-9]{1,20}$/D'],
        ]);

        return response()->json(['data' => $service->approve($item, ClubSepaFeeRecharge::findOrFail($proposal), $request->user(), $data['revenue_account'])])->header('Cache-Control', 'private, no-store');
    }

    public function requestFeeRechargeVoid(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item, string $proposal, ClubSepaFeeRechargeService $service)
    {
        $this->voidAccess($request, $club, $batch, $item);
        $data = $request->validate(['confirmed' => ['required', 'accepted'], 'request_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'max:2000', 'regex:/\S/u']]);

        return response()->json(['data' => $service->requestVoid($item, ClubSepaFeeRecharge::findOrFail($proposal), $request->user(), $data)])->header('Cache-Control', 'private, no-store');
    }

    public function approveFeeRechargeVoid(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item, string $proposal, string $voidRequest, ClubSepaFeeRechargeService $service)
    {
        $this->voidAccess($request, $club, $batch, $item);
        $request->validate(['confirmed' => ['required', 'accepted']]);

        return response()->json(['data' => $service->reviewVoid($item, ClubSepaFeeRecharge::findOrFail($proposal), ClubSepaFeeRechargeVoid::findOrFail($voidRequest), $request->user(), true)])->header('Cache-Control', 'private, no-store');
    }

    public function withdrawFeeRechargeVoid(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item, string $proposal, string $voidRequest, ClubSepaFeeRechargeService $service)
    {
        $this->voidAccess($request, $club, $batch, $item);
        $data = $request->validate(['confirmed' => ['required', 'accepted'], 'reason' => ['required', 'string', 'max:2000', 'regex:/\S/u']]);

        return response()->json(['data' => $service->reviewVoid($item, ClubSepaFeeRecharge::findOrFail($proposal), ClubSepaFeeRechargeVoid::findOrFail($voidRequest), $request->user(), false, $data['reason'])])->header('Cache-Control', 'private, no-store');
    }

    public function requestFeeRechargeCredit(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item, string $proposal, ClubSepaFeeRechargeService $service)
    {
        $this->creditAccess($request, $club, $batch, $item);
        $data = $request->validate(['confirmed' => ['required', 'accepted'], 'request_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'max:2000', 'regex:/\S/u']]);

        return response()->json(['data' => $service->requestCredit($item, ClubSepaFeeRecharge::findOrFail($proposal), $request->user(), $data)])->header('Cache-Control', 'private, no-store');
    }

    public function approveFeeRechargeCredit(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item, string $proposal, string $creditRequest, ClubSepaFeeRechargeService $service)
    {
        $this->creditAccess($request, $club, $batch, $item);
        $request->validate(['confirmed' => ['required', 'accepted']]);

        return response()->json(['data' => $service->reviewCredit($item, ClubSepaFeeRecharge::findOrFail($proposal), ClubSepaFeeRechargeCredit::findOrFail($creditRequest), $request->user(), true)])->header('Cache-Control', 'private, no-store');
    }

    public function withdrawFeeRechargeCredit(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item, string $proposal, string $creditRequest, ClubSepaFeeRechargeService $service)
    {
        $this->creditAccess($request, $club, $batch, $item);
        $data = $request->validate(['confirmed' => ['required', 'accepted'],
            'reason' => ['required', 'string', 'max:2000', 'regex:/\S/u']]);

        return response()->json(['data' => $service->reviewCredit($item, ClubSepaFeeRecharge::findOrFail($proposal), ClubSepaFeeRechargeCredit::findOrFail($creditRequest), $request->user(), false, $data['reason'])])->header('Cache-Control', 'private, no-store');
    }

    public function recordFeeRechargeRefund(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item, string $proposal, string $creditRequest, ClubSepaFeeRechargeService $service)
    {
        $this->creditAccess($request, $club, $batch, $item);
        $data = $request->validate([
            'confirmed' => ['required', 'accepted'], 'request_id' => ['required', 'uuid'],
            'booked_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['required', 'string', 'max:180', 'regex:/\S/u'],
            'finance_entry_id' => ['nullable', 'integer', 'exists:club_finance_entries,id'],
        ]);

        return response()->json(['data' => $service->recordRefund($item, ClubSepaFeeRecharge::findOrFail($proposal), ClubSepaFeeRechargeCredit::findOrFail($creditRequest), $request->user(), $data)])->header('Cache-Control', 'private, no-store');
    }

    public function downloadFeeRechargeCredit(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item, string $proposal, string $creditRequest, ClubSepaFeeRechargeCreditDocumentService $documents)
    {
        $this->creditAccess($request, $club, $batch, $item);
        $recharge = ClubSepaFeeRecharge::findOrFail($proposal);
        $credit = ClubSepaFeeRechargeCredit::findOrFail($creditRequest);
        abort_unless($recharge->club_id === $club->id && $recharge->settlement_id === $item->settlement?->id
            && $credit->club_id === $club->id && $credit->recharge_id === $recharge->id, 404);

        return response($documents->pdf($credit), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$credit->credit_note_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function feeRechargeCreditDocumentLink(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item, string $proposal, string $creditRequest)
    {
        $this->creditAccess($request, $club, $batch, $item);
        $recharge = ClubSepaFeeRecharge::findOrFail($proposal);
        $credit = ClubSepaFeeRechargeCredit::findOrFail($creditRequest);
        abort_unless($recharge->club_id === $club->id && $recharge->settlement_id === $item->settlement?->id
            && $credit->club_id === $club->id && $credit->recharge_id === $recharge->id
            && $credit->credit_note_number && in_array($credit->status, ['issued', 'completed', 'refunded'], true), 404);

        $expiresAt = now()->addMinutes(5);

        return response()->json(['data' => [
            'url' => URL::temporarySignedRoute('club-sepa-fee-recharge-credits.documents.signed', $expiresAt, ['credit' => $credit->id]),
            'expires_at' => $expiresAt->toJSON(),
        ]])->header('Cache-Control', 'private, no-store');
    }

    private function creditAccess(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item): void
    {
        $this->resultAccess($request, $club, $batch, $item);
        abort_unless(Schema::hasTable('club_sepa_fee_recharge_credits'), 503, __('sepa.unavailable'));
        app(PlanFeatureService::class)->ensureAllows($club, 'payment_tracking');
        app(PlanFeatureService::class)->ensureAllows($club, 'invoices');
    }

    private function voidAccess(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item): void
    {
        $this->resultAccess($request, $club, $batch, $item);
        abort_unless(Schema::hasTable('club_sepa_fee_recharge_voids') && Schema::hasColumn('club_sepa_fee_recharges', 'invoice_id'), 503, __('sepa.unavailable'));
        app(PlanFeatureService::class)->ensureAllows($club, 'payment_tracking');
        app(PlanFeatureService::class)->ensureAllows($club, 'invoices');
    }

    public function cancelFeeRecharge(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item, string $proposal, ClubSepaFeeRechargeService $service)
    {
        $this->resultAccess($request, $club, $batch, $item);
        abort_unless(Schema::hasTable('club_sepa_fee_recharges'), 503, __('sepa.unavailable'));
        $data = $request->validate(['confirmed' => ['required', 'accepted'], 'reason' => ['required', 'string', 'max:2000', 'regex:/\S/u']]);

        return response()->json(['data' => $service->cancel($item, ClubSepaFeeRecharge::findOrFail($proposal), $request->user(), $data['reason'])])->header('Cache-Control', 'private, no-store');
    }

    public function correctFee(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item, ClubSepaFeeService $service)
    {
        $this->resultAccess($request, $club, $batch, $item);
        abort_unless(Schema::hasTable('club_sepa_fee_corrections'), 503, __('sepa.unavailable'));
        app(PlanFeatureService::class)->ensureAllows($club, 'payment_tracking');
        $data = $request->validate([
            'confirmed' => ['required', 'accepted'],
            'request_id' => ['required', 'uuid'],
            'expected_revision' => ['required', 'integer', 'min:0'],
            'amount_cents' => ['required', 'integer', 'min:0', 'max:99999999'],
            'booked_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['required', 'string', 'max:180', 'regex:/\S/u'],
            'reason' => ['required', 'string', 'max:2000', 'regex:/\S/u'],
        ]);

        return response()->json(['data' => $service->correct($item, $request->user(), $data)])->header('Cache-Control', 'private, no-store');
    }

    public function recordFee(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item, ClubSepaFeeService $service)
    {
        $this->resultAccess($request, $club, $batch, $item);
        abort_unless(Schema::hasColumn('club_sepa_settlements', 'fee_finance_entry_id'), 503, __('sepa.unavailable'));
        app(PlanFeatureService::class)->ensureAllows($club, 'payment_tracking');
        $data = $request->validate([
            'confirmed' => ['required', 'accepted'],
            'booked_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['required', 'string', 'max:180', 'regex:/\S/u'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:99999999'],
            'finance_entry_id' => ['sometimes', 'required', 'integer', 'min:1'],
        ]);

        return response()->json(['data' => $service->record($item, $request->user(), $data)])->header('Cache-Control', 'private, no-store');
    }

    public function authorizeItemRetry(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item, ClubSepaSettlementService $service)
    {
        $this->resultAccess($request, $club, $batch, $item);
        $data = $request->validate(['confirmed' => ['required', 'accepted'], 'reason' => ['required', 'string', 'max:2000', 'regex:/\S/u']]);

        return response()->json(['data' => $service->authorizeRetry($item, $request->user(), $data['reason'])])->header('Cache-Control', 'private, no-store');
    }

    private function resultAccess(Request $request, Club $club, ClubSepaBatch $batch, ClubSepaBatchItem $item): void
    {
        $this->access($request, $club, true, $batch);
        abort_unless(Schema::hasTable('club_sepa_settlements'), 503, __('sepa.unavailable'));
        abort_unless((int) $item->club_sepa_batch_id === (int) $batch->id, 404);
    }

    private function access(Request $request, Club $club, bool $write = true, ?ClubSepaBatch $batch = null, ?string $permission = null): void
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), $permission ?? ($write ? ClubPermissions::FINANCE_MANAGE : ClubPermissions::FINANCE_VIEW)), 403);
        if ($batch) {
            abort_unless((int) $batch->club_id === (int) $club->id, 404);
        }
        app(PlanFeatureService::class)->ensureAllows($club, 'sepa_export');
        abort_unless(Schema::hasTable('club_sepa_batches'), 503, __('sepa.unavailable'));
    }

    private function payload(ClubSepaBatch $batch, bool $includeNoticeContent = false): array
    {
        $batch->loadMissing('items');

        return [
            'id' => $batch->id, 'reference' => $batch->reference, 'status' => $batch->status,
            'created_by' => $batch->created_by, 'approved_by' => $batch->approved_by,
            'collection_date' => $batch->collection_date->toDateString(), 'notice_days' => $batch->notice_days,
            'approved_at' => $batch->approved_at?->toJSON(), 'notice_sent_on' => $batch->notice_sent_on?->toDateString(),
            'notice_channel' => $batch->notice_channel, 'notice_reference' => $batch->notice_reference,
            'exported_at' => $batch->exported_at?->toJSON(), 'cancelled_at' => $batch->cancelled_at?->toJSON(),
            'cancellation_reason' => $batch->cancellation_reason,
            'total_cents' => $batch->items->sum('amount_cents'),
            'creditor_name' => $batch->creditor_snapshot['sepa_account_holder'],
            'creditor_id' => $batch->creditor_snapshot['sepa_creditor_id'],
            'notices' => $batch->relationLoaded('notices') ? $batch->notices->map(fn ($notice) => [
                'id' => $notice->id, 'status' => $notice->status,
                'sent_at' => $notice->sent_at?->toJSON(), 'error_code' => $notice->error_code,
                'message_id' => $notice->message_id,
                'provider_status' => $notice->provider_status,
                'delivered_at' => $notice->delivered_at?->toJSON(),
                'bounced_at' => $notice->bounced_at?->toJSON(),
                'bounce_type' => $notice->bounce_type,
                ...($includeNoticeContent ? ['content' => $notice->content] : []),
            ])->values() : [],
            'items' => $batch->items->map(fn ($item) => [
                'id' => $item->id, 'invoice_id' => $item->invoice_id, 'amount_cents' => $item->amount_cents,
                'number' => $item->debtor_snapshot['number'], 'name' => $item->debtor_snapshot['name'],
                'mandate_reference' => $item->debtor_snapshot['sepa_mandate_reference'],
                'iban_last4' => substr($item->debtor_snapshot['sepa_iban'], -4),
                'settlement' => $item->relationLoaded('settlement') ? $item->settlement : null,
                'invoice' => $item->relationLoaded('invoice') && $item->invoice ? [
                    'id' => $item->invoice->id, 'number' => $item->invoice->number, 'amount' => $item->invoice->amount,
                    'status' => $item->invoice->status, ...$item->invoice->balancePayload(),
                ] : null,
                'payment_options' => $includeNoticeContent && $item->relationLoaded('invoice') && $item->invoice
                    ? $item->invoice->payments->filter(fn ($payment) => $payment->status === 'paid' && in_array($payment->method, ['bank_transfer', 'bank_import', 'sepa_debit'], true) && (int) round((float) $payment->amount * 100) === $item->amount_cents)
                        ->map(fn ($payment) => ['id' => $payment->id, 'amount' => $payment->amount, 'paid_at' => $payment->paid_at?->toDateString(), 'reference' => $payment->reference])->values() : [],
            ])->values(),
        ];
    }
}
