<?php

namespace App\Services;

use App\Models\CommerceAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CommerceAuditService
{
    public function log(string $action, ?Model $model = null, array $before = [], array $after = [], ?string $note = null): void
    {
        CommerceAuditLog::create([
            'user_id' => Auth::id(),
            'auditable_type' => $model ? $model::class : null,
            'auditable_id' => $model?->getKey(),
            'action' => $action,
            'before' => $before ?: null,
            'after' => $after ?: null,
            'note' => $note,
        ]);
    }
}
