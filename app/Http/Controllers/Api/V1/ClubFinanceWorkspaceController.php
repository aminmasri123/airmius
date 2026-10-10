<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubCostCenter;
use App\Models\ClubDepartment;
use App\Models\ClubFinanceEntry;
use App\Models\ClubMoneyAccount;
use App\Models\ClubProject;
use App\Models\ClubYearPeriod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ClubFinanceBalanceService;
use App\Services\ClubFinanceScopeService;
use App\Services\ClubFinanceYearCloseService;
use App\Support\ClubAuditLog;
use App\Support\ClubFinanceWorkspaceReadiness;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubFinanceWorkspaceController extends Controller
{
    public function index(Request $request, Club $club)
    {
        $this->authorizeFinance($request, $club, ClubPermissions::FINANCE_VIEW);
        if (! ClubFinanceWorkspaceReadiness::ready()) {
            return response()->json(['data' => ['available' => false]]);
        }
        $accounts = ClubMoneyAccount::where('club_id', $club->id)
            ->withSum(['entries as income_total' => fn ($q) => $q->where('type', 'income')], 'amount')
            ->withSum(['entries as expense_total' => fn ($q) => $q->where('type', 'expense')], 'amount')
            ->withSum(['payments as payment_total' => fn ($q) => $q->where('status', 'paid')], 'amount')->get();
        $summary = app(ClubFinanceBalanceService::class)->summary($club);
        $accountBalance = fn ($account) => (int) round(((float) $account->income_total - (float) $account->expense_total + (float) $account->payment_total) * 100);

        return response()->json(['data' => [
            'available' => true,
            'can_close' => app(ClubFinanceYearCloseService::class)->available() && ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_APPROVE),
            'teams' => $club->teams()->get(['id', 'name', 'club_department_id']),
            'departments' => ClubDepartment::where('club_id', $club->id)->get(['id', 'name']),
            'projects' => ClubProject::where('club_id', $club->id)->get(['id', 'name']),
            'cost_centers' => ClubCostCenter::where('club_id', $club->id)->get(['id', 'code', 'name']),
            'periods' => ClubYearPeriod::where('club_id', $club->id)->where('type', 'business')->orderByDesc('starts_on')->get(),
            'accounts' => $accounts->map(fn ($account) => [
                'id' => $account->id, 'name' => $account->name, 'type' => $account->type, 'team_id' => $account->team_id,
                'bank_name' => $account->bank_name, 'account_holder' => $account->account_holder,
                'iban' => $account->iban, 'bic' => $account->bic, 'is_active' => $account->is_active ?? true,
                'balance_cents' => (int) round(((float) $account->income_total - (float) $account->expense_total + (float) $account->payment_total) * 100),
            ]),
            'unassigned_accounts' => collect(['cash', 'bank'])->map(fn ($type) => [
                'type' => $type,
                'balance_cents' => (int) round($summary[$type.'_balance'] * 100) - $accounts->where('type', $type)->sum($accountBalance),
            ])->values(),
            'can_manage' => ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_EDIT),
            'can_approve' => ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_APPROVE),
        ]]);
    }

    public function storeAccount(Request $request, Club $club)
    {
        $this->authorizeFinance($request, $club, ClubPermissions::FINANCE_EDIT);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(['cash', 'bank'])],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('club_id', $club->id)],
            'opening_cents' => ['required', 'integer', 'between:-999999999,999999999'],
            'opened_on' => ['required', 'date'],
            ...$this->bankRules(),
        ]);
        $data = $this->normalizeBankData($data);
        $account = DB::transaction(function () use ($data, $club, $request) {
            $account = ClubMoneyAccount::create([
                'club_id' => $club->id, 'team_id' => $data['team_id'] ?? null,
                'name' => trim($data['name']), 'type' => $data['type'],
                ...Arr::only($data, ['bank_name', 'account_holder', 'iban', 'bic']),
            ]);
            if ($data['opening_cents'] !== 0) {
                ClubFinanceEntry::create([
                    'club_id' => $club->id, 'user_id' => $request->user()->id,
                    'club_money_account_id' => $account->id, 'team_id' => $account->team_id,
                    'type' => $data['opening_cents'] > 0 ? 'income' : 'expense',
                    'account' => $account->type, 'entry_kind' => 'opening', 'title' => $account->name,
                    'amount' => abs($data['opening_cents']) / 100, 'booked_on' => $data['opened_on'],
                ]);
            }
            ClubAuditLog::record($club, $request->user(), 'club.money_account.created', $account, ['opening_cents' => $data['opening_cents']]);

            return $account;
        });

        return response()->json(['data' => $account], 201);
    }

    public function updateAccount(Request $request, Club $club, ClubMoneyAccount $account)
    {
        $this->authorizeFinance($request, $club, ClubPermissions::FINANCE_EDIT);
        abort_unless((int) $account->club_id === (int) $club->id, 404);
        abort_unless(Schema::hasColumn('club_money_accounts', 'is_active'), 503, 'Kontenmigration ist noch nicht aktiviert.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160', 'not_regex:/^\s*$/u'],
            'is_active' => ['required', 'boolean'],
            ...$this->bankRules(),
        ]);
        $data['type'] = $account->type;
        $data = [...$account->only(['bank_name', 'account_holder', 'iban', 'bic']), ...$data];
        $data = $this->normalizeBankData($data);
        DB::transaction(function () use ($data, $account, $club, $request) {
            Club::whereKey($club->id)->lockForUpdate()->firstOrFail();
            $account->update(Arr::except($data, 'type'));
            ClubAuditLog::record($club, $request->user(), 'club.money_account.updated', $account, ['is_active' => $account->is_active]);
        });

        return response()->json(['data' => $account]);
    }

    private function bankRules(): array
    {
        return [
            'bank_name' => ['nullable', 'string', 'max:160'],
            'account_holder' => ['nullable', 'string', 'max:160'],
            'iban' => ['nullable', 'string', 'max:42'],
            'bic' => ['nullable', 'string', 'regex:/^[A-Za-z]{6}[A-Za-z0-9]{2}([A-Za-z0-9]{3})?$/'],
        ];
    }

    private function normalizeBankData(array $data): array
    {
        $data['name'] = trim($data['name']);
        if ($data['name'] === '') {
            throw ValidationException::withMessages(['name' => 'Bitte einen Namen angeben.']);
        }
        $fields = ['bank_name', 'account_holder', 'iban', 'bic'];
        if (! Schema::hasColumn('club_money_accounts', 'is_active')) {
            return Arr::except($data, $fields);
        }
        foreach ($fields as $field) {
            $data[$field] = $data['type'] === 'bank' ? (trim($data[$field] ?? '') ?: null) : null;
        }
        if ($data['iban']) {
            $iban = strtoupper(preg_replace('/\s+/', '', $data['iban']));
            if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
                throw ValidationException::withMessages(['iban' => 'Bitte eine gültige IBAN angeben.']);
            }
            $digits = '';
            foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $character) {
                $digits .= ctype_alpha($character) ? ord($character) - 55 : $character;
            }
            $remainder = 0;
            foreach (str_split($digits) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
            if ($remainder !== 1) {
                throw ValidationException::withMessages(['iban' => 'Bitte eine gültige IBAN angeben.']);
            }
            $data['iban'] = $iban;
        }
        $data['bic'] = $data['bic'] ? strtoupper($data['bic']) : null;

        return $data;
    }

    public function transfer(Request $request, Club $club)
    {
        $this->authorizeFinance($request, $club, ClubPermissions::FINANCE_EDIT);
        $data = $request->validate([
            'from_id' => ['required', 'integer', Rule::exists('club_money_accounts', 'id')->where('club_id', $club->id)],
            'to_id' => ['required', 'integer', 'different:from_id', Rule::exists('club_money_accounts', 'id')->where('club_id', $club->id)],
            'amount_cents' => ['required', 'integer', 'between:1,999999999'],
            'booked_on' => ['required', 'date'], 'reference' => ['nullable', 'string', 'max:120'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        DB::transaction(function () use ($data, $club, $request) {
            Club::whereKey($club->id)->lockForUpdate()->firstOrFail();
            $accounts = ClubMoneyAccount::whereIn('id', [$data['from_id'], $data['to_id']])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $existing = ClubFinanceEntry::where('club_id', $club->id)->where('transfer_key', $data['idempotency_key'])->get();
            if ($existing->isNotEmpty()) {
                abort_unless($existing->count() === 2 && $existing->where('type', 'expense')->first()?->club_money_account_id == $data['from_id']
                    && $existing->where('type', 'income')->first()?->club_money_account_id == $data['to_id']
                    && (int) round((float) $existing->first()->amount * 100) === $data['amount_cents'], 422);

                return;
            }
            foreach (['expense' => $data['from_id'], 'income' => $data['to_id']] as $type => $id) {
                $account = $accounts[$id];
                abort_if($account->is_active === false, 422, 'Archivierte Kassen und Konten können nicht bebucht werden.');
                ClubFinanceEntry::create([
                    'club_id' => $club->id, 'user_id' => $request->user()->id,
                    'club_money_account_id' => $id, 'team_id' => $account->team_id,
                    'type' => $type, 'account' => $account->type, 'entry_kind' => 'transfer',
                    'transfer_key' => $data['idempotency_key'], 'title' => $accounts[$data['from_id']]->name.' -> '.$accounts[$data['to_id']]->name,
                    'amount' => $data['amount_cents'] / 100, 'booked_on' => $data['booked_on'], 'reference' => $data['reference'] ?? null,
                ]);
            }
            ClubAuditLog::record($club, $request->user(), 'club.money_account.transferred', $accounts[$data['from_id']], ['amount_cents' => $data['amount_cents'], 'to_id' => $data['to_id']]);
        });

        return response()->json(['data' => ['transferred' => true]]);
    }

    public function assign(Request $request, Club $club, string $kind, int $id)
    {
        $this->authorizeFinance($request, $club, ClubPermissions::FINANCE_EDIT);
        $model = match ($kind) {
            'invoice' => Invoice::class,
            'payment' => Payment::class,
            default => abort(404),
        };
        $row = $model::where('club_id', $club->id)->findOrFail($id);
        if ($row instanceof Payment) {
            abort_if($request->filled('club_money_account_id') && ! in_array($row->method, ['cash', 'bank_transfer', 'bank_import', 'sepa_debit'], true), 422, 'Bitte zuerst eine passende Zahlungsart wählen.');
            $request->merge(['account' => $row->method === 'cash' ? 'cash' : 'bank', 'paid_on' => ($row->paid_at ?? $row->created_at)?->toDateString()]);
        }
        $data = app(ClubFinanceScopeService::class)->validate($request, $club);
        $fields = ['team_id', 'club_budget_id', 'club_department_id', 'club_project_id', 'club_cost_center_id'];
        if ($row instanceof Payment) {
            $fields[] = 'club_money_account_id';
        }
        $row->fill(Arr::only($data, $fields))->save();
        ClubAuditLog::record($club, $request->user(), 'club.finance.scope_assigned', $row, ['kind' => $kind]);

        return response()->json(['data' => $row]);
    }

    public function storeReference(Request $request, Club $club, string $kind)
    {
        $this->authorizeFinance($request, $club, ClubPermissions::FINANCE_EDIT);
        $table = match ($kind) {
            'project' => 'club_projects', 'cost-center' => 'club_cost_centers', default => abort(404),
        };
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'string', 'max:40', Rule::unique($table, 'code')->where('club_id', $club->id)],
        ]);
        $class = $kind === 'project' ? ClubProject::class : ClubCostCenter::class;
        $row = $class::create(['club_id' => $club->id, ...$data]);
        ClubAuditLog::record($club, $request->user(), 'club.finance.reference_created', $row, ['kind' => $kind]);

        return response()->json(['data' => $row], 201);
    }

    private function authorizeFinance(Request $request, Club $club, string $permission): void
    {
        abort_unless($request->user() && ClubPermissions::allows($club, $request->user(), $permission), 403);
        if ($request->method() !== 'GET') {
            abort_unless(ClubFinanceWorkspaceReadiness::ready(), 503, 'Finanzmigrationen sind noch nicht aktiviert.');
        }
    }
}
