<?php

namespace App\Support\Privacy;

enum DataClassification: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Personal = 'personal';
    case Sensitive = 'sensitive';
    case HighlySensitive = 'highly_sensitive';

    public function level(): int
    {
        return match ($this) {
            self::Public => 0,
            self::Internal => 1,
            self::Personal => 2,
            self::Sensitive => 3,
            self::HighlySensitive => 4,
        };
    }
}
