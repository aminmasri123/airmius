<?php

namespace App\Services;

use App\Models\CommerceOrder;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ClubShopOrderNumberService
{
    private const COLUMNS = [
        'shop_invoice' => 'invoice_number',
        'shop_credit_note' => 'credit_note_number',
    ];

    public function __construct(private readonly ClubNumberRangeService $numberRanges) {}

    public function assign(
        CommerceOrder $order,
        string $scope,
        Closure $legacyNumber,
        ?User $actor = null,
    ): string {
        $column = self::COLUMNS[$scope] ?? throw new InvalidArgumentException('Unsupported shop number scope.');

        return DB::transaction(function () use ($order, $scope, $column, $legacyNumber, $actor) {
            $locked = CommerceOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (filled($locked->{$column})) {
                return (string) $locked->{$column};
            }

            $allocation = $locked->club
                ? $this->numberRanges->allocateDefault(
                    $locked->club,
                    $scope,
                    $actor,
                    (string) Str::uuid(),
                    fn (string $number) => ! CommerceOrder::query()
                        ->where('club_id', $locked->club_id)
                        ->where($column, $number)
                        ->exists(),
                )
                : null;
            $number = $allocation?->formatted_number ?? $legacyNumber();
            $locked->forceFill([$column => $number])->save();
            if ($allocation) {
                $this->numberRanges->assignTo($allocation, $scope, $locked->id);
            }
            $order->setAttribute($column, $number);

            return $number;
        });
    }
}
