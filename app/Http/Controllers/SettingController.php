<?php

namespace App\Http\Controllers;

use App\Models\Setting;
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
        ]);

        Setting::setValue('maintenance_mode', (bool) $data['maintenance_enabled']);
        Setting::setValue('maintenance_title', $data['maintenance_title']);
        Setting::setValue('maintenance_message', $data['maintenance_message']);

        return back()->with('success', $data['maintenance_enabled']
            ? 'Wartemodus wurde aktiviert.'
            : 'Wartemodus wurde deaktiviert.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Setting $setting)
    {
        //
    }
}
