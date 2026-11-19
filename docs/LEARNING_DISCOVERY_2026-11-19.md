# Entdecken, Kursdetails und Mein Lernen

Die Teilnehmeroberflächen wurden für Web und App überarbeitet.

- Entdecken startet direkt mit Suche, Filtern und Kurskarten. Kursbilder haben eine Ersatzdarstellung, falls ein Bild fehlt oder nicht lädt. Kursleitung, Niveau, Sprache, Umfang, tatsächliche Bewertungen und Preis bleiben sichtbar. Die App verwendet umbrechende Navigationsschaltflächen statt abgeschnittener Tabs.
- Filter: Thema, Niveau, Preis (kostenlos/kostenpflichtig), Sprache und Suchtext. Die Suche berücksichtigt auch die Kursleitung. Filter und Zurücksetzen bleiben bei leeren Ergebnissen erreichbar. Die bisherige bevorzugte Sprache mit Übersetzungs-Fallback bleibt Standard; ein expliziter Sprachfilter wählt diese Sprache aus.
- Kursdetails: Beschreibung, Lernziele, Voraussetzungen, Zielgruppe, Kapitel/Lektionen und freigegebene Vorschau. Die App hat hierfür eine eigene Detailseite vor der Einschreibung. Vorschauinhalte erhalten keinen Zugang zu Abschluss-, Notiz- oder Bewertungsaktionen eines eingeschriebenen Teilnehmers.
- Mein Lernen führt zur ersten noch nicht abgeschlossenen, zeitlich freigegebenen Lektion in Kapitelreihenfolge. Erstattete/inaktive Einschreibungen erhalten keinen Weiterlernen-Zielpunkt. Sind alle Lektionen abgeschlossen, bleibt der Kurs zum Bearbeiten von Quizzen/Aufgaben bzw. Wiederholen zugänglich.
- Kostenlos einschreiben benötigt in der App eine Bestätigung. Ein Kauf öffnet das konkrete Kursprodukt im vorhandenen nativen Marktplatz, im Web die Produktseite. Ein fehlendes Kaufangebot wird angezeigt und der Kauf deaktiviert. Rückkehr vom Kauf aktualisiert die Kursdetails. Eine offene Zahlung gewährt keinen Lernzugang.

Neue Oberflächentexte sind in Deutsch, Englisch, Französisch und Arabisch vorhanden. Gemeinsame Quelle: `resources/data/learning-experience-copy.json`; die Dart-Kopie liegt in `lib/core/learning_experience_copy.dart`.

## Verifikation

- 44 Backend-Tests mit 474 Assertions erfolgreich: neue Filter/Vorschau-/Einschreibungs- und Weiterlernen-Tests sowie vorhandene Lern-, Übersetzungs- und Commerce-Buyer-Tests.
- Der neue Kauf-Test durchläuft Warenkorb, Überweisungs-Checkout, gesperrten Zugang vor Zahlung, verweigerte Zahlungsbestätigung durch Käufer und Freischaltung nach Admin-Bestätigung. Es wird kein echter Zahlungsanbieter belastet.
- 22 Flutter-Tests erfolgreich: neue Discovery-Abläufe und bestehende Assessment-/Upload-Abläufe. Enthalten sind vier Tests bei 320 Pixel Breite und vergrößerter Schrift, einschließlich Arabisch/RTL.
- 13 Web-Tests erfolgreich: Übersetzungen, Fortsetzungsziel, Kursbild-Fallback und Assessment-Regressionsprüfungen.

Die Änderungen benötigen keine neue Datenbankmigration. Backend, zusammengehöriger Web-Build und neue App müssen veröffentlicht werden. Die Umsetzung und Prüfungen sind lokal; kein Live-Deployment, keine echte Zahlung und kein Store-Upload wurden durchgeführt.
