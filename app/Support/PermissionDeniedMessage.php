<?php

namespace App\Support;

class PermissionDeniedMessage
{
    public const TITLE = 'Du hast dafür keine Berechtigung';

    public const MESSAGE = 'Du hast dafür keine Berechtigung. Bitte wende dich an deinen Verein/Admin oder prüfe dein Paket.';

    public static function normalize(?string $message, ?string $fallback = null): string
    {
        $text = trim((string) $message);

        if ($text === '' || in_array($text, [
            'Forbidden',
            'This action is forbidden.',
            'This action is unauthorized.',
        ], true)) {
            return $fallback ?: self::MESSAGE;
        }

        return $text;
    }
}
