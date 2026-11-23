<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubPayoutAccount;
use App\Models\CommerceOrderItem;
use App\Services\MarketplacePayoutService;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ClubPayoutController extends Controller
{
    public function store(Request $request, Club $club)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_MANAGE), 403);
        $request->merge(['iban' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('iban')))]);
        $data = $request->validate(['holder' => ['required', 'string', 'max:180'], 'iban' => ['required', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/']]);
        $iban = $data['iban'];
        $digits = strtr(substr($iban, 4).substr($iban, 0, 4), array_combine(range('A', 'Z'), range(10, 35)));
        $mod = 0;
        foreach (str_split($digits) as $digit) {
            $mod = ($mod * 10 + (int) $digit) % 97;
        }
        abort_unless($mod === 1, 422, 'Die IBAN-Prüfziffer ist ungültig.');
        DB::transaction(function () use ($club, $request, $data, $iban) {
            Club::whereKey($club->id)->lockForUpdate()->firstOrFail();
            abort_if(CommerceOrderItem::where('shop_club_id', $club->id)->whereNotNull('club_payout_account_id')->whereNull('club_paid_at')->exists(), 409, 'Eine Überweisung ist in Bearbeitung.');
            ClubPayoutAccount::where('club_id', $club->id)->whereIn('status', ['pending', 'verified'])->update(['status' => 'superseded']);
            ClubPayoutAccount::create([...$data, 'club_id' => $club->id, 'submitted_by' => $request->user()->id, 'last_four' => substr($iban, -4)]);
        });
        return $request->expectsJson() ? response()->json(['status' => 'pending']) : back();
    }

    public function index()
    {
        $accounts = ClubPayoutAccount::query()->join('clubs', 'clubs.id', '=', 'club_payout_accounts.club_id')
            ->whereIn('club_payout_accounts.status', ['pending', 'verified'])
            ->get(['club_payout_accounts.*', 'clubs.name as club_name'])
            ->map(fn ($account) => [...$account->only('id', 'club_id', 'club_name', 'holder', 'status'), 'iban' => $account->iban]);
        $items = CommerceOrderItem::whereNotNull('shop_club_id')->whereNull('club_paid_at')
            ->whereHas('order', fn ($order) => $order->where('status', 'completed'))
            ->orderBy('id')->paginate(50, ['id', 'shop_club_id', 'title', 'currency', 'club_payout_account_id', 'club_payout_cents']);
        return Inertia::render('Auth/Dashboard/Admin/Commerce/ClubPayouts', ['accounts' => $accounts, 'items' => $items]);
    }

    public function review(Request $request, ClubPayoutAccount $account)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['verified', 'rejected'])]]);
        abort_if((int) $account->submitted_by === (int) $request->user()->id, 403, 'Eine zweite Person muss die Kontoinhaberschaft prüfen.');
        $updated = ClubPayoutAccount::whereKey($account->id)->where('status', 'pending')->update([
            'status' => $data['status'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
        ]);
        abort_unless($updated, 409);
        return back();
    }

    public function transfer(Request $request, CommerceOrderItem $item)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['prepare', 'paid', 'release'])],
            'account_id' => ['required', 'integer'],
            'reference' => ['required_if:action,paid', 'nullable', 'string', 'max:160']]);
        DB::transaction(function () use ($item, $data) {
            $club = Club::whereKey($item->shop_club_id)->lockForUpdate()->firstOrFail();
            $order = $item->order()->lockForUpdate()->firstOrFail();
            $line = CommerceOrderItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            abort_if($line->club_paid_at, 409);
            if ($data['action'] === 'release') {
                abort_unless($line->club_payout_account_id && (int) $line->club_payout_account_id === (int) $data['account_id'], 409);
                $line->forceFill(['club_payout_account_id' => null, 'club_payout_cents' => null])->save();
                return;
            }
            $account = ClubPayoutAccount::where('club_id', $club->id)->where('status', 'verified')->lockForUpdate()->first();
            abort_unless($account, 422, 'Das Vereinskonto muss zuerst verifiziert werden.');
            abort_unless((int) $account->id === (int) $data['account_id'], 409, 'Das Vereinskonto hat sich geändert. Bitte die Ansicht aktualisieren.');
            $since = $line->is_shippable ? ($line->club_delivered_at ?: ($order->shipping_status === 'delivered' ? $order->delivered_at : null)) : $order->completed_at;
            abort_unless($order->status === 'completed' && ! $order->refunded_cents && $order->payout_status === 'pending' && ! $order->payout_id
                && in_array($order->issue_status, [null, 'none'], true) && ! $order->returnRequests()->exists()
                && $since && \Illuminate\Support\Carbon::parse($since)->lte(now()->subDays(MarketplacePayoutService::ELIGIBILITY_DAYS)), 422, 'Diese Position ist noch nicht auszahlbar.');
            if ($data['action'] === 'paid') {
                abort_unless((int) $line->club_payout_account_id === (int) $account->id, 409);
                $line->forceFill(['club_paid_at' => now(), 'club_bank_reference' => $data['reference']])->save();
                return;
            }
            abort_if($line->club_payout_account_id, 409);
            // Allocate any cart-wide discount proportionally, preserving rounding across positions.
            $total = (int) $order->items()->sum('total_cents');
            $before = (int) $order->items()->where('id', '<', $line->id)->sum('total_cents');
            abort_unless($total > 0 && $line->currency === $order->currency, 422);
            $paid = min($total, (int) $order->amount_cents);
            $gross = intdiv(($before + $line->total_cents) * $paid, $total) - intdiv($before * $paid, $total);
            $commission = $line->club_commission_cents ?? (intdiv(($before + $line->total_cents) * $order->commission_cents, $total) - intdiv($before * $order->commission_cents, $total));
            abort_unless($gross > $commission, 422, 'Kein auszahlbarer Betrag.');
            $line->forceFill(['club_payout_account_id' => $account->id, 'club_payout_cents' => $gross - $commission])->save();
        }, 3);
        return back();
    }
}
