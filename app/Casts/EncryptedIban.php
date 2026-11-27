<?php

namespace App\Casts;

use App\Support\EncryptedIbanValue;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

final class EncryptedIban implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return EncryptedIbanValue::decrypt($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return EncryptedIbanValue::encrypt($value);
    }
}
