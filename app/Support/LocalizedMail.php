<?php

namespace App\Support;

use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\Lang;
use IntlDateFormatter;
use NumberFormatter;

final readonly class LocalizedMail
{
    private const REGIONAL_LOCALES = [
        'de' => 'de_DE',
        'en' => 'en_US',
        'fr' => 'fr_FR',
        'ar' => 'ar',
    ];

    private function __construct(public string $locale) {}

    public static function for(object $notifiable): self
    {
        return new self(
            SupportedLocale::normalize(data_get($notifiable, 'language'))
                ?? SupportedLocale::normalize(app()->getLocale())
                ?? SupportedLocale::DEFAULT,
        );
    }

    /** @param array<string, mixed> $replace */
    public function text(string $key, array $replace = []): string
    {
        return Lang::get('core_mail.'.$key, $replace, $this->locale);
    }

    public function recipientName(object $notifiable): string
    {
        return trim((string) data_get($notifiable, 'name')) ?: $this->text('common.together');
    }

    public function greeting(object $notifiable): string
    {
        return $this->text('common.greeting', ['name' => $this->recipientName($notifiable)]);
    }

    public function date(mixed $value): string
    {
        if (! $value instanceof DateTimeInterface) {
            return $this->text('common.not_set');
        }

        if (class_exists(IntlDateFormatter::class)) {
            $timezone = $value->getTimezone();
            $formatter = new IntlDateFormatter(
                self::REGIONAL_LOCALES[$this->locale] ?? self::REGIONAL_LOCALES['de'],
                IntlDateFormatter::MEDIUM,
                IntlDateFormatter::NONE,
                $timezone instanceof DateTimeZone ? $timezone->getName() : date_default_timezone_get(),
            );
            $formatted = $formatter->format($value);

            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return $value->format(match ($this->locale) {
            'en' => 'm/d/Y',
            'fr' => 'd/m/Y',
            'ar' => 'Y/m/d',
            default => 'd.m.Y',
        });
    }

    public function money(float $amount, string $currency): string
    {
        $currency = strtoupper(trim($currency)) ?: 'EUR';

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter(
                self::REGIONAL_LOCALES[$this->locale] ?? self::REGIONAL_LOCALES['de'],
                NumberFormatter::CURRENCY,
            );
            $formatted = $formatter->formatCurrency($amount, $currency);

            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        $decimal = $this->locale === 'en' ? '.' : ',';
        $thousands = $this->locale === 'en' ? ',' : ($this->locale === 'fr' ? ' ' : '.');

        return number_format($amount, 2, $decimal, $thousands).' '.$currency;
    }
}
