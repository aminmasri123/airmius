<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationRouter;
use Illuminate\Support\Facades\Lang;

class AppNotification
{
    /** @return array{translation_key: string, fallback: string, replace: array<string, mixed>} */
    public static function translatedReplacement(string $key, string $fallback, array $replace = []): array
    {
        return [
            'translation_key' => $key,
            'fallback' => $fallback,
            'replace' => $replace,
        ];
    }

    /**
     * Store and deliver a notification in the recipient's preferred language.
     *
     * The semantic keys are retained as metadata so future clients can render
     * the notification again after a language change. The localized title and
     * body remain the transport-safe fallback for every existing client.
     */
    public static function sendLocalized(
        User|int $recipient,
        string $type,
        string $titleKey,
        ?string $bodyKey = null,
        array $replace = [],
        array $data = [],
        array $options = [],
    ): ?Notification {
        $user = self::resolveRecipient($recipient);

        if (! $user) {
            return null;
        }

        $localized = self::localizedData($user, $titleKey, $bodyKey, $replace);

        return self::send($user, $type, array_merge($data, $localized), $options);
    }

    /** @return array{title: string, locale: string, i18n: array<string, mixed>, body?: string}|null */
    public static function localizedData(
        User|int $recipient,
        string $titleKey,
        ?string $bodyKey = null,
        array $replace = [],
    ): ?array {
        $user = self::resolveRecipient($recipient);

        if (! $user) {
            return null;
        }

        $locale = SupportedLocale::normalize($user->language) ?? SupportedLocale::DEFAULT;
        $localizedReplace = self::localizeReplacements($replace, $locale);
        $localized = [
            'title' => Lang::get($titleKey, $localizedReplace, $locale),
            'locale' => $locale,
            'i18n' => [
                'title_key' => $titleKey,
                'body_key' => $bodyKey,
                'replace' => $replace,
            ],
        ];

        if ($bodyKey !== null) {
            $localized['body'] = Lang::get($bodyKey, $localizedReplace, $locale);
        }

        return $localized;
    }

    public static function send(User|int $recipient, string $type, array $data, array $options = []): ?Notification
    {
        $user = self::resolveRecipient($recipient);
        if (! $user) {
            return null;
        }

        return app(NotificationRouter::class)->send($user, $type, $data, $options);
    }

    private static function resolveRecipient(User|int $recipient): ?User
    {
        return $recipient instanceof User
            ? $recipient
            : User::query()->select(['id', 'language', 'notification_channels', 'notification_quiet_time'])->find($recipient);
    }

    /** @return array<string, mixed> */
    private static function localizeReplacements(array $replace, string $locale): array
    {
        return collect($replace)
            ->map(function (mixed $value) use ($locale): mixed {
                if (! is_array($value) || ! isset($value['translation_key'])) {
                    return $value;
                }

                $translation = Lang::get(
                    (string) $value['translation_key'],
                    is_array($value['replace'] ?? null) ? $value['replace'] : [],
                    $locale,
                );

                return $translation === $value['translation_key']
                    ? (string) ($value['fallback'] ?? $translation)
                    : $translation;
            })
            ->all();
    }
}
