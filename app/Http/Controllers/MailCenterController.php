<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\MailDelivery;
use App\Models\MailSenderAudit;
use App\Models\MailSenderSetting;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AdminInvoiceCreated;
use App\Notifications\AdminInvoiceStatusUpdated;
use App\Notifications\ClubInvoiceCreated;
use App\Notifications\InactiveAccountNotice;
use App\Support\TransactionalMail;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MailCenterController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['sent', 'failed', 'skipped', 'resolved'])],
            'type' => ['nullable', 'string', 'max:120'],
        ]);

        $deliveries = MailDelivery::query()
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('mail_type', $type))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (MailDelivery $delivery) => [
                'id' => $delivery->id,
                'mail_type' => $delivery->mail_type,
                'status' => $delivery->status,
                'recipient' => [
                    'name' => $delivery->recipient_name,
                    'email' => $delivery->recipient_email,
                ],
                'from_address' => $delivery->from_address,
                'mailer' => $delivery->mailer,
                'used_category' => $delivery->used_category,
                'primary_category' => $delivery->primary_category,
                'fallback_category' => $delivery->fallback_category,
                'error_message' => $delivery->error_message,
                'context' => $delivery->context ?: [],
                'resendable' => $this->resendable($delivery),
                'sent_at' => $delivery->sent_at?->format('d.m.Y H:i'),
                'created_at' => $delivery->created_at?->format('d.m.Y H:i'),
            ]);

        return Inertia::render('Auth/Dashboard/Admin/MailCenter/Index', [
            'deliveries' => $deliveries,
            'summary' => $this->summary(),
            'queue' => $this->queueSummary(),
            'senders' => $this->senders(),
            'preferences' => $this->preferences(),
            'canManageSecrets' => $this->canManageSecrets($request),
            'audits' => $this->audits(),
            'categories' => array_keys(config('airmius_mail.senders', [])),
            'types' => MailDelivery::query()->select('mail_type')->distinct()->orderBy('mail_type')->pluck('mail_type')->values(),
            'filters' => [
                'status' => $filters['status'] ?? '',
                'type' => $filters['type'] ?? '',
            ],
        ]);
    }

    public function updatePreferences(Request $request)
    {
        $categories = array_keys(config('airmius_mail.senders', []));

        $data = $request->validate([
            'invoice_primary_category' => ['required', Rule::in($categories)],
            'invoice_fallback_category' => ['nullable', Rule::in($categories)],
            'disabled_categories' => ['nullable', 'array'],
            'disabled_categories.*' => [Rule::in($categories)],
        ]);

        $data['disabled_categories'] = collect($data['disabled_categories'] ?? [])
            ->reject(fn (string $category) => $category === 'system')
            ->values()
            ->all();

        Setting::setValue('mail_preferences', json_encode($data, JSON_PRETTY_PRINT));

        return back()->with('success', 'Mail-Regeln wurden aktualisiert.');
    }

    public function updateSender(Request $request, string $category)
    {
        $this->authorizeSecrets($request);

        abort_unless(array_key_exists($category, config('airmius_mail.senders', [])), 404);

        $data = $request->validate([
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'email', 'max:255'],
            'new_password' => ['nullable', 'string', 'max:255'],
            'scheme' => ['nullable', 'string', Rule::in(['', 'smtp', 'smtps'])],
            'active' => ['boolean'],
        ]);

        if (filled($data['new_password'] ?? null)) {
            $this->authorizeSecretPasswordChange($request);
        }

        $defaults = config("airmius_mail.senders.{$category}", []);
        $setting = MailSenderSetting::query()->firstOrNew(['category' => $category]);
        $before = $this->safeSenderSnapshot($category, $setting->exists ? $setting : null);

        $setting->fill([
            'mailer' => $defaults['mailer'] ?? 'smtp',
            'from_address' => $data['from_address'],
            'from_name' => $data['from_name'] ?: config('mail.from.name'),
            'host' => $data['host'] ?: config('mail.mailers.'.($defaults['mailer'] ?? 'smtp').'.host'),
            'port' => $data['port'] ?: config('mail.mailers.'.($defaults['mailer'] ?? 'smtp').'.port'),
            'username' => $data['username'] ?: $data['from_address'],
            'scheme' => $data['scheme'] ?: null,
            'active' => (bool) ($data['active'] ?? false),
            'updated_by' => $request->user()->id,
        ]);

        if (filled($data['new_password'] ?? null)) {
            $setting->password = $data['new_password'];
            $setting->password_updated_at = now();
        }

        $setting->save();

        $this->auditSender($request, $category, 'sender.updated', $before, $this->safeSenderSnapshot($category, $setting), [
            'password_changed' => filled($data['new_password'] ?? null),
        ]);

        return back()->with('success', 'Mailbox wurde aktualisiert.');
    }

    public function testSender(Request $request, string $category)
    {
        $this->authorizeSecrets($request);

        abort_unless(array_key_exists($category, config('airmius_mail.senders', [])), 404);

        $transport = app(TransactionalMail::class)->transportFor($category);

        try {
            Mail::mailer($transport['mailer'])
                ->raw('Dies ist eine Testmail fuer die Mailbox-Kategorie '.$category.'.', function ($message) use ($request, $transport, $category) {
                    $message
                        ->to($request->user()->email)
                        ->from($transport['address'], $transport['name'])
                        ->subject('Airmius Testmail: '.$category);
                });

            $this->auditSender($request, $category, 'sender.test_sent', null, [
                'category' => $category,
                'mailer' => $transport['mailer'],
                'from_address' => $transport['address'],
                'test_recipient' => $request->user()->email,
            ]);

            return back()->with('success', 'Testmail wurde gesendet.');
        } catch (\Throwable $exception) {
            $this->auditSender($request, $category, 'sender.test_failed', null, [
                'category' => $category,
                'mailer' => $transport['mailer'],
                'from_address' => $transport['address'],
                'error' => str($exception->getMessage())->limit(180)->toString(),
            ]);

            return back()->with('error', 'Testmail ist fehlgeschlagen.');
        }
    }

    public function resend(Request $request, MailDelivery $mailDelivery)
    {
        $categories = array_keys(config('airmius_mail.senders', []));

        $data = $request->validate([
            'category' => ['required', Rule::in($categories)],
        ]);

        $recipient = $mailDelivery->recipient_id
            ? User::query()->find($mailDelivery->recipient_id)
            : null;

        if (! $recipient?->email) {
            return back()->with('error', 'Empfaenger konnte nicht gefunden werden.');
        }

        $notification = $this->notificationFor($mailDelivery);

        if (! $notification) {
            return back()->with('error', 'Diese Mail-Art kann noch nicht automatisch erneut gesendet werden.');
        }

        $transport = app(TransactionalMail::class)->transportFor($data['category']);

        try {
            $recipient->notify($notification($transport));

            MailDelivery::create([
                'dedupe_key' => 'manual-resend:'.$mailDelivery->id.':'.now()->timestamp,
                'mail_type' => $mailDelivery->mail_type,
                'recipient_id' => $recipient->id,
                'recipient_email' => $recipient->email,
                'recipient_name' => $recipient->name,
                'status' => 'sent',
                'primary_category' => $data['category'],
                'used_category' => $transport['category'],
                'mailer' => $transport['mailer'],
                'from_address' => $transport['address'],
                'context' => ($mailDelivery->context ?: []) + [
                    'resent_from_delivery_id' => $mailDelivery->id,
                    'manual_resend' => true,
                ],
                'sent_at' => now(),
            ]);

            return back()->with('success', 'Mail wurde erneut gesendet.');
        } catch (\Throwable $exception) {
            MailDelivery::create([
                'dedupe_key' => 'manual-resend-failed:'.$mailDelivery->id.':'.now()->timestamp,
                'mail_type' => $mailDelivery->mail_type,
                'recipient_id' => $recipient->id,
                'recipient_email' => $recipient->email,
                'recipient_name' => $recipient->name,
                'status' => 'failed',
                'primary_category' => $data['category'],
                'used_category' => $transport['category'],
                'mailer' => $transport['mailer'],
                'from_address' => $transport['address'],
                'error_message' => $exception->getMessage(),
                'context' => ($mailDelivery->context ?: []) + [
                    'resent_from_delivery_id' => $mailDelivery->id,
                    'manual_resend' => true,
                ],
            ]);

            return back()->with('error', 'Erneuter Versand ist fehlgeschlagen.');
        }
    }

    public function resolve(MailDelivery $mailDelivery)
    {
        $mailDelivery->update([
            'status' => 'resolved',
            'error_message' => $mailDelivery->error_message ?: 'Manuell erledigt.',
            'context' => ($mailDelivery->context ?: []) + [
                'resolved_by' => auth()->id(),
                'resolved_at' => now()->toIso8601String(),
            ],
        ]);

        return back()->with('success', 'Mail-Eintrag wurde erledigt.');
    }

    private function summary(): array
    {
        return [
            'total' => MailDelivery::query()->count(),
            'sent' => MailDelivery::query()->where('status', 'sent')->count(),
            'failed' => MailDelivery::query()->where('status', 'failed')->count(),
            'skipped' => MailDelivery::query()->where('status', 'skipped')->count(),
            'resolved' => MailDelivery::query()->where('status', 'resolved')->count(),
            'last_24h' => MailDelivery::query()->where('created_at', '>=', now()->subDay())->count(),
        ];
    }

    private function queueSummary(): array
    {
        return [
            'pending_jobs' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
            'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            'recent_failed_jobs' => Schema::hasTable('failed_jobs')
                ? DB::table('failed_jobs')
                    ->latest('failed_at')
                    ->limit(5)
                    ->get(['id', 'queue', 'exception', 'failed_at'])
                    ->map(fn ($job) => [
                        'id' => $job->id,
                        'queue' => $job->queue,
                        'failed_at' => $job->failed_at ? Carbon::parse($job->failed_at)->format('d.m.Y H:i') : null,
                        'error' => str($job->exception)->before("\n")->limit(180)->toString(),
                    ])
                : [],
        ];
    }

    private function senders(): array
    {
        $mail = app(TransactionalMail::class);
        $settings = MailSenderSetting::query()->get()->keyBy('category');

        return collect(config('airmius_mail.senders', []))
            ->map(function (array $sender, string $category) use ($mail, $settings) {
                $setting = $settings->get($category);
                $resolved = $mail->transportFor($category);

                return [
                    'category' => $category,
                    'mailer' => $sender['mailer'] ?? null,
                    'address' => $setting?->from_address ?: ($sender['address'] ?? null),
                    'name' => $setting?->from_name ?: ($sender['name'] ?? null),
                    'host' => $setting?->host ?: config('mail.mailers.'.($sender['mailer'] ?? 'smtp').'.host'),
                    'port' => $setting?->port ?: config('mail.mailers.'.($sender['mailer'] ?? 'smtp').'.port'),
                    'username' => $setting?->username ?: config('mail.mailers.'.($sender['mailer'] ?? 'smtp').'.username'),
                    'scheme' => $setting?->scheme ?: '',
                    'active' => $setting ? $setting->active : ! in_array($category, $mail->disabledCategories(), true),
                    'has_password' => filled($setting?->password) || filled(config('mail.mailers.'.($sender['mailer'] ?? 'smtp').'.password')),
                    'password_updated_at' => $setting?->password_updated_at?->format('d.m.Y H:i'),
                    'resolved' => $resolved,
                    'ready' => $this->senderReady($resolved['mailer'] ?? ($sender['mailer'] ?? null)),
                    'disabled' => in_array($category, $mail->disabledCategories(), true) || ($setting && ! $setting->active),
                ];
            })
            ->values()
            ->all();
    }

    private function audits(): array
    {
        return MailSenderAudit::query()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (MailSenderAudit $audit) => [
                'id' => $audit->id,
                'category' => $audit->category,
                'action' => $audit->action,
                'actor_id' => $audit->actor_id,
                'before' => $audit->before,
                'after' => $audit->after,
                'created_at' => $audit->created_at?->format('d.m.Y H:i'),
            ])
            ->all();
    }

    private function preferences(): array
    {
        $raw = Setting::valueFor('mail_preferences', '{}');
        $stored = json_decode((string) $raw, true);

        return [
            'invoice_primary_category' => $stored['invoice_primary_category'] ?? app(TransactionalMail::class)->invoicePrimaryCategory(),
            'invoice_fallback_category' => $stored['invoice_fallback_category'] ?? app(TransactionalMail::class)->invoiceFallbackCategory(),
            'disabled_categories' => app(TransactionalMail::class)->disabledCategories(),
        ];
    }

    private function senderReady(?string $mailer): bool
    {
        if (! $mailer) {
            return false;
        }

        $config = config("mail.mailers.{$mailer}");

        if (! is_array($config)) {
            return false;
        }

        if (($config['transport'] ?? null) !== 'smtp') {
            return true;
        }

        return filled($config['host'] ?? null)
            && filled($config['port'] ?? null)
            && filled($config['username'] ?? null)
            && filled($config['password'] ?? null);
    }

    private function canManageSecrets(Request $request): bool
    {
        return (bool) $request->user()?->hasRole('super_admin');
    }

    private function authorizeSecrets(Request $request): void
    {
        abort_unless($this->canManageSecrets($request), 403);
    }

    private function authorizeSecretPasswordChange(Request $request): void
    {
        $this->authorizeSecrets($request);

        if (! config('airmius_mail.require_2fa_for_secret_changes', true)) {
            return;
        }

        abort_unless(
            filled($request->user()?->two_factor_secret) && filled($request->user()?->two_factor_confirmed_at),
            403,
            'Bitte aktiviere zuerst 2FA, bevor du Mail-Passwoerter aenderst.'
        );
    }

    private function safeSenderSnapshot(string $category, ?MailSenderSetting $setting): array
    {
        $defaults = config("airmius_mail.senders.{$category}", []);

        return [
            'category' => $category,
            'mailer' => $setting?->mailer ?: ($defaults['mailer'] ?? null),
            'from_address' => $setting?->from_address ?: ($defaults['address'] ?? null),
            'from_name' => $setting?->from_name ?: ($defaults['name'] ?? null),
            'host' => $setting?->host,
            'port' => $setting?->port,
            'username' => $setting?->username,
            'scheme' => $setting?->scheme,
            'active' => $setting?->active,
            'has_password' => filled($setting?->password),
            'password_updated_at' => $setting?->password_updated_at?->toIso8601String(),
        ];
    }

    private function auditSender(Request $request, string $category, string $action, ?array $before, ?array $after, array $extra = []): void
    {
        MailSenderAudit::create([
            'category' => $category,
            'action' => $action,
            'actor_id' => $request->user()?->id,
            'before' => $before,
            'after' => $after ? array_merge($after, $extra) : $extra,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);
    }

    private function resendable(MailDelivery $delivery): bool
    {
        if (! $delivery->recipient_id) {
            return false;
        }

        if (str_starts_with((string) $delivery->mail_type, 'inactive_account.')) {
            return true;
        }

        return in_array($delivery->mail_type, [
            'invoice.created',
            'invoice.status_updated',
            'club.invoice.created',
            'recurring_contribution.invoice.created',
        ], true) && $delivery->recipient_id && ($delivery->context['invoice_id'] ?? null);
    }

    private function notificationFor(MailDelivery $delivery): ?\Closure
    {
        if (str_starts_with((string) $delivery->mail_type, 'inactive_account.')) {
            $stage = str($delivery->mail_type)->after('inactive_account.')->toString();

            return fn (array $transport) => new InactiveAccountNotice(
                $stage,
                $delivery->context['scheduled_at'] ?? null,
                $transport['mailer'],
                $transport['address'],
                $transport['name'],
            );
        }

        $invoiceId = $delivery->context['invoice_id'] ?? null;
        $invoice = $invoiceId ? Invoice::query()->find($invoiceId) : null;

        if (! $invoice) {
            return null;
        }

        return match ($delivery->mail_type) {
            'invoice.created' => fn (array $transport) => new AdminInvoiceCreated(
                $invoice,
                $transport['mailer'],
                $transport['address'],
                $transport['name'],
            ),
            'invoice.status_updated' => fn (array $transport) => new AdminInvoiceStatusUpdated(
                $invoice,
                $delivery->context['old_status'] ?? null,
                $transport['mailer'],
                $transport['address'],
                $transport['name'],
            ),
            'club.invoice.created',
            'recurring_contribution.invoice.created' => fn (array $transport) => new ClubInvoiceCreated(
                $invoice->loadMissing('club'),
                $transport['mailer'],
                $transport['address'],
                $transport['name'],
            ),
            default => null,
        };
    }
}
