import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ClubAssetInventoryCheckoutSuiteScreen extends StatelessWidget {
  const ClubAssetInventoryCheckoutSuiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final assets = [
      _AssetItem(
        'Trikotsatz U17',
        'Ausgeliehen',
        'Team U17, Rückgabe 18.06.2026',
        AirmiusColors.amber,
        Icons.checkroom_outlined,
      ),
      _AssetItem(
        'Hallen-Schluessel',
        'Kritisch',
        'Trainer Max, Signatur erforderlich',
        AirmiusColors.red,
        Icons.vpn_key_outlined,
      ),
      _AssetItem(
        'Erste-Hilfe-Koffer',
        'Verfuegbar',
        'Sporthalle West, Prüfung faellig',
        AirmiusColors.green,
        Icons.medical_services_outlined,
      ),
      _AssetItem(
        'Beamer Vereinsheim',
        'Reserviert',
        'Vorstandssitzung, 20:00 Uhr',
        AirmiusColors.blue,
        Icons.videocam_outlined,
      ),
    ];

    final actions = [
      _ActionItem(
        'Ausleihe starten',
        'Mitglied, Team, Zeitraum, Zustand, Kaution und Rückgabehinweis erfassen.',
      ),
      _ActionItem(
        'Rückgabe prüfen',
        'Zustand, Schaden, Foto, Gebuehr, Kommentar und Verantwortliche dokumentieren.',
      ),
      _ActionItem(
        'QR-Code scannen',
        'Material direkt aufrufen, Status ändern, Standort prüfen und Historie sehen.',
      ),
      _ActionItem(
        'Wartung planen',
        'Prüffrist, Reparatur, Ersatzbeschaffung und Admin-Benachrichtigung anlegen.',
      ),
    ];

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const Text(
          'Inventar & Ausleihe',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: 'Inventar & Ausleihe',
        subtitle:
            'Vereinsmaterial, Schluessel, Trikots, Geräte, QR-Codes, Rückgabe und Audit als mobile Vereins-UI.',
        trailing: StatusPill(
          'Material',
          color: Theme.of(context).colorScheme.secondary,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('ASSET SUITE'),
                  const SizedBox(height: 10),
                  Text(
                    'Vereinsmaterial bekommt einen klaren mobilen Prozess.',
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                      height: 1.08,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Inventar, Ausleihe, Rückgabe, Zustand, Fotos, QR-Codes und Verantwortliche werden so vorbereitet, dass der Verein später alles im Dateimanager und Audit nachvollziehen kann.',
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.42,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(
                  child: MetricCard(value: '184', label: 'Objekte'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: '12', label: 'Offen'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: 'QR', label: 'Scan'),
                ),
              ],
            ),
            const SizedBox(height: 14),
            for (final asset in assets) ...[
              AirmiusPanel(
                borderColor: airmiusSemanticColor(
                  context,
                  asset.color,
                ).withValues(alpha: .44),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 50,
                      height: 50,
                      decoration: BoxDecoration(
                        color: airmiusSemanticColor(
                          context,
                          asset.color,
                        ).withValues(alpha: .14),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(
                          color: airmiusSemanticColor(
                            context,
                            asset.color,
                          ).withValues(alpha: .45),
                        ),
                      ),
                      child: Icon(
                        asset.icon,
                        color: airmiusSemanticColor(context, asset.color),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            asset.title,
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontWeight: FontWeight.w900,
                              fontSize: 16,
                            ),
                          ),
                          const SizedBox(height: 5),
                          Text(
                            asset.body,
                            style: TextStyle(
                              color: airmiusMutedColor(context),
                              height: 1.35,
                            ),
                          ),
                          const SizedBox(height: 10),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              StatusPill(
                                asset.status,
                                color: airmiusSemanticColor(
                                  context,
                                  asset.color,
                                ),
                              ),
                              const StatusPill('Audit'),
                            ],
                          ),
                        ],
                      ),
                    ),
                    Icon(
                      Icons.chevron_right,
                      color: airmiusMutedColor(context),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('MOBILE AKTIONEN'),
                  const SizedBox(height: 12),
                  for (final action in actions) ...[
                    _ActionRow(item: action),
                    if (action != actions.last) const SizedBox(height: 10),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('API READY'),
                  SizedBox(height: 10),
                  _ApiLine(
                    label: 'asset',
                    value:
                        'Name, Kategorie, Standort, Team, Zustand, Seriennummer, QR-Code',
                  ),
                  _ApiLine(
                    label: 'checkout',
                    value:
                        'Ausleiher, Zeitraum, Verantwortliche, Kaution, Unterschrift, Rückgabe',
                  ),
                  _ApiLine(
                    label: 'evidence',
                    value:
                        'Fotos, Dateien, Schadensbericht, Rechnung, Wartungsnachweis',
                  ),
                  _ApiLine(
                    label: 'audit',
                    value:
                        'Statuswechsel, Besitzer, Adminaktion, Erinnerung, Eskalation',
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AssetItem {
  const _AssetItem(this.title, this.status, this.body, this.color, this.icon);

  final String title;
  final String status;
  final String body;
  final Color color;
  final IconData icon;
}

class _ActionItem {
  const _ActionItem(this.title, this.body);

  final String title;
  final String body;
}

class _ActionRow extends StatelessWidget {
  const _ActionRow({required this.item});

  final _ActionItem item;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Container(
        width: 34,
        height: 34,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: airmiusAccentColor(context).withValues(alpha: .16),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: airmiusAccentColor(context).withValues(alpha: .42),
          ),
        ),
        child: Icon(
          Icons.task_alt_outlined,
          color: airmiusAccentColor(context),
          size: 19,
        ),
      ),
      const SizedBox(width: 12),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              item.title,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              item.body,
              style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
            ),
          ],
        ),
      ),
    ],
  );
}

class _ApiLine extends StatelessWidget {
  const _ApiLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 9),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 108,
          child: Text(
            label,
            style: TextStyle(
              color: Theme.of(context).colorScheme.secondary,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
          ),
        ),
      ],
    ),
  );
}
