<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubSepaBatch;
use App\Models\ClubSepaBatchItem;
use App\Models\Invoice;
use App\Models\User;
use App\Support\ClubAuditLog;
use App\Support\ClubMembershipInput;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ClubSepaBatchService
{
    public function create(Club $club, User $actor, array $data): ClubSepaBatch
    {
        return DB::transaction(function () use ($club, $actor, $data) {
            $club = Club::query()->lockForUpdate()->findOrFail($club->id);
            $creditor = $this->creditor($club);
            abort_unless($creditor['sepa_creditor_id'] && $creditor['sepa_iban'], 422, __('sepa.credentials'));
            $this->checkLeadTime($data['collection_date'], $data['notice_days'], today());
            $invoices = Invoice::query()->where('club_id', $club->id)
                ->whereIn('id', $data['invoice_ids'])->orderBy('id')->lockForUpdate()->get();
            abort_unless($invoices->count() === count($data['invoice_ids']), 422, __('sepa.invoices'));
            $batch = ClubSepaBatch::create([
                'club_id' => $club->id, 'created_by' => $actor->id,
                'reference' => 'AIR-'.Str::upper(Str::random(24)),
                'collection_date' => $data['collection_date'], 'notice_days' => $data['notice_days'],
                'creditor_snapshot' => $creditor, 'status' => 'draft',
            ]);
            foreach ($invoices as $invoice) {
                abort_if(ClubSepaBatchItem::where('reserved_invoice_id', $invoice->id)->exists(), 422, __('sepa.reserved'));
                abort_unless(in_array($invoice->status, ['open', 'overdue'], true) && $invoice->outstandingCents() > 0, 422, __('sepa.invoices'));
                $batch->items()->create([
                    'invoice_id' => $invoice->id, 'reserved_invoice_id' => $invoice->id,
                    'amount_cents' => $invoice->outstandingCents(), 'debtor_snapshot' => $this->debtor($club, $invoice),
                ]);
            }
            $this->audit($batch, $actor, 'created');

            return $batch;
        });
    }

    public function approve(ClubSepaBatch $batch, User $actor): ClubSepaBatch
    {
        return $this->change($batch, function ($locked) use ($actor) {
            abort_unless($locked->status === 'draft', 422, __('sepa.state'));
            abort_if((int) $locked->created_by === $actor->id, 422, __('sepa.second_person'));
            $this->assertUnchanged($locked);
            $this->checkLeadTime($locked->collection_date, $locked->notice_days, today());
            $locked->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->audit($locked, $actor, 'approved');
        });
    }

    public function recordNotice(ClubSepaBatch $batch, User $actor, array $data): ClubSepaBatch
    {
        return $this->change($batch, function ($locked) use ($actor, $data) {
            abort_unless($locked->status === 'approved', 422, __('sepa.state'));
            app(ClubSepaNoticeService::class)->stopPending($locked);
            $this->assertUnchanged($locked);
            $sent = Carbon::parse($data['sent_on'])->startOfDay();
            abort_if($sent->isFuture() || $sent->lt($locked->approved_at->copy()->startOfDay()), 422, __('sepa.notice_date'));
            $this->checkLeadTime($locked->collection_date, $locked->notice_days, $sent);
            $locked->update([
                'status' => 'notified', 'notice_sent_on' => $sent,
                'notice_channel' => $data['channel'], 'notice_reference' => $data['reference'],
                'notified_by' => $actor->id,
            ]);
            $this->audit($locked, $actor, 'notice_recorded');
        });
    }

    public function export(ClubSepaBatch $batch, User $actor): string
    {
        $locked = $this->change($batch, function ($locked) use ($actor) {
            // A download retry returns exactly the stored bank instruction, never a new debit.
            if ($locked->status === 'exported') {
                return;
            }
            abort_unless($locked->status === 'notified', 422, __('sepa.notice_required'));
            abort_if($locked->collection_date->lt(today()), 422, __('sepa.expired'));
            $this->checkLeadTime($locked->collection_date, $locked->notice_days, $locked->notice_sent_on);
            $this->assertUnchanged($locked);
            $creditor = new Club($locked->creditor_snapshot);
            $creditor->id = $locked->club_id;
            $invoices = collect();
            $memberships = collect();
            foreach ($locked->items as $item) {
                $snapshot = $item->debtor_snapshot;
                // Unsaved models ensure XML generation uses frozen amounts and names.
                $invoice = new Invoice([
                    'number' => $snapshot['number'], 'title' => $snapshot['title'],
                    'user_id' => $item->id, 'amount' => $item->amount_cents / 100,
                ]);
                $invoice->setRelation('user', new User(['name' => $snapshot['name']]));
                $invoices->push($invoice);
                $memberships->put($item->id, (object) $snapshot);
            }
            $xml = app(ClubMembershipSepaService::class)->buildDebitXml(
                $creditor, $invoices, $memberships, $locked->collection_date->toDateString(), $locked->reference,
            );
            $locked->update(['status' => 'exported', 'export_xml' => $xml, 'exported_at' => now()]);
            Invoice::whereIn('id', $locked->items->pluck('invoice_id'))->update(['sepa_exported_at' => now()]);
            $this->audit($locked, $actor, 'exported');
        });

        return $locked->export_xml;
    }

    public function cancel(ClubSepaBatch $batch, User $actor, string $reason): ClubSepaBatch
    {
        return $this->change($batch, function ($locked) use ($actor, $reason) {
            abort_unless(in_array($locked->status, ['draft', 'approved', 'notified'], true), 422, __('sepa.cancel_exported'));
            app(ClubSepaNoticeService::class)->stopPending($locked);
            $locked->items()->update(['reserved_invoice_id' => null]);
            $locked->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $this->audit($locked, $actor, 'cancelled');
        });
    }

    private function change(ClubSepaBatch $batch, callable $action): ClubSepaBatch
    {
        return DB::transaction(function () use ($batch, $action) {
            Club::query()->lockForUpdate()->findOrFail($batch->club_id);
            $locked = ClubSepaBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $action($locked);

            return $locked->fresh();
        });
    }

    public function assertUnchanged(ClubSepaBatch $batch, ?int $itemId = null): void
    {
        abort_unless($this->creditor($batch->club) === $batch->creditor_snapshot, 422, __('sepa.changed'));
        $batch->load(['items' => fn ($query) => $query->when($itemId !== null, fn ($query) => $query->whereKey($itemId))]);
        abort_if($itemId !== null && $batch->items->count() !== 1, 422, __('sepa.changed'));
        $invoices = Invoice::whereIn('id', $batch->items->pluck('invoice_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($batch->items as $item) {
            $invoice = $invoices->get($item->invoice_id);
            abort_unless($invoice && in_array($invoice->status, ['open', 'overdue'], true)
                && $invoice->outstandingCents() === $item->amount_cents
                && $this->debtor($batch->club, $invoice) === $item->debtor_snapshot, 422, __('sepa.changed'));
        }
    }

    private function creditor(Club $club): array
    {
        return [
            'name' => $club->name, 'sepa_account_holder' => $club->sepa_account_holder ?: $club->name,
            'sepa_creditor_id' => $club->sepa_creditor_id,
            'sepa_iban' => ClubMembershipInput::normalizeIban($club->sepa_iban),
            'sepa_bic' => ClubMembershipInput::normalizeBic($club->sepa_bic),
        ];
    }

    private function debtor(Club $club, Invoice $invoice): array
    {
        $member = DB::table('club_user')->where('club_id', $club->id)->where('user_id', $invoice->user_id)->lockForUpdate()->first();
        abort_unless($member && $member->sepa_mandate_active && filled($member->sepa_iban)
            && filled($member->sepa_mandate_reference) && filled($member->sepa_mandate_signed_on)
            && Carbon::parse($member->sepa_mandate_signed_on)->lte(today()), 422, __('sepa.mandate'));

        return [
            'name' => $invoice->user?->name, 'number' => $invoice->number, 'title' => $invoice->title,
            'sepa_iban' => ClubMembershipInput::normalizeIban($member->sepa_iban),
            'sepa_bic' => ClubMembershipInput::normalizeBic($member->sepa_bic),
            'sepa_mandate_reference' => $member->sepa_mandate_reference,
            'sepa_mandate_signed_on' => Carbon::parse($member->sepa_mandate_signed_on)->toDateString(),
        ];
    }

    private function checkLeadTime($date, int $days, $sent): void
    {
        abort_if(Carbon::parse($date)->startOfDay()->lt(Carbon::parse($sent)->startOfDay()->addDays($days)), 422, __('sepa.lead_time'));
    }

    private function audit(ClubSepaBatch $batch, User $actor, string $event): void
    {
        ClubAuditLog::record($batch->club, $actor, 'club.sepa.'.$event, $batch, ['reference' => $batch->reference]);
    }
}
