<?php

namespace App\Http\Controllers;

use App\Models\ClubSepaFeeRechargeCredit;
use App\Services\ClubSepaFeeRechargeCreditDocumentService;
use Illuminate\Http\Response;

final class ClubSepaFeeRechargeCreditDocumentController extends Controller
{
    public function __invoke(ClubSepaFeeRechargeCredit $credit, ClubSepaFeeRechargeCreditDocumentService $documents): Response
    {
        return response($documents->pdf($credit), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$credit->credit_note_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
