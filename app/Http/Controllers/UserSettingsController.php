<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserSettingsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return Inertia::render('Auth/Dashboard/Settings/Index', [
            'profileAddress' => $request->user()->only([
                'country',
                'street',
                'house_number',
                'postal_code',
                'city',
                'state',
            ]),
            'billingHistory' => [
                'invoices' => Invoice::query()
                    ->where('user_id', $request->user()->id)
                    ->with('club:id,name')
                    ->latest('id')
                    ->limit(30)
                    ->get(),
                'payments' => Payment::query()
                    ->where('user_id', $request->user()->id)
                    ->with(['club:id,name', 'invoice:id,number,title'])
                    ->latest('id')
                    ->limit(30)
                    ->get(),
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
    public function update(Request $request)
    {
            $data = $request->validate([
                'theme' => ['nullable', 'in:air,dark,womanly'],
                'country' => ['required', 'string', 'size:2'],
                'street' => ['nullable', 'string', 'max:255'],
                'house_number' => ['nullable', 'string', 'max:40'],
                'postal_code' => ['nullable', 'string', 'max:30'],
                'city' => ['nullable', 'string', 'max:255'],
                'state' => ['nullable', 'string', 'max:255'],
            ]);

            if (empty($data['theme'])) {
                unset($data['theme']);
            }

            $request->user()->update([
                ...$data,
                'country' => strtoupper($data['country']),
            ]);

            return back(); // oder Inertia redirect
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
