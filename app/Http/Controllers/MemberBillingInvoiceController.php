<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\SubscriptionInvoice;
use App\Services\ClubMembershipInvoicePdf;
use App\Support\AppNotification;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;

class MemberBillingInvoiceController extends Controller
{
    public function download(Request $request, string $kind, int $invoice)
    {
        if ($kind === 'subscription_invoice') {
            $owned = SubscriptionInvoice::where('user_id', $request->user()->id)->findOrFail($invoice);

            return app(SubscriptionInvoiceController::class)->download($request, $owned);
        }
        abort_unless($kind === 'club_invoice', 404);
        $owned = Invoice::ownedBy($request->user())->findOrFail($invoice);

        return response(app(ClubMembershipInvoicePdf::class)->render($owned), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="membership-invoice-'.$owned->id.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function question(Request $request, string $kind, int $invoice)
    {
        abort_unless($kind === 'club_invoice', 404);
        $owned = Invoice::ownedBy($request->user())->with('club.owner')->findOrFail($invoice);
        abort_unless($owned->club, 422);
        $data = $request->validate(['message' => ['required', 'string', 'min:5', 'max:2000']]);
        $club = $owned->club;
        $entry = ClubAuditLog::record($club, $request->user(), 'club.invoice.member_question', $owned, [
            'invoice_number' => $owned->number, 'message' => $data['message'],
        ]);
        $recipients = $club->users()->get()->filter(fn ($user) => ClubPermissions::allows($club, $user, ClubPermissions::FINANCE_MANAGE))
            ->concat([$club->owner])->filter()->unique('id');
        foreach ($recipients as $recipient) {
            AppNotification::sendLocalized($recipient, 'club.invoice.member_question',
                'member_billing.question_title', 'member_billing.question_body',
                ['member' => $request->user()->name, 'number' => $owned->number, 'message' => $data['message']],
                ['club_id' => $club->id, 'invoice_id' => $owned->id,
                    'url' => route('auth.club-memberships.index', ['club_id' => $club->id, 'tab' => 'invoices'])],
                ['dedupe_key' => 'invoice-question-'.$entry->id]);
        }

        return response()->json(['message' => __('member_billing.question_sent')]);
    }
}
