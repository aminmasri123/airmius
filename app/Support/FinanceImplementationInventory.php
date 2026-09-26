<?php

namespace App\Support;

final class FinanceImplementationInventory
{
    public const VERSION = '2026-09-26.t003-t008';

    public static function forClient(): array
    {
        return [
            'version' => self::VERSION,
            'scope' => 'T003-T008 existing implementation inventory',
            'coverage' => [
                self::item('receivables', 'Forderungen', ['Invoice', 'SubscriptionInvoice', 'PaymentCheckout'], ['amount', 'due_date', 'status', 'source']),
                self::item('payment_methods', 'Zahlarten', ['Payment', 'PaymentCheckout', 'ClubMembershipApplication'], ['method', 'provider', 'bank_transfer', 'stripe', 'paypal', 'sepa']),
                self::item('allocation', 'Zuordnung', ['ClubInvoicePaymentService', 'BankTransaction', 'Payment'], ['invoice_id', 'bank_transaction_id', 'manual_confirmation', 'partial_payment']),
                self::item('sepa', 'SEPA', ['ClubSepaBatch', 'ClubSepaBatchItem', 'ClubSepaNotice', 'ClubSepaSettlement'], ['batch_reference', 'mandate_reference', 'notice_delivery', 'settlement']),
                self::item('returns', 'Rueckgaben', ['ClubSepaSettlement', 'ClubSepaFeeService'], ['returned_amount', 'return_reason', 'fee_finance_entry_id', 'retry_authorization']),
                self::item('dunning', 'Mahnung', ['Invoice', 'SendMembershipAndBillingReminders'], ['reminder_sent_at', 'due_soon_notified_at', 'overdue']),
                self::item('refunds', 'Erstattungen', ['CommerceRefund', 'CommerceReturnRequest', 'ClubSepaFeeRechargeCredit'], ['provider_idempotency', 'refund_due_cents', 'refund_recorded_at']),
                self::item('deduplication', 'Deduplizierung', ['ClubMemberDuplicates', 'MailDelivery', 'ClubPaymentNumberService'], ['duplicate_candidate', 'dedupe_key', 'number_allocation']),
            ],
            'open_followups' => [
                'unified_receivable_payment_status_machine',
                'formal_dunning_rule_versions',
                'club_level_refund_four_eye_workflow',
                'provider_bank_adapter_staging_evidence',
            ],
        ];
    }

    private static function item(string $key, string $label, array $artifacts, array $contractFields): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => 'inventoried',
            'artifacts' => $artifacts,
            'contract_fields' => $contractFields,
        ];
    }
}
