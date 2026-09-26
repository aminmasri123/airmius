<?php

return [
    'recharge_void_bank' => 'This invoice has payment or bank activity. Review the bank status and the separate refund workflow first.',
    'recharge_title' => 'Returned-debit fee recharge',
    'recharge_account' => 'The separate recharge account is missing or matches the bank account.',
    'recharge_invoice_controlled' => 'This invoice belongs to an approved fee recharge and cannot be changed or deleted here.',
    'recharge_changed' => 'The fee or proposal has changed. Review the current state.',
    'recharge_active' => 'An active recharge proposal already exists for this fee.',
    'fee_correction_state' => 'The fee booking has changed. Review its current state again.',
    'fee_correction_title' => 'Returned-debit fee correction',
    'fee_export_account_required' => 'Configure a separate expense account in DATEV settings before exporting recorded returned-debit fees.',
    'fee_title' => 'Bank fee for returned debit',
    'fee_recorded' => 'A different fee has already been recorded for this return.',
    'fee_mismatch' => 'The selected expense does not match the club, amount, date or bank reference.',
    'fee_existing' => 'A booking already exists for this bank reference. Explicitly link the existing expense.',
    'fee_controlled' => 'This expense is linked to a SEPA return and cannot be changed here.',

    'import_mapping' => 'Map every required field uniquely and explicitly exclude every remaining column.',
    'import_file' => 'Use a UTF-8 CSV with the specified columns and at most 200 rows (2 MB).',
    'import_rows' => 'The import contains ambiguous or invalid rows. Resolve all errors before importing.',
    'import_review' => 'The file, bank result or preview has changed. Review and confirm a new preview.',
    'import_unlinked' => 'Positions without a linked receipt require separate confirmation. No existing payment will be reversed.',
    'payment_controlled' => 'This payment belongs to a saved SEPA result and cannot be edited or deleted here.',
    'result_recorded' => 'A different bank result is already recorded for this entry.',
    'bank_reference_used' => 'This bank booking reference has already been used in this club.',
    'payment_mismatch' => 'The selected payment does not match the invoice, amount or booking date.',
    'bank_date' => 'The booking date must be between collection and today; a return cannot precede the receipt.',
    'return_required' => 'Record a documented return first.',
    'export_required' => 'Bank results can only be recorded for exported debit batches.',
    'mail_transport' => 'A real mail transport without automatic fallback delivery is required.',
    'notice_recipient' => 'Each entry requires a valid email address. Review recipients before sending.',
    'notice_recipient_changed' => 'A recipient address has changed. Cancel and prepare a new batch.',
    'notice_prepare_first' => 'Prepare and review the advance notices first.',
    'notice_sending' => 'An advance notice is being sent. Wait for dispatch to finish.',
    'notice_subject' => 'SEPA advance notice: :club – invoice :invoice',
    'notice_body' => 'Hello :name,

:club will collect :amount for invoice :invoice by SEPA direct debit on :date.

Creditor ID: :creditor
Mandate reference: :mandate
Account ends in: :iban
Debit batch: :reference

Please contact the club administration before collection if you have any questions.

:club',
    'retained_history' => 'This club has saved direct debit batches. Its financial history must be retained, so permanent deletion is unavailable.',
    'credentials' => 'Complete the club account and creditor ID.',
    'invoices' => 'Select only outstanding invoices belonging to this club.',
    'reserved' => 'An invoice is already reserved by an active debit batch.',
    'state' => 'This action is not allowed in the current state.',
    'second_person' => 'A different authorized person must approve this batch.',
    'notice_date' => 'The dispatch date must be between approval and today.',
    'notice_required' => 'Record the advance notice for all entries before exporting.',
    'expired' => 'The collection date is in the past.',
    'cancel_exported' => 'An exported batch cannot simply be cancelled. Check its bank status.',
    'changed' => 'An invoice, mandate or club account has changed. Cancel and prepare a new batch.',
    'mandate' => 'Every invoice requires a complete active mandate.',
    'lead_time' => 'The agreed advance notice period is not met.',
    'unavailable' => 'Debit batches are currently unavailable.',
    'use_batch' => 'Open the saved debit batch and export it there.',
    'recharge_refund_title' => 'Fee recharge refund',
];
