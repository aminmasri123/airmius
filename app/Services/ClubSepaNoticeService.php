<?php

namespace App\Services;

use App\Jobs\SendClubSepaNotice;
use App\Models\Club;
use App\Models\ClubSepaBatch;
use App\Models\ClubSepaNotice;
use App\Models\MailDelivery;
use App\Models\User;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use App\Support\LocalizedMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ClubSepaNoticeService
{
    public function prepare(ClubSepaBatch $batch, User $actor): ClubSepaBatch
    {
        return DB::transaction(function () use ($batch, $actor) {
            $batch = $this->lockBatch($batch->id);
            $this->validateDispatch($batch, $actor);
            foreach ($batch->items as $item) {
                if (ClubSepaNotice::where('club_sepa_batch_item_id', $item->id)->exists()) {
                    continue;
                }
                $user = $item->invoice->user;
                abort_unless($user && filter_var($user->email, FILTER_VALIDATE_EMAIL), 422, __('sepa.notice_recipient'));
                $mail = LocalizedMail::for($user);
                $debtor = $item->debtor_snapshot;
                $values = [
                    'name' => $debtor['name'], 'club' => $batch->creditor_snapshot['sepa_account_holder'],
                    'creditor' => $batch->creditor_snapshot['sepa_creditor_id'],
                    'amount' => $mail->money($item->amount_cents / 100, 'EUR'),
                    'date' => $mail->date($batch->collection_date),
                    'mandate' => $debtor['sepa_mandate_reference'], 'invoice' => $debtor['number'],
                    'iban' => substr($debtor['sepa_iban'], -4), 'reference' => $batch->reference,
                ];
                $batch->notices()->create([
                    'club_sepa_batch_item_id' => $item->id, 'requested_by' => $actor->id,
                    'content' => [
                        'email' => $user->email, 'name' => $debtor['name'], 'locale' => $mail->locale,
                        'subject' => Lang::get('sepa.notice_subject', $values, $mail->locale),
                        'body' => Lang::get('sepa.notice_body', $values, $mail->locale),
                    ],
                ]);
            }
            ClubAuditLog::record($batch->club, $actor, 'club.sepa.notices_prepared', $batch, ['reference' => $batch->reference]);

            return $batch->load('notices');
        });
    }

    public function enqueue(ClubSepaBatch $batch, User $actor): ClubSepaBatch
    {
        app(ClubSepaNoticeMailer::class)->transport();
        $ids = DB::transaction(function () use ($batch, $actor) {
            $batch = $this->lockBatch($batch->id);
            $this->recoverInterrupted($batch);
            $this->validateDispatch($batch, $actor);
            $notices = $batch->notices()->lockForUpdate()->get();
            abort_unless($notices->count() === $batch->items->count(), 422, __('sepa.notice_prepare_first'));
            $ids = [];
            foreach ($notices as $notice) {
                // A repeated enqueue may repair a lost queue handoff. The worker claims
                // each row once; sent/uncertain rows must never be retried automatically.
                if (in_array($notice->status, ['prepared', 'queued', 'blocked'], true)) {
                    $this->checkRecipient($notice);
                    $notice->update(['status' => 'queued', 'requested_by' => $actor->id, 'error_code' => null]);
                    $ids[] = $notice->id;
                }
            }
            ClubAuditLog::record($batch->club, $actor, 'club.sepa.notices_queued', $batch, ['reference' => $batch->reference, 'count' => count($ids)]);

            return $ids;
        });
        foreach ($ids as $id) {
            SendClubSepaNotice::dispatch($id)->afterCommit();
        }

        return $batch->fresh()->load('notices');
    }

    public function deliver(int $id): void
    {
        $candidate = ClubSepaNotice::find($id);
        if (! $candidate) {
            return;
        }
        $notice = DB::transaction(function () use ($candidate) {
            $batch = $this->lockBatch($candidate->club_sepa_batch_id);
            $notice = ClubSepaNotice::lockForUpdate()->findOrFail($candidate->id);
            if ($notice->status !== 'queued') {
                return null;
            }
            try {
                // Each worker rechecks its own invoice. The full batch is checked
                // at enqueue and again at export, avoiding quadratic worker reads.
                $this->validateDispatch($batch, User::find($notice->requested_by), $notice->club_sepa_batch_item_id);
                $this->checkRecipient($notice);
                app(ClubSepaNoticeMailer::class)->transport();
            } catch (Throwable) {
                $notice->update(['status' => 'blocked', 'error_code' => 'dispatch_blocked']);

                return null;
            }
            $notice->update(['status' => 'sending', 'started_at' => now(), 'error_code' => null]);

            return $notice;
        });
        if (! $notice) {
            return;
        }
        // Commit the claim BEFORE external I/O. A crash leaves an uncertain claim,
        // never a ready row that another worker may silently send again.
        try {
            $messageId = app(ClubSepaNoticeMailer::class)->send($notice);
        } catch (Throwable) {
            ClubSepaNotice::whereKey($id)->where('status', 'sending')
                ->update(['status' => 'uncertain', 'error_code' => 'transport_uncertain']);

            return;
        }
        DB::transaction(function () use ($notice, $messageId) {
            $batch = $this->lockBatch($notice->club_sepa_batch_id);
            $notice = ClubSepaNotice::lockForUpdate()->findOrFail($notice->id);
            if ($notice->status !== 'sending') {
                return;
            }
            $notice->update([
                'status' => 'sent',
                'sent_at' => now(),
                'message_id' => $messageId,
                'provider_status' => 'pending',
            ]);
            MailDelivery::create([
                'dedupe_key' => 'club-sepa-notice:'.$notice->id, 'mail_type' => 'club_sepa_notice',
                'recipient_id' => $notice->item->invoice->user_id,
                'recipient_email' => $notice->content['email'], 'recipient_name' => $notice->content['name'],
                'status' => 'sent', 'primary_category' => 'billing', 'used_category' => 'billing', 'sent_at' => $notice->sent_at,
                'context' => [
                    'batch_id' => $batch->id,
                    'notice_id' => $notice->id,
                    'message_id' => $messageId,
                    'provider_status' => 'pending',
                ],
            ]);
            ClubAuditLog::record($batch->club, User::find($notice->requested_by), 'club.sepa.notice_sent', $batch, ['notice_id' => $notice->id]);
            $notices = $batch->notices()->get();
            if ($batch->status === 'approved' && $notices->count() === $batch->items()->count()
                && $notices->every(fn ($entry) => $entry->status === 'sent')) {
                $latest = $notices->max('sent_at')->copy()->startOfDay();
                if ($batch->collection_date->gte($latest->copy()->addDays($batch->notice_days))) {
                    $batch->update([
                        'status' => 'notified', 'notice_sent_on' => $latest, 'notice_channel' => 'email',
                        'notice_reference' => 'SEPA notices: '.$notices->pluck('id')->join(', '),
                        'notified_by' => $notice->requested_by,
                    ]);
                }
            }
        });
    }

    public function stopPending(ClubSepaBatch $batch): void
    {
        if (! Schema::hasTable('club_sepa_notices')) {
            return;
        }
        $this->recoverInterrupted($batch);
        abort_if($batch->notices()->where('status', 'sending')->exists(), 422, __('sepa.notice_sending'));
        $batch->notices()->whereIn('status', ['prepared', 'queued', 'blocked'])->update(['status' => 'cancelled']);
    }

    private function recoverInterrupted(ClubSepaBatch $batch): void
    {
        // Queue timeouts are 60 seconds. Ten minutes gives interrupted workers ample
        // time to stop; uncertainty still requires external verification, not retry.
        $batch->notices()->where('status', 'sending')->where('started_at', '<', now()->subMinutes(10))
            ->update(['status' => 'uncertain', 'error_code' => 'transport_uncertain']);
    }

    private function checkRecipient(ClubSepaNotice $notice): void
    {
        abort_unless($notice->item->invoice->user?->email === $notice->content['email'], 422, __('sepa.notice_recipient_changed'));
    }

    private function validateDispatch(ClubSepaBatch $batch, ?User $actor, ?int $itemId = null): void
    {
        abort_unless($actor && ClubPermissions::allows($batch->club, $actor, ClubPermissions::FINANCE_MANAGE), 403);
        app(PlanFeatureService::class)->ensureAllows($batch->club, 'sepa_export');
        abort_unless($batch->status === 'approved', 422, __('sepa.state'));
        abort_if($batch->collection_date->lt(today()->addDays($batch->notice_days)), 422, __('sepa.lead_time'));
        app(ClubSepaBatchService::class)->assertUnchanged($batch, $itemId);
    }

    private function lockBatch(int $id): ClubSepaBatch
    {
        $batch = ClubSepaBatch::findOrFail($id);
        Club::lockForUpdate()->findOrFail($batch->club_id);

        return ClubSepaBatch::lockForUpdate()->findOrFail($id);
    }
}
