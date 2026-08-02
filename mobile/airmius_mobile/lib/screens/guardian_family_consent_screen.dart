import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'guardian_center_screen.dart';
import 'membership_application_form_screen.dart';
import 'privacy_consent_center_screen.dart';

class GuardianFamilyConsentScreen extends StatefulWidget {
  const GuardianFamilyConsentScreen({super.key});

  @override
  State<GuardianFamilyConsentScreen> createState() =>
      _GuardianFamilyConsentScreenState();
}

class _GuardianFamilyConsentScreenState
    extends State<GuardianFamilyConsentScreen> {
  String _childStatus = 'Offen';
  bool _trainingConsent = true;
  bool _mediaConsent = false;
  bool _emergencyConsent = true;
  bool _paymentConsent = true;
  bool _clubRulesConsent = true;

  final List<_GuardianItem> _items = const [
    _GuardianItem(
      title: 'Kindkonto bestätigen',
      body:
          'Elternteil bestätigt Kindkonto, Alter, Kontakt und Verantwortlichkeit.',
      status: 'Offen',
      icon: Icons.child_care_outlined,
      color: AirmiusColors.blue,
    ),
    _GuardianItem(
      title: 'Vereinsbeitritt freigeben',
      body:
          'Mitgliedsantrag, Datenschutz, Regeln und Zahlungsdaten für Minderjährige prüfen.',
      status: 'Prüfung',
      icon: Icons.assignment_turned_in_outlined,
      color: AirmiusColors.green,
    ),
    _GuardianItem(
      title: 'Training & Events',
      body:
          'Teilnahme, Anwesenheit, Notfallkontakt und Trainerkommunikation erlauben.',
      status: 'Aktiv',
      icon: Icons.event_available_outlined,
      color: AirmiusColors.amber,
    ),
    _GuardianItem(
      title: 'Medienfreigabe',
      body:
          'Fotos, Videos, Teambeiträge und öffentliche Vereinsinhalte optional erlauben.',
      status: 'Optional',
      icon: Icons.photo_camera_outlined,
      color: AirmiusColors.red,
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 760),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const PageTitle(
                          title: 'Guardian & Elternfreigaben',
                          subtitle:
                              'Kindkonto, Vereinsbeitritt, Datenschutz, Notfallkontakt, Training, Medien und Elternstatus.',
                        ),
                        const SizedBox(height: 16),
                        _GuardianHero(
                          onApprove: () =>
                              _toast('Guardian-Freigabe vorbereiten'),
                        ),
                        const SizedBox(height: 16),
                        _ChoicePanel(
                          title: 'Kindstatus',
                          value: _childStatus,
                          values: const [
                            'Offen',
                            'Prüfung',
                            'Aktiv',
                            'Abgelehnt',
                          ],
                          onChanged: (value) =>
                              setState(() => _childStatus = value),
                        ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Einwilligungen',
                          child: Column(
                            children: [
                              _SwitchRow(
                                title: 'Training erlauben',
                                subtitle:
                                    'Teilnahme an Training, Events und Anwesenheitslisten.',
                                value: _trainingConsent,
                                onChanged: (value) =>
                                    setState(() => _trainingConsent = value),
                              ),
                              _SwitchRow(
                                title: 'Medienfreigabe erlauben',
                                subtitle:
                                    'Fotos, Videos und öffentliche Vereinsbeiträge optional erlauben.',
                                value: _mediaConsent,
                                onChanged: (value) =>
                                    setState(() => _mediaConsent = value),
                              ),
                              _SwitchRow(
                                title: 'Notfallkontakt erlauben',
                                subtitle:
                                    'Trainer und Verein dürfen Notfallkontakt einsehen.',
                                value: _emergencyConsent,
                                onChanged: (value) =>
                                    setState(() => _emergencyConsent = value),
                              ),
                              _SwitchRow(
                                title: 'Zahlungsdaten erlauben',
                                subtitle:
                                    'Beiträge, Zahlungsintervall und Zahlmethode für Kindkonto.',
                                value: _paymentConsent,
                                onChanged: (value) =>
                                    setState(() => _paymentConsent = value),
                              ),
                              _SwitchRow(
                                title: 'Vereinsregeln akzeptieren',
                                subtitle:
                                    'Regeln, Datenschutz, Beitragsordnung und Pflichtdokumente.',
                                value: _clubRulesConsent,
                                onChanged: (value) =>
                                    setState(() => _clubRulesConsent = value),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in _items) ...[
                          _GuardianCard(
                            item: item,
                            onOpen: () =>
                                _toast('${item.title}: Detail vorbereitet'),
                          ),
                          const SizedBox(height: 12),
                        ],
                        AirmiusPanel(
                          title: 'Verknüpfte Bereiche',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(
                                label: 'Guardian Center',
                                icon: Icons.family_restroom_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => GuardianCenterScreen(),
                                  ),
                                ),
                              ),
                              AirmiusButton(
                                label: 'Mitgliedsantrag',
                                icon: Icons.assignment_add,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) =>
                                        MembershipApplicationFormScreen(),
                                  ),
                                ),
                              ),
                              AirmiusButton(
                                label: 'Datenschutz',
                                icon: Icons.privacy_tip_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) =>
                                        PrivacyConsentCenterScreen(),
                                  ),
                                ),
                              ),
                              AirmiusButton(
                                label: 'Freigeben',
                                icon: Icons.verified_user_outlined,
                                onPressed: () =>
                                    _toast('Kindkonto freigeben vorbereitet'),
                              ),
                            ],
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

  void _toast(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _GuardianHero extends StatelessWidget {
  const _GuardianHero({required this.onApprove});

  final VoidCallback onApprove;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF12243A), Color(0xFF0B111B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: Theme.of(context).colorScheme.outline),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const AirmiusLogo(size: 42),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Eyebrow('GUARDIAN'),
                    SizedBox(height: 4),
                    Text(
                      'Elternfreigaben sauber steuern',
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
              AirmiusButton(
                label: 'Freigeben',
                icon: Icons.verified_user_outlined,
                onPressed: onApprove,
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            'Die Guardian-Webmodule werden als mobile UI abgebildet: Kindkonto, Elternkonto, Pending Consent, Vereinsbeitritt, Notfallkontakt und Datenschutz.',
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.45,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: MetricCard(value: '4', label: 'Flows'),
              ),
              SizedBox(width: 10),
              Expanded(
                child: MetricCard(value: '5', label: 'Consents'),
              ),
              SizedBox(width: 10),
              Expanded(
                child: MetricCard(value: '1', label: 'Kind'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({
    required this.title,
    required this.value,
    required this.values,
    required this.onChanged,
  });

  final String title;
  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: title,
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (final item in values)
            ChoiceChip(
              label: Text(item),
              selected: value == item,
              onSelected: (_) => onChanged(item),
              selectedColor: AirmiusColors.blue.withValues(alpha: .24),
              backgroundColor: airmiusSurfaceColor(context),
              labelStyle: TextStyle(
                color: value == item
                    ? airmiusTextColor(context)
                    : airmiusMutedColor(context),
                fontWeight: FontWeight.w900,
              ),
              side: BorderSide(
                color: value == item
                    ? AirmiusColors.blue
                    : airmiusBorderColor(context),
              ),
            ),
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({
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
      margin: const EdgeInsets.only(bottom: 10),
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
                    fontSize: 12,
                    height: 1.35,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          Switch.adaptive(
            value: value,
            onChanged: onChanged,
            activeThumbColor: AirmiusColors.blue,
          ),
        ],
      ),
    );
  }
}

class _GuardianCard extends StatelessWidget {
  const _GuardianCard({required this.item, required this.onOpen});

  final _GuardianItem item;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: item.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: item.color.withValues(alpha: .18),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: item.color.withValues(alpha: .5)),
            ),
            child: Icon(item.icon, color: item.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                StatusPill(item.status, color: item.color),
                const SizedBox(height: 8),
                Text(
                  item.body,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.45,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: onOpen,
            icon: Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
          ),
        ],
      ),
    );
  }
}

class _GuardianItem {
  const _GuardianItem({
    required this.title,
    required this.body,
    required this.status,
    required this.icon,
    required this.color,
  });

  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}
