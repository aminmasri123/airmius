<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SettingController;
use App\Models\Setting;
use App\Services\Ai\AiProviderTokenStatusService;
use App\Services\ExternalProviderUsageService;
use App\Support\EmailTemplate;
use Illuminate\Http\Request;

class AdminSystemController extends Controller
{
    public function dashboard(
        Request $request,
        AiProviderTokenStatusService $aiTokenStatus,
        ExternalProviderUsageService $providerUsage
    ) {
        $this->authorizeManager($request);
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        return response()->json([
            'data' => [
                'settings' => [
                    'ai_token_status' => [
                        'providers' => $aiTokenStatus->all(),
                        'alerts' => $aiTokenStatus->alerts(),
                    ],
                    'maintenance' => [
                        'enabled' => Setting::boolFor('maintenance_mode'),
                        'title' => Setting::valueFor(
                            'maintenance_title',
                            'Airmius ist gerade im Wartemodus'
                        ),
                        'message' => Setting::valueFor(
                            'maintenance_message',
                            'Wir verbessern gerade die Plattform. Bitte versuche es in Kürze erneut.'
                        ),
                    ],
                    'billing' => $this->billingSettings(),
                    'email_templates' => EmailTemplate::forAdmin(),
                ],
                'provider_costs' => $providerUsage->dashboard($data['month'] ?? null),
                'abilities' => ['manage' => true],
            ],
        ]);
    }

    public function updateSettings(Request $request)
    {
        $this->authorizeManager($request);
        app(SettingController::class)->update($request);

        return response()->json([
            'data' => [
                'maintenance' => [
                    'enabled' => Setting::boolFor('maintenance_mode'),
                    'title' => Setting::valueFor('maintenance_title'),
                    'message' => Setting::valueFor('maintenance_message'),
                ],
                'billing' => $this->billingSettings(),
                'email_templates' => EmailTemplate::forAdmin(),
            ],
        ]);
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()?->can('system.manage'), 403);
    }

    private function billingSettings(): array
    {
        return [
            'brand_name' => Setting::valueFor('billing_brand_name', config('airmius.billing.company_name')),
            'company_name' => Setting::valueFor('billing_company_name', config('airmius.billing.company_name')),
            'legal_name' => Setting::valueFor('billing_legal_name', config('airmius.billing.legal_name')),
            'company_street' => Setting::valueFor('billing_company_street', config('airmius.billing.street')),
            'company_postal_code' => Setting::valueFor('billing_company_postal_code', config('airmius.billing.postal_code')),
            'company_city' => Setting::valueFor('billing_company_city', config('airmius.billing.city')),
            'company_country' => Setting::valueFor('billing_company_country', config('airmius.billing.country')),
            'company_email' => Setting::valueFor('billing_company_email', config('airmius.billing.email')),
            'company_website' => Setting::valueFor('billing_company_website', config('airmius.billing.website')),
            'tax_number' => Setting::valueFor('billing_tax_number', config('airmius.billing.tax_number')),
            'vat_id' => Setting::valueFor('billing_vat_id', config('airmius.billing.vat_id')),
            'court' => Setting::valueFor('billing_court', config('airmius.billing.court')),
            'registration_number' => Setting::valueFor('billing_registration_number', config('airmius.billing.registration_number')),
            'managing_director' => Setting::valueFor('billing_managing_director', config('airmius.billing.managing_director')),
            'small_business_notice' => Setting::valueFor('billing_small_business_notice', config('airmius.billing.small_business_notice')),
            'invoice_note' => Setting::valueFor('billing_invoice_note', config('airmius.billing.invoice_note')),
            'bank_account_holder' => Setting::valueFor('billing_bank_account_holder', 'Airmius'),
            'bank_name' => Setting::valueFor('billing_bank_name', ''),
            'iban' => Setting::valueFor('billing_iban', ''),
            'bic' => Setting::valueFor('billing_bic', ''),
            'payment_terms_days' => (int) Setting::valueFor('billing_payment_terms_days', 14),
        ];
    }
}
