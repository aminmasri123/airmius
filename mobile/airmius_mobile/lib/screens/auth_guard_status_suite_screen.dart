import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class AuthGuardStatusSuiteScreen extends StatefulWidget {
  const AuthGuardStatusSuiteScreen({super.key});

  @override
  State<AuthGuardStatusSuiteScreen> createState() =>
      _AuthGuardStatusSuiteScreenState();
}

class _AuthGuardStatusSuiteScreenState
    extends State<AuthGuardStatusSuiteScreen> {
  String _active = 'Account';
  bool _rememberDevice = true;
  bool _guardianRequired = true;
  bool _maintenanceBanner = false;

  static const _tabs = ['Account', 'Security', 'Guardian', 'System'];

  static const _steps = <_GuardStep>[
    _GuardStep(
      area: 'Account',
      title: 'Profil vervollstaendigen',
      route: 'Auth/CompleteProfile',
      status: 'Pflichtprofil',
      body:
          'Fehlende Profildaten, Rolle, Geburtsdatum, Sprache und erste Datenschutzfreigabe als Mobile-Flow abbilden.',
      icon: Icons.assignment_ind_outlined,
      primary: 'Profil speichern',
      secondary: 'Pflichtfelder zeigen',
    ),
    _GuardStep(
      area: 'Account',
      title: 'E-Mail bestätigen',
      route: 'Auth/VerifyEmail',
      status: 'Verifizierung',
      body:
          'Verify-Mail erneut senden, Tokenstatus zeigen, Countdown darstellen und User nach Erfolg in den richtigen Bereich leiten.',
      icon: Icons.mark_email_read_outlined,
      primary: 'Link erneut senden',
      secondary: 'Status prüfen',
    ),
    _GuardStep(
      area: 'Account',
      title: 'Gesperrtes Konto',
      route: 'Auth/Suspended',
      status: 'Sperre',
      body:
          'Sperrgrund, Supportweg, Appeal-Hinweis, Datenschutzkontakt und sichere Abmeldung im App-Stil zeigen.',
      icon: Icons.block_outlined,
      primary: 'Support kontaktieren',
      secondary: 'Abmelden',
      danger: true,
    ),
    _GuardStep(
      area: 'Security',
      title: 'Passwort bestätigen',
      route: 'Auth/ConfirmPassword',
      status: 'Sensitive Aktion',
      body:
          'Vor kritischen Aktionen wie Konto löschen, API Token oder Zahlungsdaten eine kompakte Passwortbestätigung anzeigen.',
      icon: Icons.lock_outline,
      primary: 'Bestätigen',
      secondary: 'Abbrechen',
    ),
    _GuardStep(
      area: 'Security',
      title: 'Zwei-Faktor Challenge',
      route: 'Auth/TwoFactorChallenge',
      status: '2FA',
      body:
          'Authenticator-Code, Recovery-Code, Fehlerzustand, Rate-Limit und Gerät merken als native Mobile-Karte übernehmen.',
      icon: Icons.security_outlined,
      primary: 'Code prüfen',
      secondary: 'Recovery nutzen',
    ),
    _GuardStep(
      area: 'Security',
      title: 'Passwort vergessen',
      route: 'Auth/ForgotPassword / ResetPassword',
      status: 'Recovery',
      body:
          'E-Mail anfordern, Reset-Token erfassen, neues Passwort setzen und klare Erfolgsmeldung wie in der Web-App anbieten.',
      icon: Icons.password_outlined,
      primary: 'Reset senden',
      secondary: 'Neues Passwort',
    ),
    _GuardStep(
      area: 'Guardian',
      title: 'Guardian Consent ausstehend',
      route: 'Auth/GuardianConsent/Pending',
      status: 'Einwilligung',
      body:
          'Minderjaehrige sehen wartende Freigabe, Guardian-Kontakt, erneutes Senden und gesperrte App-Bereiche.',
      icon: Icons.groups_outlined,
      primary: 'Erneut senden',
      secondary: 'Freigaben sehen',
    ),
    _GuardStep(
      area: 'Guardian',
      title: 'Guardian Login und Verify',
      route: 'Guardian/Login / Verify',
      status: 'Elternportal',
      body:
          'Guardian-Code, Kinderliste, Zustimmung, Ablehnung und Ablaufdatum in einer mobil lesbaren Entscheidungskarte abbilden.',
      icon: Icons.verified_user_outlined,
      primary: 'Code prüfen',
      secondary: 'Kinderdaten öffnen',
    ),
    _GuardStep(
      area: 'System',
      title: 'Forbidden / Kein Zugriff',
      route: 'Errors/Forbidden',
      status: '403',
      body:
          'Fehlende Rolle, falscher Workspace, Club-Kontext oder Altersfreigabe mit Rückweg und Support-Aktion anzeigen.',
      icon: Icons.gpp_maybe_outlined,
      primary: 'Zurück zum Dashboard',
      secondary: 'Zugriff anfragen',
    ),
    _GuardStep(
      area: 'System',
      title: 'Maintenance',
      route: 'Maintenance',
      status: 'Wartung',
      body:
          'Wartungsbanner, voraussichtliche Dauer, Status-Link und Offline-Hinweis für App-User abbilden.',
      icon: Icons.construction_outlined,
      primary: 'Status ansehen',
      secondary: 'Später erinnern',
    ),
  ];

  List<_GuardStep> get _visibleSteps =>
      _steps.where((step) => step.area == _active).toList();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const AirmiusLogo(compact: true),
      ),
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
              sliver: SliverToBoxAdapter(
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 760),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        _Hero(
                          rememberDevice: _rememberDevice,
                          guardianRequired: _guardianRequired,
                          maintenanceBanner: _maintenanceBanner,
                        ),
                        const SizedBox(height: 16),
                        _SegmentedTabs(
                          tabs: _tabs,
                          active: _active,
                          onChanged: (value) => setState(() => _active = value),
                        ),
                        const SizedBox(height: 16),
                        _SwitchPanel(
                          rememberDevice: _rememberDevice,
                          guardianRequired: _guardianRequired,
                          maintenanceBanner: _maintenanceBanner,
                          onRememberChanged: (value) =>
                              setState(() => _rememberDevice = value),
                          onGuardianChanged: (value) =>
                              setState(() => _guardianRequired = value),
                          onMaintenanceChanged: (value) =>
                              setState(() => _maintenanceBanner = value),
                        ),
                        const SizedBox(height: 16),
                        ..._visibleSteps.map(
                          (step) => Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: _GuardCard(
                              step: step,
                              onPrimary: () => openUiAction(
                                context,
                                title: step.primary,
                                body:
                                    '${step.title}: ${step.body}\n\nMobile Route: ${step.route}',
                                status: step.status,
                                icon: step.icon,
                              ),
                              onSecondary: () => openUiAction(
                                context,
                                title: step.secondary,
                                body:
                                    'Detailansicht für ${step.route}: Formularstatus, API-Fehler, Weiterleitung und Audit-Hinweis anzeigen.',
                                status: 'Guard Detail',
                                icon: Icons.manage_search_outlined,
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: 4),
                        _ChecklistPanel(
                          active: _active,
                          onOpen: () => openUiAction(
                            context,
                            title: 'Guard Flow prüfen',
                            body:
                                'Alle Auth-, Guardian-, Error- und Systemzustaende als mobile UI-Paritaet prüfen. Backend-API wird später verbunden.',
                            status: 'UI Parity',
                            icon: Icons.fact_check_outlined,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({
    required this.rememberDevice,
    required this.guardianRequired,
    required this.maintenanceBanner,
  });

  final bool rememberDevice;
  final bool guardianRequired;
  final bool maintenanceBanner;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'AUTH GUARD STATUS',
            style: TextStyle(
              color: airmiusAccentColor(context),
              fontWeight: FontWeight.w900,
              letterSpacing: .9,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Geschuetzte Web-Zustaende als App-Flows',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Complete Profile, Verify Email, Suspended, 2FA, Guardian Pending, Forbidden und Maintenance werden in der App nicht als nackte Seiten, sondern als klare mobile Entscheidungs- und Statuskarten gefuehrt.',
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.5,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _Metric(label: 'Guard Pages', value: '10'),
              _Metric(
                label: '2FA Gerät',
                value: rememberDevice ? 'Merken' : 'Einmalig',
              ),
              _Metric(
                label: 'Guardian',
                value: guardianRequired ? 'Gate aktiv' : 'Optional',
              ),
              _Metric(
                label: 'System',
                value: maintenanceBanner ? 'Wartung' : 'Normal',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _SegmentedTabs extends StatelessWidget {
  const _SegmentedTabs({
    required this.tabs,
    required this.active,
    required this.onChanged,
  });

  final List<String> tabs;
  final String active;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: tabs
            .map(
              (tab) => Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  selected: tab == active,
                  label: Text(tab),
                  onSelected: (_) => onChanged(tab),
                  selectedColor: airmiusAccentColor(
                    context,
                  ).withValues(alpha: .24),
                  backgroundColor: airmiusSurfaceSoftColor(context),
                  side: BorderSide(
                    color: tab == active
                        ? airmiusAccentColor(context)
                        : airmiusBorderColor(context),
                  ),
                  labelStyle: TextStyle(
                    color: tab == active
                        ? airmiusTextColor(context)
                        : airmiusMutedColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            )
            .toList(),
      ),
    );
  }
}

class _SwitchPanel extends StatelessWidget {
  const _SwitchPanel({
    required this.rememberDevice,
    required this.guardianRequired,
    required this.maintenanceBanner,
    required this.onRememberChanged,
    required this.onGuardianChanged,
    required this.onMaintenanceChanged,
  });

  final bool rememberDevice;
  final bool guardianRequired;
  final bool maintenanceBanner;
  final ValueChanged<bool> onRememberChanged;
  final ValueChanged<bool> onGuardianChanged;
  final ValueChanged<bool> onMaintenanceChanged;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Mobile Guard Simulation',
      subtitle:
          'Diese Schalter zeigen später API-Zustaende aus Laravel und machen die App-Flows testbar.',
      children: [
        _SwitchLine(
          title: 'Gerät bei 2FA merken',
          subtitle: 'Zeigt Recovery- und Trusted-Device-Hinweise.',
          value: rememberDevice,
          onChanged: onRememberChanged,
        ),
        _SwitchLine(
          title: 'Guardian-Freigabe erforderlich',
          subtitle: 'Sperrt sensible Bereiche bis zur Elternfreigabe.',
          value: guardianRequired,
          onChanged: onGuardianChanged,
        ),
        _SwitchLine(
          title: 'Wartungsbanner anzeigen',
          subtitle: 'Aktiviert Systemhinweis und Status-CTA.',
          value: maintenanceBanner,
          onChanged: onMaintenanceChanged,
        ),
      ],
    );
  }
}

class _SwitchLine extends StatelessWidget {
  const _SwitchLine({
    required this.title,
    required this.subtitle,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusInputColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          Switch(
            value: value,
            activeThumbColor: Theme.of(context).colorScheme.secondary,
            onChanged: onChanged,
          ),
        ],
      ),
    );
  }
}

