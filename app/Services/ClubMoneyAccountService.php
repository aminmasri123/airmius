<?php

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\ClubMoneyAccount;
use App\Support\ClubFinanceWorkspaceReadiness;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ClubMoneyAccountService
{
    public function transactionHash(string $hash, ?int $accountId): string
    {
        return $accountId ? hash('sha256', $accountId.':'.$hash) : $hash;
    }

    public function isDuplicateTransaction(int $clubId, string $hash, ?int $accountId): bool
    {
        return BankTransaction::where('club_id', $clubId)->where(function ($query) use ($hash, $accountId) {
            $query->where('transaction_hash', $this->transactionHash($hash, $accountId));
            if ($accountId && Schema::hasColumn('bank_transactions', 'club_money_account_id')) {
                $query->orWhere(fn ($legacy) => $legacy->where('transaction_hash', $hash)->whereNull('club_money_account_id'));
            }
        })->exists();
    }

    public function forIban(int $clubId, ?string $iban): ?int
    {
        if (! $iban || ! Schema::hasColumn('club_money_accounts', 'iban')) {
            return null;
        }
        $accounts = ClubMoneyAccount::where('club_id', $clubId)->where('type', 'bank')->where('is_active', true)
            ->where('iban', strtoupper(preg_replace('/\s+/', '', $iban)))->get();

        return $accounts->count() === 1 ? $accounts->first()->id : null;
    }

    public function resolve(int $clubId, string $type, ?int $accountId, ?int $teamId = null, bool $requireSelection = false): ?int
    {
        if (! ClubFinanceWorkspaceReadiness::ready()) {
            return null;
        }
        $accounts = ClubMoneyAccount::where('club_id', $clubId)->where('type', $type)->get();
        if ($accountId) {
            $account = $accounts->firstWhere('id', $accountId);
            if (! $account || $account->is_active === false || ($teamId && $account->team_id && (int) $account->team_id !== $teamId)) {
                throw ValidationException::withMessages(['club_money_account_id' => 'Bitte eine passende aktive Kasse oder ein aktives Bankkonto des Vereins wählen.']);
            }

            return $account->id;
        }
        $available = $accounts->filter(fn ($account) => $account->is_active !== false
            && ($teamId ? (int) $account->team_id === $teamId : $account->team_id === null));
        if ($requireSelection && $available->count() === 1) {
            return $available->first()->id;
        }
        if ($requireSelection && $accounts->isNotEmpty()) {
            throw ValidationException::withMessages(['club_money_account_id' => 'Bitte das Bankkonto für diese Bankdatei auswählen.']);
        }

        return null;
    }
}
