# AIRMIUS Test-Accounts fuer MVP-Smoke-Tests

Stand: 2026-07-07

Diese Datei dokumentiert die Rollen, die lokal oder in Staging fuer Smoke Tests vorhanden sein muessen. Es werden bewusst keine produktiven Standard-Passwoerter im Repository festgelegt.

## Sicherheitsregel

- Keine festen Demo-Passwoerter in produktiven Seedern oder Commits speichern.
- Testaccounts nur in lokaler Entwicklung oder Staging verwenden.
- Passwoerter pro Umgebung im Passwortmanager oder in einem nicht versionierten `.env`-Eintrag verwalten.
- Vor Produktion pruefen: keine `.test`-Accounts, keine alten persoenlichen Admin-Seeds, Admin-2FA aktivieren.

## Empfohlene lokale Accounts

| Rolle | E-Mail lokal/Staging | Laravel-Rolle | Zweck im Smoke Test |
| --- | --- | --- | --- |
| Plattform-Admin | `admin@airmius.test` | `super_admin` oder `admin` | Adminbereich, Nutzer, Rollen, Moderation, Commerce, Settings |
| Sportler | `sportler@airmius.test` | `player` | Login, Profil, Feed, Team, Event, Chat, Upload, Mitgliedsantrag |
| Trainer | `trainer@airmius.test` | `coach` | Teamsteuerung, Training, Anwesenheit, Chat, Sport-CV-Bestaetigungen |
| Verein-Admin | `verein@airmius.test` | `club_admin` oder `club_owner` | Verein, Mitglieder, Rechnungen, Zahlungen, Einladungen, Imports |
| Elternteil | `elternteil@airmius.test` | `guardian` oder `parent` | Elternzustimmung, Kinderverknuepfung, Benachrichtigungen |

## Muss-Verknuepfungen fuer Tests

- Der Verein-Admin besitzt oder verwaltet einen Testverein.
- Der Trainer ist im Testverein und in mindestens einem Team als `coach` verknuepft.
- Der Sportler ist Mitglied im Testverein und Spieler im Testteam.
- Das Elternteil ist fuer Guardian-Flows als `guardian`/`parent` vorhanden.
- Fuer Zahlungs-Tests hat der Testverein mindestens eine offene oder bezahlte Mitgliedsrechnung.

## Sichere lokale Anlage

1. `php artisan migrate --seed` ausfuehren.
2. Falls die Accounts nicht vorhanden sind, im lokalen Adminbereich oder per Tinker anlegen.
3. Rollen im Adminbereich oder per Spatie-Rolle zuweisen.
4. Testverein, Team und Mitgliedschaften im UI anlegen oder per lokaler Seed-Erweiterung, die nicht fuer Produktion genutzt wird.
5. Passwort nach dem ersten Login aendern und im Passwortmanager der Testumgebung speichern.

## Lokale Tinker-Hilfe

Nur lokal/Staging nutzen und das Passwort nicht committen:

```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::updateOrCreate(
    ['email' => 'sportler@airmius.test'],
    [
        'name' => 'Mika Sportler',
        'first_name' => 'Mika',
        'last_name' => 'Sportler',
        'password' => Hash::make('HIER_LOKALES_PASSWORT_EINTRAGEN'),
        'email_verified_at' => now(),
        'country' => 'DE',
        'birth_date' => now()->subYears(22)->toDateString(),
        'gender' => 'not_specified',
        'status' => 'active',
        'account_status' => 'active',
    ]
);

$user->syncRoles(['player']);
```

## Seeder-Hinweis

`database/seeders/ClubsTeamsUsersSeeder.php` erzeugt Demo-Daten. Vor Staging/Produktion pruefen, ob darin persoenliche oder alte Admin-Testdaten enthalten sind. Solche Daten duerfen nicht als produktive Standard-Accounts verwendet werden.

## Smoke-Test-Reihenfolge mit Accounts

1. Plattform-Admin: Adminbereich oeffnen und Rollen/Nutzer pruefen.
2. Verein-Admin: Verein oeffnen, Mitgliedsliste und Billing pruefen.
3. Trainer: Team oeffnen, Training oder Event pruefen.
4. Sportler: Feed, Chat, Event-Zusage, Upload und Mitgliedsantrag pruefen.
5. Elternteil: Guardian-Login, Kind/Einwilligung und Benachrichtigungen pruefen.