<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubContributionRule extends Model
{
    public const PRORATION_POLICIES = [
        'prorate_days',
        'full_amount',
        'next_period',
    ];

    public const PRORATION_POLICY_LABELS = [
        'prorate_days' => 'Anteilig nach Tagen',
        'full_amount' => 'Voller Betrag',
        'next_period' => 'Erst ab nächstem Zeitraum',
    ];

    public const RULE_TYPES = [
        'standard',
        'youth',
        'supporting',
        'family',
        'sibling_discount',
        'reduction',
        'exemption',
        'discount',
        'special',
        'base',
        'department',
        'admission',
        'allocation',
        'service',
    ];

    public const DISCOUNT_OPERATORS = ['percent', 'fixed'];

    public const RULE_TYPE_LABELS = [
        'standard' => 'Standardbeitrag',
        'youth' => 'Jugendtarif',
        'supporting' => 'Fördertarif',
        'base' => 'Grundbeitrag',
        'department' => 'Abteilungsbeitrag',
        'admission' => 'Aufnahmegebühr',
        'allocation' => 'Umlage',
        'service' => 'Leistungsbeitrag',
        'family' => 'Familienbeitrag',
        'sibling_discount' => 'Geschwisterrabatt',
        'reduction' => 'Ermäßigung',
        'exemption' => 'Befreiung',
        'discount' => 'Rabatt',
        'special' => 'Sonderbeitrag',
    ];

    public const DISCOUNT_OPERATOR_LABELS = [
        'percent' => 'Prozentualer Rabatt',
        'fixed' => 'Fester Rabattbetrag',
    ];

    protected $fillable = [
        'club_id',
        'club_membership_type_id',
        'club_policy_document_id',
        'name',
        'valid_from',
        'valid_until',
        'billing_interval',
        'proration_policy',
        'amount',
        'age_min',
        'age_max',
        'factor_key',
        'factor_operator',
        'factor_value',
        'priority',
        'tax_account',
        'accounting_account',
        'snapshot',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'amount' => 'decimal:2',
            'priority' => 'integer',
            'snapshot' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function membershipType()
    {
        return $this->belongsTo(ClubMembershipType::class, 'club_membership_type_id');
    }

    public function policyDocument()
    {
        return $this->belongsTo(ClubPolicyDocument::class, 'club_policy_document_id');
    }

    public function scopeEffectiveOn($query, $date)
    {
        return $query
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date);
            });
    }

    public static function ruleTypeOptions(): array
    {
        return collect(self::RULE_TYPES)
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => self::RULE_TYPE_LABELS[$value],
            ])
            ->all();
    }

    public static function discountOperatorOptions(): array
    {
        return collect(self::DISCOUNT_OPERATORS)
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => self::DISCOUNT_OPERATOR_LABELS[$value],
            ])
            ->all();
    }

    public static function prorationPolicyOptions(): array
    {
        return collect(self::PRORATION_POLICIES)
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => self::PRORATION_POLICY_LABELS[$value],
            ])
            ->all();
    }
}
