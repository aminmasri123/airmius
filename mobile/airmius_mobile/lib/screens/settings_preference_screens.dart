import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SettingsAppearanceScreen extends StatelessWidget {
  const SettingsAppearanceScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t.t('accessibility.design'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: t.t('accessibility.design'),
        subtitle: t.t('accessibility.designDescription'),
        child: const AirmiusThemeChooser(),
      ),
    );
  }
}

class SettingsLanguageScreen extends StatelessWidget {
  const SettingsLanguageScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final accent = Theme.of(context).colorScheme.primary;
    final text = airmiusTextColor(context);
    final muted = airmiusMutedColor(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(
          scope.t('settings.language'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: scope.t('settings.language'),
        subtitle: scope.t('settings.languageBody'),
        child: AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                scope.t('settings.languageBody'),
                style: TextStyle(color: muted, height: 1.4),
              ),
              const SizedBox(height: 14),
              for (final language in AirmiusLanguage.values) ...[
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(Icons.language_outlined, color: accent),
                  title: Text(
                    language.label,
                    style: TextStyle(color: text, fontWeight: FontWeight.w900),
                  ),
                  subtitle: Text(language.code.toUpperCase()),
                  trailing: scope.language == language
                      ? Icon(Icons.check_circle, color: accent)
                      : null,
                  onTap: () => scope.setLanguage(language),
                ),
                if (language != AirmiusLanguage.values.last)
                  const Divider(height: 1),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
