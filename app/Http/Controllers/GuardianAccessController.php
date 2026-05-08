<?php

namespace App\Http\Controllers;

use App\Models\GuardianAccessCode;
use App\Models\User;
use App\Notifications\GuardianAccessCodeRequested;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class GuardianAccessController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Guardian/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $childrenExist = User::query()
            ->whereRaw('LOWER(guardian_email) = ?', [$email])
            ->whereDate('birth_date', '>', now()->subYears(16)->toDateString())
            ->exists();

        if ($childrenExist) {
            $code = (string) random_int(100000, 999999);

            GuardianAccessCode::create([
                'email' => $email,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(15),
            ]);

            Notification::route('mail', $email)
                ->notify(new GuardianAccessCodeRequested($code));
        }

        $request->session()->put('guardian_access_email', $email);

        return redirect()
            ->route('guardian-access.verify')
            ->with('status', 'Wenn diese E-Mail bei uns als Eltern-E-Mail gespeichert ist, wurde ein Code gesendet.');
    }

    public function verify(): Response
    {
        return Inertia::render('Guardian/Verify', [
            'email' => session('guardian_access_email'),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $email = $request->session()->get('guardian_access_email');
        abort_if(! $email, 403);

        $accessCode = GuardianAccessCode::query()
            ->where('email', $email)
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->latest()
            ->first();

        if (! $accessCode || ! Hash::check($data['code'], $accessCode->code_hash)) {
            return back()->withErrors([
                'code' => 'Der Code ist ungueltig oder abgelaufen.',
            ]);
        }

        $accessCode->update(['used_at' => now()]);
        $request->session()->put('guardian_access_verified_email', $email);

        return redirect()->route('guardian-access.children');
    }

    public function children(Request $request): Response|RedirectResponse
    {
        $email = $request->session()->get('guardian_access_verified_email');

        if (! $email) {
            return redirect()->route('guardian-access.create');
        }

        return Inertia::render('Guardian/Children', [
            'email' => $email,
            'children' => User::query()
                ->whereRaw('LOWER(guardian_email) = ?', [$email])
                ->whereDate('birth_date', '>', now()->subYears(16)->toDateString())
                ->select([
                    'id',
                    'name',
                    'email',
                    'birth_date',
                    'guardian_consent_at',
                    'guardian_consent_rejected_at',
                    'guardian_consent_revoked_at',
                ])
                ->orderBy('birth_date', 'desc')
                ->get()
                ->map(fn (User $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'email' => $child->email,
                    'birth_date' => $child->birth_date,
                    'age' => $child->birth_date ? (int) Carbon::parse($child->birth_date)->age : null,
                    'approved_at' => $child->guardian_consent_at,
                    'rejected_at' => $child->guardian_consent_rejected_at,
                    'revoked_at' => $child->guardian_consent_revoked_at,
                ]),
        ]);
    }

    public function createAccount(Request $request): Response|RedirectResponse
    {
        $email = $request->session()->get('guardian_access_verified_email');

        if (! $email) {
            return redirect()->route('guardian-access.create');
        }

        return Inertia::render('Guardian/CreateAccount', [
            'email' => $email,
            'hasExistingAccount' => User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->exists(),
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $email = $request->session()->get('guardian_access_verified_email');
        abort_if(! $email, 403);

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existingUser) {
            $this->linkGuardianAccount($existingUser, $email);

            return redirect()
                ->route('login')
                ->with('status', 'Dein vorhandenes Konto wurde als Elternkonto verknuepft. Du kannst dich jetzt anmelden.');
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'country' => ['required', 'string', 'size:2'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name']);

        $guardian = User::create([
            'name' => trim($firstName.' '.$lastName),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'birth_date' => Carbon::parse($data['birth_date'])->toDateString(),
            'country' => strtoupper($data['country']),
            'password' => Hash::make($data['password']),
        ]);

        $guardian->forceFill(['email_verified_at' => now()])->save();
        $this->linkGuardianAccount($guardian, $email);

        return redirect()
            ->route('login')
            ->with('status', 'Dein Elternkonto wurde erstellt. Du kannst dich jetzt anmelden.');
    }

    public function revoke(Request $request, User $child): RedirectResponse
    {
        $email = $request->session()->get('guardian_access_verified_email');
        abort_if(! $email, 403);
        abort_unless(mb_strtolower((string) $child->guardian_email) === $email, 403);
        abort_unless($child->birth_date && Carbon::parse($child->birth_date)->age < 16, 422);

        $child->forceFill([
            'guardian_consent_at' => null,
            'guardian_consent_rejected_at' => null,
            'guardian_consent_revoked_at' => now(),
            'guardian_consent_revoked_by_email' => $email,
        ])->save();

        if ($child->hasRole('minor_player')) {
            $child->removeRole('minor_player');
        }

        if (! $child->hasRole('minor_pending_consent')) {
            $child->assignRole('minor_pending_consent');
        }

        return back()->with('success', 'Die Zustimmung wurde widerrufen.');
    }

    public function approve(Request $request, User $child): RedirectResponse
    {
        $email = $request->session()->get('guardian_access_verified_email');
        abort_if(! $email, 403);
        abort_unless(mb_strtolower((string) $child->guardian_email) === $email, 403);
        abort_unless($child->birth_date && Carbon::parse($child->birth_date)->age < 16, 422);

        $child->forceFill([
            'guardian_consent_at' => now(),
            'guardian_consent_rejected_at' => null,
            'guardian_consent_revoked_at' => null,
            'guardian_consent_revoked_by_email' => null,
            'guardian_consent_token' => null,
        ])->save();

        if ($child->hasRole('minor_pending_consent')) {
            $child->removeRole('minor_pending_consent');
        }

        if (! $child->hasRole('minor_player')) {
            $child->assignRole('minor_player');
        }

        return back()->with('success', 'Die Ablehnung wurde zurueckgenommen und die Zustimmung erteilt.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget([
            'guardian_access_email',
            'guardian_access_verified_email',
        ]);

        return redirect()->route('guardian-access.create');
    }

    private function linkGuardianAccount(User $guardian, string $email): void
    {
        if (! $guardian->hasRole('guardian')) {
            $guardian->assignRole('guardian');
        }

        User::query()
            ->whereRaw('LOWER(guardian_email) = ?', [$email])
            ->whereDate('birth_date', '>', now()->subYears(16)->toDateString())
            ->update(['guardian_user_id' => $guardian->id]);
    }
}
