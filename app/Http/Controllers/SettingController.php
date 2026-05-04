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
