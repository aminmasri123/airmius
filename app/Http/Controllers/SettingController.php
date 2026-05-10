<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\EmailTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('Auth/Dashboard/Admin/Settings/Index', [
            'settings' => [
                'maintenance' => [
                    'enabled' => Setting::boolFor('maintenance_mode'),
                    'title' => Setting::valueFor('maintenance_title', 'Airmius ist gerade im Wartemodus'),
                    'message' => Setting::valueFor(
                        'maintenance_message',
                        'Wir verbessern gerade die Plattform. Bitte versuche es in Kürze erneut.'
                    ),
                ],
                'billing' => [
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
                ],
                'email_templates' => EmailTemplate::forAdmin(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Setting $setting)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Setting $setting)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'maintenance_enabled' => ['required', 'boolean'],
            'maintenance_title' => ['required', 'string', 'max:120'],
            'maintenance_message' => ['required', 'string', 'max:500'],
            'billing_company_name' => ['nullable', 'string', 'max:160'],
            'billing_legal_name' => ['nullable', 'string', 'max:160'],
            'billing_company_street' => ['nullable', 'string', 'max:160'],
            'billing_company_postal_code' => ['nullable', 'string', 'max:40'],
            'billing_company_city' => ['nullable', 'string', 'max:120'],
            'billing_company_country' => ['nullable', 'string', 'max:120'],
            'billing_company_email' => ['nullable', 'email', 'max:160'],
            'billing_company_website' => ['nullable', 'string', 'max:160'],
            'billing_tax_number' => ['nullable', 'string', 'max:80'],
            'billing_vat_id' => ['nullable', 'string', 'max:80'],
            'billing_court' => ['nullable', 'string', 'max:160'],
            'billing_registration_number' => ['nullable', 'string', 'max:80'],
            'billing_managing_director' => ['nullable', 'string', 'max:160'],
            'billing_small_business_notice' => ['nullable', 'string', 'max:300'],
            'billing_invoice_note' => ['nullable', 'string', 'max:300'],
            'billing_bank_account_holder' => ['nullable', 'string', 'max:120'],
            'billing_bank_name' => ['nullable', 'string', 'max:120'],
            'billing_iban' => ['nullable', 'string', 'max:40'],
            'billing_bic' => ['nullable', 'string', 'max:20'],
            'billing_payment_terms_days' => ['required', 'integer', 'min:1', 'max:60'],
            'email_templates' => ['required', 'array'],
            'email_templates.*.subject' => ['required', 'string', 'max:180'],
            'email_templates.*.greeting' => ['required', 'string', 'max:180'],
            'email_templates.*.body' => ['required', 'string', 'max:5000'],
            'email_templates.*.action_label' => ['nullable', 'string', 'max:120'],
        ]);

        Setting::setValue('maintenance_mode', (bool) $data['maintenance_enabled']);
        Setting::setValue('maintenance_title', $data['maintenance_title']);
        Setting::setValue('maintenance_message', $data['maintenance_message']);
        Setting::setValue('billing_company_name', $data['billing_company_name'] ?? '');
        Setting::setValue('billing_legal_name', $data['billing_legal_name'] ?? '');
        Setting::setValue('billing_company_street', $data['billing_company_street'] ?? '');
        Setting::setValue('billing_company_postal_code', $data['billing_company_postal_code'] ?? '');
        Setting::setValue('billing_company_city', $data['billing_company_city'] ?? '');
        Setting::setValue('billing_company_country', $data['billing_company_country'] ?? '');
        Setting::setValue('billing_company_email', $data['billing_company_email'] ?? '');
        Setting::setValue('billing_company_website', $data['billing_company_website'] ?? '');
        Setting::setValue('billing_tax_number', $data['billing_tax_number'] ?? '');
        Setting::setValue('billing_vat_id', strtoupper(str_replace(' ', '', $data['billing_vat_id'] ?? '')));
        Setting::setValue('billing_court', $data['billing_court'] ?? '');
        Setting::setValue('billing_registration_number', $data['billing_registration_number'] ?? '');
        Setting::setValue('billing_managing_director', $data['billing_managing_director'] ?? '');
        Setting::setValue('billing_small_business_notice', $data['billing_small_business_notice'] ?? '');
        Setting::setValue('billing_invoice_note', $data['billing_invoice_note'] ?? '');
        Setting::setValue('billing_bank_account_holder', $data['billing_bank_account_holder'] ?? '');
        Setting::setValue('billing_bank_name', $data['billing_bank_name'] ?? '');
        Setting::setValue('billing_iban', strtoupper(str_replace(' ', '', $data['billing_iban'] ?? '')));
        Setting::setValue('billing_bic', strtoupper(str_replace(' ', '', $data['billing_bic'] ?? '')));
        Setting::setValue('billing_payment_terms_days', (int) $data['billing_payment_terms_days']);
        EmailTemplate::save($data['email_templates']);

        return back()->with('success', 'Systemeinstellungen wurden gespeichert.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Setting $setting)
    {
        //
    }
}
