<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $invoices = Invoice::query()
            ->with(['club:id,name', 'user:id,name,email'])
            ->withSum('payments as paid_amount', 'amount')
            ->latest('issued_at')
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'title' => $invoice->title,
                'description' => $invoice->description,
                'amount' => number_format((float) $invoice->amount, 2, ',', '.') . ' EUR',
                'paid_amount' => number_format((float) ($invoice->paid_amount ?? 0), 2, ',', '.') . ' EUR',
                'status' => $invoice->status,
                'source' => $invoice->source,
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'issued_at' => $invoice->issued_at?->format('Y-m-d'),
                'paid_at' => $invoice->paid_at?->format('Y-m-d H:i'),
                'club' => $invoice->club ? [
                    'id' => $invoice->club->id,
                    'name' => $invoice->club->name,
                ] : null,
                'user' => $invoice->user ? [
                    'id' => $invoice->user->id,
                    'name' => $invoice->user->name,
                    'email' => $invoice->user->email,
                ] : null,
            ]);

        $summary = [
            'count' => Invoice::count(),
            'open' => Invoice::whereIn('status', ['open', 'pending'])->count(),
            'paid' => Invoice::where('status', 'paid')->count(),
            'overdue' => Invoice::where('status', 'overdue')->count(),
            'revenue' => number_format((float) Invoice::where('status', 'paid')->sum('amount'), 2, ',', '.') . ' EUR',
        ];

        return Inertia::render('Auth/Dashboard/Admin/Invoices/Index', [
            'invoices' => $invoices,
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
    public function show(Invoice $invoice)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Invoice $invoice)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Invoice $invoice)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Invoice $invoice)
    {
        //
    }
}
