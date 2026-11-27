<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;

final class EncryptedIbanValue
{
    private const PREFIX = 'iban:v1:';

    public static function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::isEncrypted($value) ? $value : self::PREFIX.Crypt::encryptString($value);
    }

    public static function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::isEncrypted($value)
            ? Crypt::decryptString(substr($value, strlen(self::PREFIX)))
            : $value;
    }

    public static function isEncrypted(?string $value): bool
    {
        return $value !== null && str_starts_with($value, self::PREFIX);
    }
}
