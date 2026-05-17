<?php

namespace App\Http\Controllers;

use App\Models\GuardianAccessCode;
use App\Models\User;
use App\Notifications\GuardianAccessCodeRequested;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GuardianAccessController extends Controller
{
    private const VERIFIED_SESSION_TTL_MINUTES = 15;
    private const CODE_TTL_MINUTES = 15;
    private const CODE_LENGTH = 6;

    private const REQUEST_RATE_LIMIT = 3;
    private const REQUEST_RATE_LIMIT_SECONDS = 60;

    private const CONFIRM_RATE_LIMIT = 8;
    private const CONFIRM_RATE_LIMIT_SECONDS = 180;

    private const ACCOUNT_STORE_RATE_LIMIT = 5;
    private const ACCOUNT_STORE_RATE_LIMIT_SECONDS = 60;

    private const CHILD_ACTION_RATE_LIMIT = 20;
    private const CHILD_ACTION_RATE_LIMIT_SECONDS = 60;

    private const DESTROY_RATE_LIMIT = 10;
    private const DESTROY_RATE_LIMIT_SECONDS = 60;

    private const MIN_GUARDIAN_AGE = 16;

    private const SESSION_EMAIL_KEY = 'guardian_access_email';
    private const SESSION_VERIFIED_EMAIL_KEY = 'guardian_access_verified_email';
    private const SESSION_VERIFIED_AT_KEY = 'guardian_access_verified_at';

    public function create(): Response
    {
        request()->session()->forget([
            self::SESSION_EMAIL_KEY,
            self::SESSION_VERIFIED_EMAIL_KEY,
            self::SESSION_VERIFIED_AT_KEY,
        ]);

        return Inertia::render('Guardian/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = mb_strtolower(trim($data['email']));

        $this->throttleAccess(
            $this->rateLimitKey($request, 'request-code', $email),
            self::REQUEST_RATE_LIMIT,
            self::REQUEST_RATE_LIMIT_SECONDS,
            'email',
            'Bitte warte {:seconds} Sekunden, bevor du einen weiteren Code anforderst.',
        );

        GuardianAccessCode::query()
            ->where('email', $email)
            ->delete();

        $childrenExist = User::query()
            ->where('guardian_email', $email)
            ->whereDate('birth_date', '>', $this->minorBirthDateThreshold())
            ->exists();

        if ($childrenExist) {
            $code = $this->buildAccessCode();

            GuardianAccessCode::create([
                'email' => $email,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            ]);

            Notification::route('mail', $email)->notify(new GuardianAccessCodeRequested($code));
        }

        $request->session()->put(self::SESSION_EMAIL_KEY, $email);
        $request->session()->forget([
            self::SESSION_VERIFIED_EMAIL_KEY,
            self::SESSION_VERIFIED_AT_KEY,
        ]);

        return redirect()
            ->route('guardian-access.verify')
            ->with('status', 'Wenn diese E-Mail bei uns als Eltern-E-Mail gespeichert ist, wurde ein Code gesendet.');
    }

    public function verify(): Response|RedirectResponse
    {
        if (! session(self::SESSION_EMAIL_KEY)) {
            return redirect()->route('guardian-access.create');
        }

        return Inertia::render('Guardian/Verify', [
            'email' => session(self::SESSION_EMAIL_KEY),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:' . self::CODE_LENGTH],
        ]);

        $email = $request->session()->get(self::SESSION_EMAIL_KEY);
        if (! $email) {
            return redirect()
                ->route('guardian-access.create')
                ->withErrors([
                    'code' => 'Bitte verifiziere deine E-Mail-Adresse erneut.',
                ]);
        }

        $rateLimitKey = $this->rateLimitKey($request, 'confirm-code', $email);

        $this->throttleAccess(
            $rateLimitKey,
            self::CONFIRM_RATE_LIMIT,
            self::CONFIRM_RATE_LIMIT_SECONDS,
            'code',
            'Zu viele Fehlversuche. Bitte warte {:seconds} Sekunden, bevor du es erneut versuchst.',
        );

        $verifiedCode = DB::transaction(function () use ($email, $data) {
            $accessCode = GuardianAccessCode::query()
                ->where('email', $email)
                ->whereNull('used_at')
                ->where('expires_at', '>=', now())
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $accessCode || ! Hash::check($data['code'], $accessCode->code_hash)) {
                return null;
            }

            $accessCode->update(['used_at' => now()]);

            return $accessCode;
        });

        if (! $verifiedCode) {
            return back()->withErrors([
                'code' => 'Der Code ist ungueltig oder abgelaufen.',
            ]);
        }

        RateLimiter::clear($rateLimitKey);

        $request->session()->regenerate();
        $request->session()->put(self::SESSION_VERIFIED_EMAIL_KEY, $email);
        $request->session()->put(self::SESSION_VERIFIED_AT_KEY, now()->toIso8601String());

        return redirect()->route('guardian-access.children');
    }

    public function children(Request $request): Response|RedirectResponse
    {
        $email = $this->guardianEmail($request);

        if (! $email) {
            return redirect()->route('guardian-access.create');
        }

        $user = $request->user();

        $childrenQuery = User::query()
            ->where(function ($query) use ($email, $user) {
                $query->where('guardian_email', $email);

                if ($user) {
                    $query->orWhere('guardian_user_id', $user->id);
                }
            })
            ->whereDate('birth_date', '>', $this->minorBirthDateThreshold())
            ->select([
                'id',
                'name',
                'email',
                'birth_date',
                'guardian_consent_at',
                'guardian_consent_rejected_at',
                'guardian_consent_revoked_at',
            ])
            ->orderBy('birth_date', 'desc');

        return Inertia::render('Guardian/Children', [
            'email' => $email,
            'hasAuthenticatedAccount' => (bool) $user,
            'children' => $childrenQuery
                ->get()
                ->map(fn (User $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'email' => $child->email,
                    'birth_date' => $child->birth_date,
                    'age' => $child->birth_date ? (int) $child->birth_date->age : null,
                    'approved_at' => $child->guardian_consent_at,
                    'rejected_at' => $child->guardian_consent_rejected_at,
                    'revoked_at' => $child->guardian_consent_revoked_at,
                ]),
        ]);
    }

    public function createAccount(Request $request): Response|RedirectResponse
    {
        $email = $this->verifiedGuardianEmail($request);

        if (! $email) {
            return redirect()
                ->route('guardian-access.create')
                ->withErrors([
                    'email' => 'Bitte verifiziere die E-Mail zuerst erneut.',
                ]);
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
        $email = $this->verifiedGuardianEmail($request);
        if (! $email) {
            return redirect()
                ->route('guardian-access.create')
                ->withErrors([
                    'email' => 'Bitte verifiziere die E-Mail zuerst erneut.',
                ]);
        }

        $this->throttleAccess(
            $this->rateLimitKey($request, 'store-account', $email),
            self::ACCOUNT_STORE_RATE_LIMIT,
            self::ACCOUNT_STORE_RATE_LIMIT_SECONDS,
            'email',
            'Zu viele Konto-Anfragen. Bitte warte {:seconds} Sekunden, bevor du es erneut versuchst.',
        );

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existingUser) {
            if (! $existingUser->birth_date) {
                return back()->withErrors([
                    'email' => 'Das bestehende Konto hat kein gueltiges Geburtsdatum. Bitte aktualisiere es zuerst im Profil.',
                ]);
            }

            if ((int) $existingUser->birth_date->age < self::MIN_GUARDIAN_AGE) {
                return back()->withErrors([
                    'email' => 'Diese E-Mail ist bereits einem Minderjaehrigen zugeordnet.',
                ]);
            }

            $this->linkGuardianAccount($existingUser, $email);
            $request->session()->forget([self::SESSION_VERIFIED_EMAIL_KEY, self::SESSION_VERIFIED_AT_KEY]);

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

        $birthDate = Carbon::parse($data['birth_date']);

        if ($birthDate->age < self::MIN_GUARDIAN_AGE) {
            throw ValidationException::withMessages([
                'birth_date' => 'Das Elternkonto muss einem volljaehrigen Erwachsenen zugeordnet werden.',
            ]);
        }

        $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name']);

        $guardian = User::create([
            'name' => trim($firstName.' '.$lastName),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'birth_date' => $birthDate->toDateString(),
            'country' => strtoupper($data['country']),
            'password' => Hash::make($data['password']),
        ]);

        $guardian->forceFill(['email_verified_at' => now()])->save();
        $this->linkGuardianAccount($guardian, $email);
        $request->session()->forget([self::SESSION_VERIFIED_EMAIL_KEY, self::SESSION_VERIFIED_AT_KEY]);

        return redirect()
            ->route('login')
            ->with('status', 'Dein Elternkonto wurde erstellt. Du kannst dich jetzt anmelden.');
    }

    public function revoke(Request $request, User $child): RedirectResponse
    {
        $email = $this->guardianEmail($request);
        $this->assertGuardianEmailAccess($email, 'code');
        $this->assertCanManageChild($request, $child, $email);

        $this->throttleAccess(
            $this->rateLimitKey($request, 'revoke-child:' . $child->id, $email),
            self::CHILD_ACTION_RATE_LIMIT,
            self::CHILD_ACTION_RATE_LIMIT_SECONDS,
            'code',
            'Zu viele Aktionen. Bitte warte {:seconds} Sekunden, bevor du es erneut versuchst.',
        );

        DB::transaction(function () use ($request, $child, $email) {
            $managedChild = User::query()
                ->whereKey($child->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCanManageChild($request, $managedChild, $email);
            $this->assertChildMinor($managedChild);

            $managedChild->forceFill([
                'guardian_consent_at' => null,
                'guardian_consent_rejected_at' => null,
                'guardian_consent_revoked_at' => now(),
                'guardian_consent_revoked_by_email' => $email,
            ])->save();

            if ($managedChild->hasRole('minor_player')) {
                $managedChild->removeRole('minor_player');
            }

            if (! $managedChild->hasRole('minor_pending_consent')) {
                $managedChild->assignRole('minor_pending_consent');
            }
        });

        return back()->with('success', 'Die Zustimmung wurde widerrufen.');
    }

    public function approve(Request $request, User $child): RedirectResponse
    {
        $email = $this->guardianEmail($request);
        $this->assertGuardianEmailAccess($email, 'code');
        $this->assertCanManageChild($request, $child, $email);

        $this->throttleAccess(
            $this->rateLimitKey($request, 'approve-child:' . $child->id, $email),
            self::CHILD_ACTION_RATE_LIMIT,
            self::CHILD_ACTION_RATE_LIMIT_SECONDS,
            'code',
            'Zu viele Aktionen. Bitte warte {:seconds} Sekunden, bevor du es erneut versuchst.',
        );

        DB::transaction(function () use ($request, $child, $email) {
            $managedChild = User::query()
                ->whereKey($child->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCanManageChild($request, $managedChild, $email);
            $this->assertChildMinor($managedChild);

            $managedChild->forceFill([
                'guardian_consent_at' => now(),
                'guardian_consent_rejected_at' => null,
                'guardian_consent_revoked_at' => null,
                'guardian_consent_revoked_by_email' => null,
                'guardian_consent_token' => null,
            ])->save();

            if ($managedChild->hasRole('minor_pending_consent')) {
                $managedChild->removeRole('minor_pending_consent');
            }

            if (! $managedChild->hasRole('minor_player')) {
                $managedChild->assignRole('minor_player');
            }

            if ($managedChild->guardian_email && ! $managedChild->guardian_user_id) {
                // Keep the link open for later if an existing parent account already exists.
                $guardian = User::query()
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $managedChild->guardian_email)])
                    ->first();

                if ($guardian) {
                    $managedChild->guardian_user_id = $guardian->id;
                    $managedChild->save();
                }
            }
        });

        return back()->with('success', 'Die Ablehnung wurde zurueckgenommen und die Zustimmung erteilt.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->throttleAccess(
            $this->rateLimitKey($request, 'logout', (string) $request->ip()),
            self::DESTROY_RATE_LIMIT,
            self::DESTROY_RATE_LIMIT_SECONDS,
            'email',
            'Du hast dich zu haeufig ausgeloggt. Bitte warte {:seconds} Sekunden, bevor du es erneut versuchst.',
        );

        $request->session()->forget([
            self::SESSION_EMAIL_KEY,
            self::SESSION_VERIFIED_EMAIL_KEY,
            self::SESSION_VERIFIED_AT_KEY,
        ]);

        if ($request->user()) {
            return redirect()->route('auth.dashboard');
        }

        return redirect()->route('guardian-access.create');
    }

    private function linkGuardianAccount(User $guardian, string $email): void
    {
        if (! $guardian->hasRole('guardian')) {
            $guardian->assignRole('guardian');
        }

        User::query()
            ->where('guardian_email', $email)
            ->whereDate('birth_date', '>', $this->minorBirthDateThreshold())
            ->update(['guardian_user_id' => $guardian->id]);
    }

    private function guardianEmail(Request $request): ?string
    {
        $email = $request->session()->get(self::SESSION_VERIFIED_EMAIL_KEY);

        if ($email && $this->isGuardianEmailVerificationFresh($request)) {
            return mb_strtolower((string) $email);
        }

        $user = $request->user();

        if ($user && $user->can('guardians.children.view')) {
            return mb_strtolower((string) $user->email);
        }

        return null;
    }

    private function verifiedGuardianEmail(Request $request): ?string
    {
        if (! $this->isGuardianEmailVerificationFresh($request)) {
            return null;
        }

        return mb_strtolower((string) $request->session()->get(self::SESSION_VERIFIED_EMAIL_KEY));
    }

    private function isGuardianEmailVerificationFresh(Request $request): bool
    {
        $email = $request->session()->get(self::SESSION_VERIFIED_EMAIL_KEY);
        $verifiedAt = $request->session()->get(self::SESSION_VERIFIED_AT_KEY);

        if (! $email || ! $verifiedAt) {
            return false;
        }

        try {
            $verified = Carbon::parse($verifiedAt);
        } catch (\Exception) {
            $request->session()->forget([self::SESSION_VERIFIED_EMAIL_KEY, self::SESSION_VERIFIED_AT_KEY]);
            return false;
        }

        if ($verified->addMinutes(self::VERIFIED_SESSION_TTL_MINUTES)->isPast()) {
            $request->session()->forget([self::SESSION_VERIFIED_EMAIL_KEY, self::SESSION_VERIFIED_AT_KEY]);
            return false;
        }

        return true;
    }

    private function canManageChild(Request $request, User $child, string $email): bool
    {
        if (mb_strtolower((string) $child->guardian_email) === $email) {
            return true;
        }

        $user = $request->user();

        return (bool) $user && (int) $child->guardian_user_id === (int) $user->id;
    }

    private function isMinor(User $user): bool
    {
        return (bool) $user->birth_date && (int) $user->birth_date->age < self::MIN_GUARDIAN_AGE;
    }

    private function assertGuardianEmailAccess(?string $email, string $errorField): void
    {
        if (! $email) {
            throw ValidationException::withMessages([
                $errorField => 'Bitte verifiziere die E-Mail zuerst erneut.',
            ]);
        }
    }

    private function assertCanManageChild(Request $request, User $child, string $email): void
    {
        if (! $this->canManageChild($request, $child, $email)) {
            throw ValidationException::withMessages([
                'code' => 'Du darfst diese Zustimmung nicht bearbeiten.',
            ]);
        }
    }

    private function assertChildMinor(User $child): void
    {
        if (! $this->isMinor($child)) {
            throw ValidationException::withMessages([
                'code' => 'Diese Aktion ist nur fuer Minderjaehrige moeglich.',
            ]);
        }
    }

    private function buildAccessCode(): string
    {
        $min = 10 ** (self::CODE_LENGTH - 1);
        $max = 10 ** self::CODE_LENGTH - 1;

        return (string) random_int($min, $max);
    }

    private function minorBirthDateThreshold(): string
    {
        return now()->subYears(16)->toDateString();
    }

    private function rateLimitKey(Request $request, string $purpose, string $email): string
    {
        return "guardian-access:{$purpose}:" . md5($email) . ':' . $request->ip();
    }

    private function throttleAccess(
        string $key,
        int $maxAttempts,
        int $decaySeconds,
        string $errorField,
        string $message
    ): void {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = max(1, (int) RateLimiter::availableIn($key));

            throw ValidationException::withMessages([
                $errorField => str_replace('{:seconds}', (string) $seconds, $message),
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }
}
