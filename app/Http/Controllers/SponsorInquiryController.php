<?php

namespace App\Http\Controllers;

use App\Models\SponsorInquiry;
use App\Services\SponsorInquiryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SponsorInquiryController extends Controller
{
    public function __construct(private SponsorInquiryService $inquiries) {}

    public function index(Request $request)
    {
        $incoming = $request->integer('sponsor_id') > 0
            ? $this->inquiries->incoming($request->user(), $request->integer('sponsor_id'))
            : collect();

        return response()->json([
            'clubs' => $this->inquiries->availableClubs($request->user()),
            'outgoing' => $this->inquiries->outgoing($request->user()),
            'incoming' => $incoming,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sponsor_id' => ['required', 'integer', 'exists:sponsors,id'],
            'club_id' => ['required', 'integer', 'exists:clubs,id'],
            'subject' => ['required', 'string', 'min:5', 'max:160'],
            'message' => ['required', 'string', 'min:20', 'max:5000'],
        ]);
        $inquiry = $this->inquiries->create($request->user(), $data);

        return $request->expectsJson()
            ? response()->json(['data' => $inquiry], 201)
            : back()->with('success', 'Deine Sponsoring-Anfrage wurde gesendet.');
    }

    public function respond(Request $request, SponsorInquiry $inquiry)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['interested', 'declined'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $record = $this->inquiries->respond($request->user(), $inquiry, $data['decision'], $data['note'] ?? null);

        return $request->expectsJson()
            ? response()->json(['data' => $record])
            : back()->with('success', 'Sponsoring-Anfrage beantwortet.');
    }

    public function withdraw(Request $request, SponsorInquiry $inquiry)
    {
        $record = $this->inquiries->withdraw($request->user(), $inquiry);

        return $request->expectsJson()
            ? response()->json(['data' => $record])
            : back()->with('success', 'Anfrage zurückgezogen.');
    }
}
