<?php

namespace App\Http\Controllers;

use App\Models\Sponsor;
use App\Services\SponsorInquiryService;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class PublicSponsorController extends Controller
{
    public function index(Request $request, SponsorInquiryService $inquiries)
    {
        $sponsors = Sponsor::query()
            ->with('club:id,name')
            ->publiclyVerified()
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhereDate('starts_at', '<=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', now()->toDateString());
            })
            ->orderByRaw('CASE WHEN club_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get()
            ->map(fn (Sponsor $sponsor) => [
                'id' => $sponsor->id,
                'name' => $sponsor->name,
                'country_code' => $sponsor->country_code,
                'region' => $sponsor->region,
                'city' => $sponsor->city,
                'website' => filter_var($sponsor->website, FILTER_VALIDATE_URL) && in_array(parse_url($sponsor->website, PHP_URL_SCHEME), ['http', 'https'], true)
                    ? $sponsor->website
                    : null,
                'logo_url' => UploadStorage::url($sponsor->logo),
                'logo_light_url' => UploadStorage::url($sponsor->logo_light ?: $sponsor->logo),
                'logo_dark_url' => UploadStorage::url($sponsor->logo_dark ?: $sponsor->logo_light ?: $sponsor->logo),
                'starts_at' => optional($sponsor->starts_at)->toDateString(),
                'ends_at' => optional($sponsor->ends_at)->toDateString(),
                'club' => $sponsor->club ? [
                    'id' => $sponsor->club->id,
                    'name' => $sponsor->club->name,
                ] : null,
                'scope' => $sponsor->scope ?: ($sponsor->club_id ? 'club' : 'platform'),
            ]);

        return Inertia::render('Guest/Sponsors', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'sponsors' => $sponsors,
            'clubOptions' => $request->user() ? $inquiries->availableClubs($request->user()) : [],
            'outgoingInquiries' => $request->user() ? $inquiries->outgoing($request->user()) : [],
            'stats' => [
                'total' => $sponsors->count(),
                'platform' => $sponsors->where('scope', 'platform')->count(),
                'outfit_subscription' => $sponsors->where('scope', 'outfit_subscription')->count(),
                'club' => $sponsors->where('scope', 'club')->count(),
            ],
        ]);
    }
}
