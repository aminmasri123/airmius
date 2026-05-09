<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $payments = Payment::query()
            ->with(['club:id,name', 'user:id,name,email', 'invoice:id,number,title,status'])
            ->latest('paid_at')
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Payment $payment) => [
                'id' => $payment->id,
                'amount' => number_format((float) $payment->amount, 2, ',', '.') . ' EUR',
                'raw_amount' => (float) $payment->amount,
                'status' => $payment->status,
                'method' => $payment->method,
                'reference' => $payment->reference,
                'paid_at' => $payment->paid_at?->format('Y-m-d H:i'),
                'notes' => $payment->notes,
                'club' => $payment->club ? [
                    'id' => $payment->club->id,
                    'name' => $payment->club->name,
                ] : null,
                'user' => $payment->user ? [
                    'id' => $payment->user->id,
                    'name' => $payment->user->name,
                    'email' => $payment->user->email,
                ] : null,
                'invoice' => $payment->invoice ? [
                    'id' => $payment->invoice->id,
                    'number' => $payment->invoice->number,
                    'title' => $payment->invoice->title,
                    'status' => $payment->invoice->status,
                ] : null,
            ]);

        $summary = [
            'count' => Payment::count(),
            'paid' => Payment::where('status', 'paid')->count(),
            'pending' => Payment::whereIn('status', ['pending', 'open'])->count(),
            'revenue' => number_format((float) Payment::where('status', 'paid')->sum('amount'), 2, ',', '.') . ' EUR',
        ];

        return Inertia::render('Auth/Dashboard/Admin/Payments/Index', [
            'payments' => $payments,
            'summary' => $summary,
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
    public function show(Payment $payment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Payment $payment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Payment $payment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payment $payment)
    {
        //
    }
}
