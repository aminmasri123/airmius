<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\ClubInvoicePaymentService;
use App\Services\ClubPaymentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
                'amount' => number_format((float) $payment->amount, 2, ',', '.').' EUR',
                'raw_amount' => (float) $payment->amount,
                'status' => $payment->status,
                'method' => $payment->method,
                'reference' => $payment->reference,
                'paid_at' => $payment->paid_at?->format('Y-m-d H:i'),
                'notes' => $payment->notes,
                'delete_url' => route('payments.destroy', $payment),
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
            'revenue' => number_format((float) Payment::where('status', 'paid')->sum('amount'), 2, ',', '.').' EUR',
        ];

        return Inertia::render('Auth/Dashboard/Admin/Payments/Index', [
            'payments' => $payments,
            'summary' => $summary,
            'clubs' => Club::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->limit(250)
                ->get(),
            'users' => User::query()
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->limit(250)
                ->get(),
            'invoices' => Invoice::query()
                ->select('id', 'club_id', 'user_id', 'number', 'title', 'amount', 'status')
                ->whereIn('status', ['open', 'pending', 'overdue'])
                ->latest()
                ->limit(250)
                ->get()
                ->map(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'club_id' => $invoice->club_id,
                    'user_id' => $invoice->user_id,
                    'number' => $invoice->number,
                    'title' => $invoice->title,
                    'amount' => number_format((float) $invoice->amount, 2, ',', '.').' EUR',
                    'status' => $invoice->status,
                ]),
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
        $data = $request->validate([
            'club_id' => ['required', Rule::exists('clubs', 'id')],
            'user_id' => ['required', Rule::exists('users', 'id')],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'status' => ['required', Rule::in(['paid', 'pending', 'open', 'failed', 'cancelled'])],
            'method' => ['required', Rule::in(['bank_transfer', 'cash', 'card', 'paypal', 'stripe', 'manual'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($data) {
            $club = Club::query()->findOrFail((int) $data['club_id']);
            $payment = app(ClubPaymentNumberService::class)->create($club, [
                'user_id' => $data['user_id'],
                'invoice_id' => $data['invoice_id'] ?? null,
                'amount' => $data['amount'],
                'status' => $data['status'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'paid_at' => $data['paid_at'] ?? ($data['status'] === 'paid' ? now() : null),
                'notes' => $data['notes'] ?? null,
            ], request()->user());

            if ($payment->invoice_id) {
                $this->syncInvoicePaymentStatus($payment->invoice);
            }
        });

        return back()->with('success', 'Zahlung wurde erstellt.');
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
        DB::transaction(function () use ($payment) {
            $invoice = $payment->invoice_id ? Invoice::lockForUpdate()->findOrFail($payment->invoice_id) : null;
            $locked = Payment::lockForUpdate()->findOrFail($payment->id);
            app(ClubInvoicePaymentService::class)->assertEditable($locked);
            $locked->delete();

            if ($invoice) {
                $this->syncInvoicePaymentStatus($invoice->refresh());
            }
        });

        return back()->with('success', 'Zahlung wurde gelöscht.');
    }

    private function syncInvoicePaymentStatus(?Invoice $invoice): void
    {
        if (! $invoice) {
            return;
        }

        $paidAmount = (float) $invoice->payments()
            ->where('status', 'paid')
            ->sum('amount');

        $invoice->forceFill([
            'status' => $paidAmount >= (float) $invoice->amount ? 'paid' : 'open',
            'paid_at' => $paidAmount >= (float) $invoice->amount ? ($invoice->paid_at ?: now()) : null,
        ])->save();
    }
}
