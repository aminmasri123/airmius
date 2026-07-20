import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../models/module_definition.dart';
import '../widgets/airmius_widgets.dart';

class LocalizationCenterScreen extends StatelessWidget {
  const LocalizationCenterScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: Text(scope.t('language'), style: const TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: scope.t('language'),
        subtitle: 'Deutsch, Englisch, Franzoesisch und Arabisch mit RTL-Vorbereitung',
        trailing: StatusPill(scope.language.code),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Mehrsprachigkeit'),
                  const SizedBox(height: 8),
                  Text(scope.t('login.subtitle'), style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final language in AirmiusLanguage.values)
                        ChoiceChip(
                          selected: scope.language == language,
                          label: Text(language.label),
                          onSelected: (_) => scope.setLanguage(language),
                          selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(color: scope.language == language ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: scope.language == language ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(children: [Expanded(child: MetricCard(value: scope.language.code, label: 'Aktiv')), const SizedBox(width: 10), Expanded(child: MetricCard(value: scope.language.isRtl ? 'RTL' : 'LTR', label: 'Richtung')), const SizedBox(width: 10), const Expanded(child: MetricCard(value: '4', label: 'Sprachen'))]),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Modulübersetzungen'),
                  const SizedBox(height: 10),
                  for (final module in appModules.take(12)) ...[
                    _TranslationLine(source: module.title, translated: scope.copy(module.title)),
                    const SizedBox(height: 8),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('API-Synchronisierung'),
                  const SizedBox(height: 8),
                  const Text('Die App speichert später Sprache, Textrichtung, Benachrichtigungssprache und Public-Content-Locale über Laravel.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(label: 'Sprache speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Sprache speichern', body: 'Aktuelle Sprache im Profil speichern und Public-/App-Inhalte neu laden.', status: scope.language.code, icon: Icons.save_outlined)),
                      AirmiusButton(label: 'Übersetzungen prüfen', icon: Icons.fact_check_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Übersetzungen prüfen', body: 'Fehlende Detailtexte pro Modul sammeln, damit die Mobile-App vollstaendig lokalisiert werden kann.', status: 'L10n', icon: Icons.fact_check_outlined)),
                    ],
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

class _TranslationLine extends StatelessWidget {
  const _TranslationLine({required this.source, required this.translated});

  final String source;
  final String translated;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
      child: Row(
        children: [
          Expanded(child: Text(source, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700))),
          const SizedBox(width: 10),
          Expanded(child: Text(translated, textAlign: TextAlign.end, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
        ],
      ),
    );
  }
}
