<?php

namespace App\Support;

use App\Models\MailDelivery;
use App\Models\MailSenderSetting;
use App\Models\Setting;
use Closure;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TransactionalMail
{
    public function applyToMessage(MailMessage $message, string $category): MailMessage
    {
        $transport = $this->transportFor($category);

        if ($transport['mailer']) {
            $message->mailer($transport['mailer']);
        }

        if ($transport['address']) {
            $message->from($transport['address'], $transport['name'] ?: config('mail.from.name'));
        }

        return $message;
    }

    public function transportFor(string $category): array
    {
        if ($this->categoryDisabled($category) && $category !== 'system') {
            return $this->transportFor('system');
        }

        $sender = config("airmius_mail.senders.{$category}") ?: config('airmius_mail.senders.system', []);
        $stored = MailSenderSetting::query()->where('category', $category)->first();

        if ($stored && ! $stored->active && $category !== 'system') {
            return $this->transportFor('system');
        }

        if ($stored) {
            $sender = array_merge($sender, [
                'mailer' => $stored->mailer,
                'address' => $stored->from_address,
                'name' => $stored->from_name ?: ($sender['name'] ?? config('mail.from.name')),
            ]);
        }

        if (app()->environment('local') && ! config('airmius_mail.allow_real_mail_in_local')) {
            return [
                'category' => $category,
                'mailer' => 'log',
                'address' => $sender['address'] ?? config('mail.from.address'),
                'name' => $sender['name'] ?? config('mail.from.name'),
            ];
        }

        $transport = [
            'category' => $category,
            'mailer' => $sender['mailer'] ?? config('mail.default'),
            'address' => $sender['address'] ?? config('mail.from.address'),
            'name' => $sender['name'] ?? config('mail.from.name'),
        ];

        if ($stored) {
            $this->applyStoredMailerConfig($stored);
        }

        if (! $this->mailerIsReady($transport['mailer']) && $category !== 'system') {
            return $this->transportFor('system');
        }

        return $transport;
    }

    public function invoicePrimaryCategory(): string
    {
        return $this->preference('invoice_primary_category', config('airmius_mail.invoice_primary_category', 'system'));
    }

    public function invoiceFallbackCategory(): ?string
    {
        return $this->preference('invoice_fallback_category', config('airmius_mail.invoice_fallback_category', 'billing'));
    }

    public function disabledCategories(): array
    {
        $disabled = $this->preference('disabled_categories', []);

        return is_array($disabled) ? $disabled : [];
    }

    public function notifyWithFallback(
        object $notifiable,
        Closure $notificationFactory,
        string $primaryCategory,
        ?string $fallbackCategory,
        string $dedupeKey,
        int $throttleSeconds,
        array $context = [],
    ): bool {
        $cacheKey = $this->cacheKey($dedupeKey);

        if (! Cache::add($cacheKey, true, now()->addSeconds(max(1, $throttleSeconds)))) {
            Log::info('Transactional mail skipped by throttle.', $context + [
                'dedupe_key' => $dedupeKey,
                'throttle_seconds' => $throttleSeconds,
            ]);

            $this->recordDelivery($notifiable, [
                'dedupe_key' => $dedupeKey,
                'mail_type' => $context['mail_type'] ?? 'unknown',
                'status' => 'skipped',
                'primary_category' => $primaryCategory,
                'fallback_category' => $fallbackCategory,
                'error_message' => 'Skipped by throttle.',
                'context' => $context + ['throttle_seconds' => $throttleSeconds],
            ]);

            return false;
        }

        $primary = $this->transportFor($primaryCategory);

        try {
            $notifiable->notify($notificationFactory($primary));

            Log::info('Transactional mail sent.', $context + [
                'category' => $primaryCategory,
                'mailer' => $primary['mailer'],
                'from' => $primary['address'],
            ]);

            $this->recordDelivery($notifiable, [
                'dedupe_key' => $dedupeKey,
                'mail_type' => $context['mail_type'] ?? 'unknown',
                'status' => 'sent',
                'primary_category' => $primaryCategory,
                'fallback_category' => $fallbackCategory,
                'used_category' => $primary['category'],
                'mailer' => $primary['mailer'],
                'from_address' => $primary['address'],
                'context' => $context,
                'sent_at' => now(),
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Transactional mail primary sender failed.', $context + [
                'category' => $primaryCategory,
                'mailer' => $primary['mailer'],
                'from' => $primary['address'],
                'message' => $exception->getMessage(),
            ]);
        }

        if (! $fallbackCategory) {
            $this->recordDelivery($notifiable, [
                'dedupe_key' => $dedupeKey,
                'mail_type' => $context['mail_type'] ?? 'unknown',
                'status' => 'failed',
                'primary_category' => $primaryCategory,
                'error_message' => $exception->getMessage(),
                'context' => $context,
            ]);

            Cache::forget($cacheKey);

            return false;
        }

        $fallback = $this->transportFor($fallbackCategory);

        try {
            $notifiable->notify($notificationFactory($fallback));

            Log::info('Transactional mail sent with fallback sender.', $context + [
                'category' => $fallbackCategory,
                'mailer' => $fallback['mailer'],
                'from' => $fallback['address'],
            ]);

            $this->recordDelivery($notifiable, [
                'dedupe_key' => $dedupeKey,
                'mail_type' => $context['mail_type'] ?? 'unknown',
                'status' => 'sent',
                'primary_category' => $primaryCategory,
                'fallback_category' => $fallbackCategory,
                'used_category' => $fallback['category'],
                'mailer' => $fallback['mailer'],
                'from_address' => $fallback['address'],
                'context' => $context,
                'sent_at' => now(),
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Transactional mail fallback sender failed.', $context + [
                'category' => $fallbackCategory,
                'mailer' => $fallback['mailer'],
                'from' => $fallback['address'],
                'message' => $exception->getMessage(),
            ]);
        }

        $this->recordDelivery($notifiable, [
            'dedupe_key' => $dedupeKey,
            'mail_type' => $context['mail_type'] ?? 'unknown',
            'status' => 'failed',
            'primary_category' => $primaryCategory,
            'fallback_category' => $fallbackCategory,
            'used_category' => $fallback['category'],
            'mailer' => $fallback['mailer'],
            'from_address' => $fallback['address'],
            'error_message' => $exception->getMessage(),
            'context' => $context,
        ]);

        Cache::forget($cacheKey);

        return false;
    }

    private function cacheKey(string $dedupeKey): string
    {
        return 'transactional-mail:'.sha1($dedupeKey);
    }

    private function mailerIsReady(?string $mailer): bool
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

    private function applyStoredMailerConfig(MailSenderSetting $setting): void
    {
        Config::set("mail.mailers.{$setting->mailer}.transport", 'smtp');
        Config::set("mail.mailers.{$setting->mailer}.scheme", $setting->scheme ?: config("mail.mailers.{$setting->mailer}.scheme"));
        Config::set("mail.mailers.{$setting->mailer}.host", $setting->host ?: config("mail.mailers.{$setting->mailer}.host"));
        Config::set("mail.mailers.{$setting->mailer}.port", $setting->port ?: config("mail.mailers.{$setting->mailer}.port"));
        Config::set("mail.mailers.{$setting->mailer}.username", $setting->username);

        if (filled($setting->password)) {
            Config::set("mail.mailers.{$setting->mailer}.password", $setting->password);
        }

        Mail::purge($setting->mailer);
    }

    private function preference(string $key, mixed $default = null): mixed
    {
        $raw = Setting::valueFor('mail_preferences', '{}');
        $preferences = json_decode((string) $raw, true);

        return is_array($preferences) && array_key_exists($key, $preferences)
            ? $preferences[$key]
            : $default;
    }

    private function categoryDisabled(string $category): bool
    {
        return in_array($category, $this->disabledCategories(), true);
    }

    private function recordDelivery(object $notifiable, array $data): void
    {
        try {
            MailDelivery::create($data + [
                'recipient_id' => $notifiable->id ?? null,
                'recipient_email' => $notifiable->email ?? null,
                'recipient_name' => $notifiable->name ?? null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
