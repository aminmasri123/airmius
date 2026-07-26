<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MailCenterController;
use App\Models\MailDelivery;
use App\Models\MailSenderAudit;
use App\Models\MailSenderSetting;
use App\Models\Setting;
use App\Support\TransactionalMail;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminMailController extends Controller
{
    public function dashboard(Request $request)
    {
        $this->authorizeManager($request);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['sent', 'failed', 'skipped', 'resolved'])],
            'type' => ['nullable', 'string', 'max:120'],
        ]);
        $deliveries = MailDelivery::query()
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('mail_type', $type))
            ->latest()->limit(100)->get()
            ->map(fn (MailDelivery $delivery) => [
                'id' => $delivery->id,
                'mail_type' => $delivery->mail_type,
                'status' => $delivery->status,
                'recipient' => ['name' => $delivery->recipient_name, 'email' => $delivery->recipient_email],
                'from_address' => $delivery->from_address,
                'mailer' => $delivery->mailer,
                'used_category' => $delivery->used_category,
                'primary_category' => $delivery->primary_category,
                'fallback_category' => $delivery->fallback_category,
                'error_message' => $delivery->error_message,
                'context' => collect($delivery->context ?: [])->except([
                    'token', 'password', 'secret', 'authorization', 'provider_payload',
                ])->all(),
                'resendable' => $this->resendable($delivery),
                'sent_at' => optional($delivery->sent_at)->toIso8601String(),
                'created_at' => optional($delivery->created_at)->toIso8601String(),
            ]);

        return response()->json(['data' => [
            'summary' => [
                'total' => MailDelivery::query()->count(),
                'sent' => MailDelivery::query()->where('status', 'sent')->count(),
                'failed' => MailDelivery::query()->where('status', 'failed')->count(),
                'skipped' => MailDelivery::query()->where('status', 'skipped')->count(),
                'resolved' => MailDelivery::query()->where('status', 'resolved')->count(),
                'last_24h' => MailDelivery::query()->where('created_at', '>=', now()->subDay())->count(),
            ],
            'queue' => [
                'pending_jobs' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
                'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
                'recent_failed_jobs' => $this->recentFailedJobs(),
            ],
            'deliveries' => $deliveries,
            'senders' => $this->senders(),
            'preferences' => $this->preferences(),
            'audits' => MailSenderAudit::query()->latest()->limit(20)->get()
                ->map(fn (MailSenderAudit $audit) => [
                    'id' => $audit->id,
                    'category' => $audit->category,
                    'action' => $audit->action,
                    'actor_id' => $audit->actor_id,
                    'created_at' => optional($audit->created_at)->toIso8601String(),
                ]),
            'categories' => array_keys(config('airmius_mail.senders', [])),
            'types' => MailDelivery::query()->select('mail_type')->distinct()
                ->orderBy('mail_type')->pluck('mail_type')->values(),
            'filters' => ['status' => $filters['status'] ?? '', 'type' => $filters['type'] ?? ''],
            'abilities' => [
                'manage' => true,
                'manage_secrets' => $request->user()?->hasRole('super_admin') === true,
            ],
        ]]);
    }

    public function updatePreferences(Request $request)
    {
        $this->authorizeManager($request);
        app(MailCenterController::class)->updatePreferences($request);

        return response()->json(['data' => $this->preferences()]);
    }

    public function updateSender(Request $request, string $category)
    {
        $this->authorizeManager($request);
        app(MailCenterController::class)->updateSender($request, $category);

        return response()->json([
            'data' => collect($this->senders())->firstWhere('category', $category),
        ]);
    }

    public function testSender(Request $request, string $category)
    {
        $this->authorizeManager($request);
        app(MailCenterController::class)->testSender($request, $category);

        return response()->json(['data' => ['processed' => true]]);
    }

    public function resend(Request $request, MailDelivery $mailDelivery)
    {
        $this->authorizeManager($request);
        app(MailCenterController::class)->resend($request, $mailDelivery);

        return response()->json(['data' => ['processed' => true]]);
    }

    public function resolve(Request $request, MailDelivery $mailDelivery)
    {
        $this->authorizeManager($request);
        app(MailCenterController::class)->resolve($mailDelivery);

        return response()->json([
            'data' => ['id' => $mailDelivery->id, 'status' => $mailDelivery->refresh()->status],
        ]);
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()?->can('system.manage'), 403);
    }

    private function senders(): array
    {
        $mail = app(TransactionalMail::class);
        $settings = MailSenderSetting::query()->get()->keyBy('category');

        return collect(config('airmius_mail.senders', []))
            ->map(function (array $sender, string $category) use ($mail, $settings) {
                $setting = $settings->get($category);
                $mailer = $sender['mailer'] ?? 'smtp';
                $config = config("mail.mailers.{$mailer}", []);
                $ready = is_array($config)
                    && (($config['transport'] ?? null) !== 'smtp'
                        || (filled($config['host'] ?? null)
                            && filled($config['port'] ?? null)
                            && filled($config['username'] ?? null)
                            && filled($config['password'] ?? null)));

                return [
                    'category' => $category,
                    'mailer' => $mailer,
                    'address' => $setting?->from_address ?: ($sender['address'] ?? null),
                    'name' => $setting?->from_name ?: ($sender['name'] ?? null),
                    'host' => $setting?->host ?: ($config['host'] ?? null),
                    'port' => $setting?->port ?: ($config['port'] ?? null),
                    'username' => $setting?->username ?: ($config['username'] ?? null),
                    'scheme' => $setting?->scheme ?: '',
                    'active' => $setting ? $setting->active : ! in_array($category, $mail->disabledCategories(), true),
                    'has_password' => filled($setting?->password) || filled($config['password'] ?? null),
                    'password_updated_at' => optional($setting?->password_updated_at)->toIso8601String(),
                    'ready' => $ready,
                    'disabled' => in_array($category, $mail->disabledCategories(), true)
                        || ($setting && ! $setting->active),
                ];
            })->values()->all();
    }

    private function preferences(): array
    {
        $stored = json_decode((string) Setting::valueFor('mail_preferences', '{}'), true);
        $stored = is_array($stored) ? $stored : [];
        $mail = app(TransactionalMail::class);

        return [
            'invoice_primary_category' => $stored['invoice_primary_category'] ?? $mail->invoicePrimaryCategory(),
            'invoice_fallback_category' => $stored['invoice_fallback_category'] ?? $mail->invoiceFallbackCategory(),
            'disabled_categories' => $mail->disabledCategories(),
        ];
    }

    private function recentFailedJobs(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [];
        }

        return DB::table('failed_jobs')->latest('failed_at')->limit(5)
            ->get(['id', 'queue', 'exception', 'failed_at'])
            ->map(fn ($job) => [
                'id' => $job->id,
                'queue' => $job->queue,
                'failed_at' => $job->failed_at
                    ? Carbon::parse($job->failed_at)->toIso8601String()
                    : null,
                'error' => str($job->exception)->before("\n")->limit(180)->toString(),
            ])->all();
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
        ], true) && filled($delivery->context['invoice_id'] ?? null);
    }
}
