<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Invoice;
use App\Support\ClubMembershipInput;
use App\Support\EncryptedIbanValue;
use App\Support\SepaData;
use Illuminate\Support\Facades\DB;

final class ClubSepaMandateService
{
    public function debtor(Club $club, Invoice $invoice): array
    {
        // user_id is the invoiced payer; membership_user_id is the beneficiary.
        $member = DB::table('club_user')->where('club_id', $club->id)
            ->where('user_id', $invoice->user_id)->lockForUpdate()->first();
        abort_unless($member && filled($invoice->user?->name), 422, __('sepa.payer_required'));
        $data = (array) $member;
        $data['sepa_iban'] = EncryptedIbanValue::decrypt($member->sepa_iban);
        abort_unless(SepaData::mandateReady($data), 422, __('sepa.mandate'));
        abort_if(filled($member->payment_method) && $member->payment_method !== 'sepa_debit', 422, __('sepa.payer_payment_method'));

        $duplicate = DB::table('club_user')->where('club_id', $club->id)
            ->where('user_id', '!=', $invoice->user_id)->where('sepa_mandate_active', true)
            ->where('sepa_mandate_reference', $member->sepa_mandate_reference)->exists();
        $duplicateExternal = DB::table('club_external_members')->where('club_id', $club->id)
            ->where(fn ($query) => $query->whereNull('linked_user_id')->orWhere('linked_user_id', '!=', $invoice->user_id))
            ->where('sepa_mandate_active', true)->where('sepa_mandate_reference', $member->sepa_mandate_reference)->exists();
        abort_if($duplicate || $duplicateExternal, 422, __('sepa.ambiguous_mandate'));

        return [
            'name' => $invoice->user->name, 'number' => $invoice->number, 'title' => $invoice->title,
            'sepa_iban' => ClubMembershipInput::normalizeIban($data['sepa_iban']),
            'sepa_bic' => ClubMembershipInput::normalizeBic($member->sepa_bic),
            'sepa_mandate_reference' => $member->sepa_mandate_reference,
            'sepa_mandate_signed_on' => $member->sepa_mandate_signed_on,
        ];
    }
}
