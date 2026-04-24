<?php

namespace App\Http\Controllers;

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
            'message' => ['required', 'string', 'max:2000'],
        ]);

        // 2. OPTIONAL: speichern in DB (falls du willst)
        // Contact::create($validated);

        // 3. OPTIONAL: E-Mail senden

        Mail::raw($validated['message'], function ($mail) use ($validated) {
            $mail->to('contact@airmius.com')
                 ->subject('Neue Kontaktanfrage von Kontaktformular - Anfrage von ' . $validated['name'])
                 ->replyTo($validated['email']);
        });


        // 4. Response für Inertia (wichtig für onSuccess)
        return back()->with([
            'success' => true
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