class _GuardCard extends StatelessWidget {
  const _GuardCard({
    required this.step,
    required this.onPrimary,
    required this.onSecondary,
  });

  final _GuardStep step;
  final VoidCallback onPrimary;
  final VoidCallback onSecondary;

  @override
  Widget build(BuildContext context) {
    final accent = airmiusSemanticColor(
      context,
      step.danger ? AirmiusColors.red : AirmiusColors.blue,
    );
    return AirmiusPanel(
      borderColor: accent.withValues(alpha: .75),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: accent.withValues(alpha: .13),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: accent.withValues(alpha: .6)),
                ),
                child: Icon(step.icon, color: accent),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      step.title,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      step.route,
                      style: TextStyle(
                        color: airmiusAccentColor(context),
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              _Pill(step.status, color: accent),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            step.body,
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.45,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: step.primary,
                icon: step.icon,
                danger: step.danger,
                onPressed: onPrimary,
              ),
              AirmiusButton(
                label: step.secondary,
                icon: Icons.manage_search_outlined,
                secondary: true,
                onPressed: onSecondary,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ChecklistPanel extends StatelessWidget {
  const _ChecklistPanel({required this.active, required this.onOpen});

  final String active;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Paritaets-Check für $active',
      subtitle:
          'Was diese Suite für die Web-zu-Mobile-Konvertierung absichert.',
      children: [
        const _CheckLine(
          'Jede Web-Zustandsseite hat einen nativen Mobile-Zustand.',
        ),
        const _CheckLine(
          'Jeder Zustand hat Primary-CTA, Secondary-CTA, Status und Route-Hinweis.',
        ),
        const _CheckLine(
          'Fehler, gesperrte Bereiche und Guardian-Gates bleiben für User verstaendlich.',
        ),
        const _CheckLine(
          'Backend kommt später über Laravel API; UI-Intent ist bereits vorbereitet.',
        ),
        const SizedBox(height: 12),
        AirmiusButton(
          label: 'Guard-Flow markieren',
          icon: Icons.fact_check_outlined,
          onPressed: onOpen,
        ),
      ],
    );
  }
}

class _CheckLine extends StatelessWidget {
  const _CheckLine(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.check_circle_outline,
            color: Theme.of(context).colorScheme.secondary,
            size: 19,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
                height: 1.35,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: airmiusSurfaceColor(context).withValues(alpha: .55),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill(this.label, {required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: .75)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _GuardStep {
  const _GuardStep({
    required this.area,
    required this.title,
    required this.route,
    required this.status,
    required this.body,
    required this.icon,
    required this.primary,
    required this.secondary,
    this.danger = false,
  });

  final String area;
  final String title;
  final String route;
  final String status;
  final String body;
  final IconData icon;
  final String primary;
  final String secondary;
  final bool danger;
}
