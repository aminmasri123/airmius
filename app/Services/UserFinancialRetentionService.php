<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserFinancialRetentionService
{
    public function preserveBeforeAccountDeletion(User $user): void
    {
        $recipient = [
            'name' => $user->name,
            'email' => $user->email,
            'captured_at' => now()->toIso8601String(),
        ];

        foreach (DB::table('subscription_invoices')
            ->leftJoin('clubs', 'clubs.id', '=', 'subscription_invoices.club_id')
            ->where('subscription_invoices.user_id', $user->id)
            ->whereNull('subscription_invoices.recipient_snapshot')
            ->select('subscription_invoices.id', 'clubs.name as club_name')
            ->get() as $invoice) {
            DB::table('subscription_invoices')->where('id', $invoice->id)->update([
                'recipient_snapshot' => json_encode([
                    ...$recipient,
                    'club_name' => $invoice->club_name,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }

        DB::table('payments')->where('user_id', $user->id)
            ->whereNull('payer_snapshot')
            ->update([
                'payer_snapshot' => json_encode($recipient, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }
}
