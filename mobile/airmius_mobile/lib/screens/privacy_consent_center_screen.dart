import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'club_policy_documents_screen.dart';
import 'data_rights_request_screen.dart';
import 'membership_application_form_screen.dart';
import 'notification_chat_operations_screen.dart';

class PrivacyConsentCenterScreen extends StatefulWidget {
  const PrivacyConsentCenterScreen({super.key});

  @override
  State<PrivacyConsentCenterScreen> createState() => _PrivacyConsentCenterScreenState();
}

class _PrivacyConsentCenterScreenState extends State<PrivacyConsentCenterScreen> {
  bool _privacyConsent = true;
  bool _clubRulesConsent = true;
  bool _paymentConsent = true;
  bool _mediaConsent = false;
  bool _guardianConsent = true;
  bool _marketingConsent = false;

  final List<_ConsentItem> _items = const [
    _ConsentItem(title: 'Datenschutz', body: 'Verarbeitung von Profil-, Kontakt-, Vereins- und Mitgliedschaftsdaten.', status: 'Aktiv', owner: 'Airmius + Verein', icon: Icons.privacy_tip_outlined, color: AirmiusColors.blue),
    _ConsentItem(title: 'Vereinsregeln', body: 'Regeln, Satzung, Verhalten, Trainingsordnung und Vereinsbeiträge.', status: 'Akzeptiert', owner: 'ZBB', icon: Icons.gavel_outlined, color: AirmiusColors.green),
    _ConsentItem(title: 'Zahlungsdaten', body: 'Zahlmethode, Beitragsintervall, SEPA-Hinweis und Zahlungsstatus.', status: 'Erforderlich', owner: 'Vereinsfinanzen', icon: Icons.payments_outlined, color: AirmiusColors.amber),
    _ConsentItem(title: 'Medienfreigabe', body: 'Fotos, Videos, Teambeiträge und öffentliche Vereinsbeiträge.', status: 'Optional', owner: 'Verein', icon: Icons.photo_camera_outlined, color: AirmiusColors.blueDeep),
    _ConsentItem(title: 'Minderjaehrige', body: 'Erziehungsberechtigte, Notfallkontakt, Einwilligung und altersabhaengige Pflichtfelder.', status: 'Pflichtfall', owner: 'Guardian', icon: Icons.family_restroom_outlined, color: AirmiusColors.red),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
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
                        const PageTitle(title: 'Datenschutz & Einwilligungen', subtitle: 'Zustimmungen, Vereinsdokumente, Medienfreigaben, Zahlungsdaten und Minderjaehrigen-Einwilligungen.'),
                        const SizedBox(height: 16),
                        _ConsentHero(onDataRights: () => Navigator.push(context, MaterialPageRoute(builder: (_) => DataRightsRequestScreen()))),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Meine Zustimmungen',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Datenschutz akzeptiert', subtitle: 'Basis für Konto, Mitgliedschaft und Vereinsfunktionen.', value: _privacyConsent, onChanged: (value) => setState(() => _privacyConsent = value)),
                              _SwitchRow(title: 'Vereinsregeln akzeptiert', subtitle: 'Regeln und Dokumente des Vereins sind mit dem Antrag verbunden.', value: _clubRulesConsent, onChanged: (value) => setState(() => _clubRulesConsent = value)),
                              _SwitchRow(title: 'Zahlungsdaten erlaubt', subtitle: 'Beiträge, Zahlungsart und Intervall dürfen verarbeitet werden.', value: _paymentConsent, onChanged: (value) => setState(() => _paymentConsent = value)),
                              _SwitchRow(title: 'Medienfreigabe', subtitle: 'Fotos, Videos und öffentliche Vereinsbeiträge optional erlauben.', value: _mediaConsent, onChanged: (value) => setState(() => _mediaConsent = value)),
                              _SwitchRow(title: 'Erziehungsberechtigten-Einwilligung', subtitle: 'Pflicht bei minderjaehrigen Mitgliedern und Jugendteams.', value: _guardianConsent, onChanged: (value) => setState(() => _guardianConsent = value)),
                              _SwitchRow(title: 'Marketing & Updates', subtitle: 'Optionale Hinweise zu Angeboten, Sponsoren und Vereinsaktionen.', value: _marketingConsent, onChanged: (value) => setState(() => _marketingConsent = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in _items) ...[
                          _ConsentCard(item: item, onOpen: () => _toast('${item.title}: Detail vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        AirmiusPanel(
                          title: 'Verknuepfte Bereiche',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Vereinsdokumente', icon: Icons.policy_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubPolicyDocumentsScreen()))),
                              AirmiusButton(label: 'Mitgliedsantrag', icon: Icons.assignment_add, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MembershipApplicationFormScreen()))),
                              AirmiusButton(label: 'Datenrechte', icon: Icons.manage_accounts_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => DataRightsRequestScreen()))),
                              AirmiusButton(label: 'Rückfrage', icon: Icons.forum_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationChatOperationsScreen(initialTab: 'Chat')))),
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
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _ConsentHero extends StatelessWidget {
  const _ConsentHero({required this.onDataRights});

  final VoidCallback onDataRights;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF10243B), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AirmiusColors.borderStrong),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const AirmiusLogo(size: 42),
              const SizedBox(width: 12),
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('PRIVACY CENTER'), SizedBox(height: 4), Text('Einwilligungen transparent steuern', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Datenrechte', icon: Icons.manage_search_outlined, onPressed: onDataRights),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Mitgliedsdaten sind sensibel. Die App zeigt deshalb klar, welche Zustimmungen aktiv sind, welche Dokumente gelten und welche Datenrechte der User hat.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '6', label: 'Consents')), SizedBox(width: 10), Expanded(child: MetricCard(value: '5', label: 'Dokumente')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Rechte'))]),
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.title, required this.subtitle, required this.value, required this.onChanged});

  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
      child: Row(
        children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w700))])),
          Switch.adaptive(value: value, onChanged: onChanged, activeThumbColor: AirmiusColors.blue),
        ],
      ),
    );
  }
}

class _ConsentCard extends StatelessWidget {
  const _ConsentCard({required this.item, required this.onOpen});

  final _ConsentItem item;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: item.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(width: 48, height: 48, decoration: BoxDecoration(color: item.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withValues(alpha: .5))), child: Icon(item.icon, color: item.color)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(item.status, color: item.color), const SizedBox(height: 8), Text(item.owner, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)), const SizedBox(height: 6), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onOpen, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _ConsentItem {
  const _ConsentItem({required this.title, required this.body, required this.status, required this.owner, required this.icon, required this.color});

  final String title;
  final String body;
  final String status;
  final String owner;
  final IconData icon;
  final Color color;
}
