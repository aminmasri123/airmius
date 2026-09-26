# Self-Service Web/API/App-Lueckenmatrix

Stand: 2026-09-26

Diese Inventur bewertet die vorhandenen Self-Service-, Rechnungs-, Buchungs-, Benachrichtigungs-, Dokument- und Mitgliedskartenwege ueber Web, API und mobile App. Sie ist bewusst getrennt von der Hauptcheckliste und wird durch `tests/Feature/SelfServiceChannelGapMatrixContractTest.php` gegen konkrete Routen, Controller, Mobile-Screens und vorhandene Regressionen abgesichert.

## Bewertungslogik

| Status | Bedeutung |
| --- | --- |
| Gruen | Web/API/App sind vorhanden und mindestens ein bestehender Verhaltenstest deckt den Pfad ab. |
| Gelb | Der Kernpfad ist vorhanden, aber ein Kanal ist nur teilweise umgesetzt oder die Abnahme bleibt operativ/geraeteseitig offen. |
| Rot | Kein belastbarer Produktpfad gefunden. |

## Matrix

| Bereich | Web-Weg | API-Weg | App-Weg | Bestehende Regression | Status | Luecke / Risiko |
| --- | --- | --- | --- | --- | --- | --- |
| Mitglieder-Self-Service | `GET /auth/settings`, `PUT /auth/settings`, Profil-, Privacy- und Sportprofil-Aktionen | `GET /api/v1/settings`, `PUT /api/v1/settings`, `GET /api/v1/portal`, `GET /api/v1/portal/{section}`, `GET /api/v1/me` | `settings_center_screen.dart`, `settings_detail_screen.dart`, `profile_screen.dart`, `membership_request_status_screen.dart` | `MemberPortalOverviewApiTest`, `SettingsLazyLoadingTest`, `MobileAccountProfileTest`, `ProfileInformationTest` | Gruen | Reale Store-/Geraete-Abnahme bleibt ausserhalb der Contract-Tests. |
| Rechnungen und Zahlungsstatus | Vereinsmitgliedschaftsbereich mit `storeInvoice`, Status, Zahlung, Mahnung; Abo-Rechnungsdownload | `GET /api/v1/billing/invoices`, `GET /api/v1/billing/invoices/{invoice}`, Mitgliedsrechnungs-, Zahlungs- und Mahnrouten unter `/api/v1/clubs/{club}` | `billing_detail_screen.dart`, `club_member_finance_screen.dart`, `finance_invoice_receipt_center_suite_screen.dart`, `club_membership_management_screen.dart` | `MobileClubMembershipParityApiTest`, `ClubMembershipInvoiceWorkflowTest`, `ClubInvoicePaymentStatusTest`, `AdminInvoiceManagementTest` | Gruen | Externe Provider-, Bank- und PDF-Endabnahme ist weiterhin ein Betriebsnachweis. |
| Buchungen / Ressourcen | Web-Inventar unter `/auth/club-inventory`, Finanzbuchungen und Banktransaktionsimport im Mitgliedschaftsbereich | Inventar-Checkout, QR, Loans, Movements und Maintenance unter `/api/v1/clubs/{club}/inventory`; Finance-Entries und Banktransaktionen unter `/api/v1/clubs/{club}` | `club_asset_inventory_checkout_suite_screen.dart`, `facility_booking_resource_scheduler_suite_screen.dart`, `club_inventory_qr_scanner_screen.dart`, `club_finance_cockpit_screen.dart` | `ClubInventoryApiTest`, `MobileClubMembershipParityApiTest`, `ClubInvoiceReconciliationAuditTest`, `FinanceImplementationInventoryTest` | Gelb | Inventar-/Ressourcenbuchung ist API/App-stark; eine vollstaendige Web-Paritaet fuer den Ressourcen-Scheduler ist noch nicht als eigene Browser-Regression belegt. |
| Benachrichtigungen | `GET /auth/notifications`, Einzelstatus, Alles-gelesen, Loeschen | `GET /api/v1/notifications`, Detail, read/unread, read-all, delete; Push-Device-Routen | `notifications_center_screen.dart`, `notification_detail_screen.dart`, `notification_preferences_screen.dart`, `airmius_push_notifications.dart` | `NotificationCenterFeatureTest`, `NotificationRoutingContractTest`, `NotificationDigestCommandTest`, `MobilePushDeliveryServiceTest` | Gruen | Produktive Push-/Mail-Zustellung haengt von Provider-Konfiguration und Geraetetests ab. |
| Dokumente und Dateien | `GET /auth/files`, Upload, Share, Preview, Download, Ordnerverwaltung; Club-Policy-Dokumente im Vereinsprofil | `/api/v1/files`, Upload-Intents, Folder-Share, Preview; `/api/v1/clubs/{club}/policy-documents` inkl. Download | `file_manager_screen.dart`, `file_preview_screen.dart`, `shared_file_access_screen.dart`, `club_policy_documents_screen.dart` | `FileManagerFeatureTest`, `EventFileContextWorkflowTest`, `ClubPolicyDocumentTest`, `ClubPolicyDocumentReadinessTest`, `clubPolicyDocumentsRender.test.mjs` | Gruen | Signatur-/Fachfreigabeprozesse je Dokumenttyp bleiben fachlich separat zu pruefen. |
| Digitale Mitgliedskarte | Web-Profil verweist auf Mitgliedskarte ueber mobile/self-service Oberflaechen; Check-in-Ereignisse sind in Eventteilnahmen sichtbar | `GET /api/v1/clubs/{club}/member-card`, Rotate, Verify mit optionalem Event-Check-in | `member_card_screen.dart`, `member_card_qr_scanner_screen.dart`, `digital_member_card_checkin_suite_screen.dart` | `ClubMemberCardTest`, `MobileApiContractTest`, mobile Widget-Regressionspfad im `widget_test.dart` | Gruen | Kamera-/Scanner-Verhalten auf realen Geraeten bleibt ein manueller Store-/Release-Nachweis. |

## Ausfuehrbare Regression

Der Contract-Test `SelfServiceChannelGapMatrixContractTest` prueft:

- Jede Matrix-Zeile enthaelt Web-, API-, App- und Test-Evidenz.
- Die genannten Backend-Routen existieren in `routes/auth.php` oder `routes/api.php`.
- Die genannten Controller, Mobile-Screens und Regressionstests existieren.
- Die Matrix bleibt von der Hauptcheckliste getrennt; der Test liest nur dieses Dokument und Codepfade.

Empfohlener gezielter Lauf:

```bash
php artisan test --filter=SelfServiceChannelGapMatrixContractTest
```

## Nicht als erledigt gewertet

- Keine produktiven Zahlungs-, Mail-, Push-, Bank- oder Store-Provider wurden mit dieser Matrix abgenommen.
- Keine reale Browser- oder Geraete-Session wurde durch den Contract-Test ersetzt.
- Die Hauptcheckliste bleibt absichtlich unveraendert; offene Checkboxen dort sind nicht automatisch geschlossen.
