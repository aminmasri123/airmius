<?php

namespace App\Http\Controllers;

use App\Models\PublicContactRequest;
use App\Support\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

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

        // Persist first: a temporary mail-provider outage must never discard a
        // support request that the user has already submitted.
        $contactRequest = PublicContactRequest::create([
            ...$validated,
            'user_id' => $request->user()?->id,
            'privacy_consent_at' => $request->boolean('privacy_consent') ? now() : null,
            'status' => 'new',
            'email_delivery_status' => 'pending',
            'retention_expires_at' => now()->addYear(),
        ]);

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

        try {
            Mail::raw($content['body'], function ($mail) use ($validated, $content) {
                $mail->to('contact@airmius.com')
                    ->subject($content['subject'])
                    ->replyTo($validated['email']);
            });
            $contactRequest->update(['email_delivery_status' => 'sent']);
        } catch (Throwable $error) {
            $contactRequest->update([
                'email_delivery_status' => 'failed',
                'email_delivery_error' => str($error->getMessage())->limit(1000),
            ]);

            Log::warning('Public contact request persisted but notification email failed.', [
                'public_contact_request_id' => $contactRequest->id,
                'category' => $contactRequest->category,
                'exception' => $error::class,
            ]);
        }

        // 4. Response für Inertia (wichtig für onSuccess)
        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'sent' => true,
                    'request_id' => $contactRequest->id,
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
