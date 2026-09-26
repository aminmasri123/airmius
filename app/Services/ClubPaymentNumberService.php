<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ClubPaymentNumberService
{
    public function __construct(private readonly ClubNumberRangeService $numberRanges) {}

    public function create(Club $club, array $attributes, ?User $actor = null): Payment
    {
        return DB::transaction(function () use ($club, $attributes, $actor) {
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
