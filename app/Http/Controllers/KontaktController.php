<?php

namespace App\Http\Controllers;

use App\Support\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class KontaktController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        // 1. Validation
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'subject' => ['nullable', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:80'],
            'priority' => ['nullable', 'string', 'max:40'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'platform' => ['nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        if ($request->expectsJson() && $request->user()) {
            $validated['name'] = $request->user()->name;
            $validated['email'] = $request->user()->email;
        }

        // 2. OPTIONAL: speichern in DB (falls du willst)
        // Contact::create($validated);

        // 3. OPTIONAL: E-Mail senden

        $context = collect([
            filled($validated['subject'] ?? null) ? 'Betreff: '.$validated['subject'] : null,
            filled($validated['category'] ?? null) ? 'Kategorie: '.$validated['category'] : null,
            filled($validated['priority'] ?? null) ? 'Priorität: '.$validated['priority'] : null,
            filled($validated['app_version'] ?? null) ? 'App-Version: '.$validated['app_version'] : null,
            filled($validated['platform'] ?? null) ? 'Plattform: '.$validated['platform'] : null,
        ])->filter()->implode("\n");

        $content = EmailTemplate::content('contact_form_admin', [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'message' => $context === ''
                ? $validated['message']
                : $context."\n\n".$validated['message'],
        ]);

        Mail::raw($content['body'], function ($mail) use ($validated, $content) {
            $mail->to('contact@airmius.com')
                ->subject($content['subject'])
                ->replyTo($validated['email']);
        });

        // 4. Response für Inertia (wichtig für onSuccess)
        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'sent' => true,
                    'category' => $validated['category'] ?? null,
                ],
            ], 201);
        }

        return back()->with([
            'success' => true,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
