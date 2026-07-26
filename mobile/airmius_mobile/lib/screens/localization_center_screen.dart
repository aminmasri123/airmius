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
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: Text(
          scope.t('language'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: scope.t('language'),
        subtitle: scope.t('language.subtitle'),
        trailing: StatusPill(scope.language.code),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(scope.t('language.multilingual')),
                  const SizedBox(height: 8),
                  Text(
                    scope.t('login.subtitle'),
                    style: const TextStyle(
                      color: AirmiusColors.muted,
                      height: 1.35,
                    ),
                  ),
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
                          selectedColor: AirmiusColors.blue.withValues(
                            alpha: 0.22,
                          ),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(
                            color: scope.language == language
                                ? AirmiusColors.blue
                                : AirmiusColors.border,
                          ),
                          labelStyle: TextStyle(
                            color: scope.language == language
                                ? AirmiusColors.blue
                                : AirmiusColors.muted,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: MetricCard(
                    value: scope.language.code,
                    label: scope.t('language.active'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: MetricCard(
                    value: scope.language.isRtl ? 'RTL' : 'LTR',
                    label: scope.t('language.direction'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: MetricCard(
                    value: '4',
                    label: scope.t('language.languages'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(scope.t('language.moduleTranslations')),
                  const SizedBox(height: 10),
                  for (final module in appModules.take(12)) ...[
                    _TranslationLine(
                      source: module.title,
                      translated: scope.copy(module.title),
                    ),
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
                  Eyebrow(scope.t('language.apiSync')),
                  const SizedBox(height: 8),
                  Text(
                    scope.t('language.apiSyncBody'),
                    style: const TextStyle(
                      color: AirmiusColors.muted,
                      height: 1.35,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(
                        label: scope.t('language.save'),
                        icon: Icons.save_outlined,
                        onPressed: () => openUiAction(
                          context,
                          title: scope.t('language.save'),
                          body: scope.t('language.saveBody'),
                          status: scope.language.code,
                          icon: Icons.save_outlined,
                        ),
                      ),
                      AirmiusButton(
                        label: scope.t('language.check'),
                        icon: Icons.fact_check_outlined,
                        secondary: true,
                        onPressed: () => openUiAction(
                          context,
                          title: scope.t('language.check'),
                          body: scope.t('language.checkBody'),
                          status: 'L10n',
                          icon: Icons.fact_check_outlined,
                        ),
                      ),
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
      decoration: BoxDecoration(
        color: AirmiusColors.cardSoft,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              source,
              style: const TextStyle(
                color: AirmiusColors.muted,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              translated,
              textAlign: TextAlign.end,
              style: const TextStyle(
                color: AirmiusColors.text,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
