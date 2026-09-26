<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubNewsletterCampaign extends Model
{
    protected $fillable = [
        'club_id', 'created_by', 'subject', 'body', 'template_key', 'idempotency_key', 'status', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function deliveries()
    {
        return $this->hasMany(ClubNewsletterDelivery::class);
    }
}
