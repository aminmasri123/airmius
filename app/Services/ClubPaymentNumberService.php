<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ClubPaymentNumberService
{
    public function __construct(private readonly ClubNumberRangeService $numberRanges) {}

    public function create(Club $club, array $attributes, ?User $actor = null): Payment
    {
        return DB::transaction(function () use ($club, $attributes, $actor) {
            if (Schema::hasColumn('payments', 'club_money_account_id')) {
                $method = $attributes['method'] ?? 'manual';
                if (in_array($method, ['cash', 'bank_transfer', 'bank_import', 'sepa_debit'], true)) {
                    $attributes['club_money_account_id'] = app(ClubMoneyAccountService::class)->resolve(
                        (int) $club->id, $method === 'cash' ? 'cash' : 'bank',
                        isset($attributes['club_money_account_id']) ? (int) $attributes['club_money_account_id'] : null,
                    );
                } elseif (! empty($attributes['club_money_account_id'])) {
                    throw ValidationException::withMessages(['club_money_account_id' => 'Bitte zuerst eine passende Zahlungsart wählen.']);
                }
            } else {
                unset($attributes['club_money_account_id']);
            }
            $receipt = $this->numberRanges->allocateDefault(
                $club,
                'receipt',
                $actor,
                (string) Str::uuid(),
                fn (string $number) => ! Payment::query()
                    ->where('club_id', $club->id)
                    ->where('receipt_number', $number)
                    ->exists(),
            );
            $donation = ($attributes['purpose'] ?? null) === 'donation'
                ? $this->numberRanges->allocateDefault(
                    $club,
                    'donation',
                    $actor,
                    (string) Str::uuid(),
                    fn (string $number) => ! Payment::query()
                        ->where('club_id', $club->id)
                        ->where('donation_number', $number)
                        ->exists(),
                )
                : null;

            $payment = Payment::query()->create([
                ...$attributes,
                'club_id' => $club->id,
                'receipt_number' => $receipt?->formatted_number,
                'donation_number' => $donation?->formatted_number,
            ]);

            if ($receipt) {
                $this->numberRanges->assignTo($receipt, 'receipt', $payment->id);
            }
            if ($donation) {
                $this->numberRanges->assignTo($donation, 'donation', $payment->id);
            }

            return $payment;
        });
    }
}
