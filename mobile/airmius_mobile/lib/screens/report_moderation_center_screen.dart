import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'notification_chat_operations_screen.dart';
import 'safety_community_operations_screen.dart';
import 'support_helpdesk_screen.dart';

class ReportModerationCenterScreen extends StatefulWidget {
  const ReportModerationCenterScreen({super.key});

  @override
  State<ReportModerationCenterScreen> createState() => _ReportModerationCenterState();
}

class _ReportModerationCenterState extends State<ReportModerationCenterScreen> {
  String _target = 'Beitrag';
  String _reason = 'Spam';
  bool _includeEvidence = true;
  bool _anonymous = false;
  bool _notifyResult = true;

  final _details = TextEditingController(text: 'Bitte pruefen, ob dieser Inhalt gegen Regeln verstoesst.');

  final List<_ModerationCase> _cases = const [
    _ModerationCase(title: 'Beitrag melden', body: 'Post, Kommentar, Bild oder Link wegen Spam, Beleidigung oder Regelverstoss melden.', status: 'User-Flow', icon: Icons.flag_outlined, color: AirmiusColors.blue),
    _ModerationCase(title: 'Nutzer melden', body: 'Profil, Chatverhalten, Missbrauch, Fake-Konto oder BelÃ¤stigung melden.', status: 'Sicherheit', icon: Icons.person_off_outlined, color: AirmiusColors.red),
    _ModerationCase(title: 'Verein melden', body: 'Falsche Vereinsdaten, Missbrauch, unerwuenschte Kontaktaufnahme oder Regelverstoss melden.', status: 'Club', icon: Icons.apartment_outlined, color: AirmiusColors.amber),
    _ModerationCase(title: 'Chat melden', body: 'Nachrichtenverlauf, Rueckfragen oder Supportkontext fuer Moderation vorbereiten.', status: 'Chat', icon: Icons.forum_outlined, color: AirmiusColors.green),
  ];

  @override
  void dispose() {
    _details.dispose();
    super.dispose();
  }

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
                        const PageTitle(title: 'Melden & Moderation', subtitle: 'Beitraege, Nutzer, Vereine, Chats, Beweise, Anonymitaet und Ergebnisbenachrichtigung.'),
                        const SizedBox(height: 16),
                        _ModerationHero(onSubmit: _submit),
                        const SizedBox(height: 16),
                        _ChoicePanel(title: 'Was moechtest du melden?', value: _target, values: const ['Beitrag', 'Nutzer', 'Verein', 'Chat', 'Datei'], onChanged: (value) => setState(() => _target = value)),
                        const SizedBox(height: 12),
                        _ChoicePanel(title: 'Grund', value: _reason, values: const ['Spam', 'Beleidigung', 'Fake', 'Datenschutz', 'Gefahr'], onChanged: (value) => setState(() => _reason = value)),
                        const SizedBox(height: 12),
                        AirmiusPanel(title: 'Details', child: AirmiusTextField(label: 'Beschreibung', controller: _details)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Meldeoptionen',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Beweise anhaengen', subtitle: 'Screenshots, Datei, Chatkontext oder Link fuer Moderation vormerken.', value: _includeEvidence, onChanged: (value) => setState(() => _includeEvidence = value)),
                              _SwitchRow(title: 'Anonym melden', subtitle: 'Identitaet gegenueber gemeldeter Person oder Verein verbergen.', value: _anonymous, onChanged: (value) => setState(() => _anonymous = value)),
                              _SwitchRow(title: 'Ergebnisbenachrichtigung', subtitle: 'Nach Abschluss per App-Update oder Chat informiert werden.', value: _notifyResult, onChanged: (value) => setState(() => _notifyResult = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in _cases) ...[
                          _CaseCard(item: item, onOpen: () => _toast('${item.title}: Moderationsdetail vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        AirmiusPanel(
                          title: 'Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Meldung senden', icon: Icons.flag_outlined, onPressed: _submit),
                              AirmiusButton(label: 'Safety Center', icon: Icons.shield_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SafetyCommunityOperationsScreen()))),
                              AirmiusButton(label: 'Chat pruefen', icon: Icons.forum_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationChatOperationsScreen(initialTab: 'Chat')))),
                              AirmiusButton(label: 'Support', icon: Icons.support_agent_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()))),
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

  void _submit() {
    _toast('Meldung vorbereiten: $_target / $_reason');
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _ModerationHero extends StatelessWidget {
  const _ModerationHero({required this.onSubmit});

  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF271621), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
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
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('SAFETY'), SizedBox(height: 4), Text('Fair melden, sauber pruefen', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Melden', icon: Icons.flag_outlined, onPressed: onSubmit),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Meldungen brauchen Kontext, Prioritaet und Transparenz. Die UI bereitet Moderationsfaelle fuer Inhalte, Profile, Vereine und Chats vor.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '5', label: 'Ziele')), SizedBox(width: 10), Expanded(child: MetricCard(value: '5', label: 'Gruende')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Optionen'))]),
        ],
      ),
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({required this.title, required this.value, required this.values, required this.onChanged});

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
              backgroundColor: AirmiusColors.card,
              labelStyle: TextStyle(color: value == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
              side: BorderSide(color: value == item ? AirmiusColors.blue : AirmiusColors.border),
            ),
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
      child: Row(children: [
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w700))])),
        Switch.adaptive(value: value, onChanged: onChanged, activeColor: AirmiusColors.blue),
      ]),
    );
  }
}

class _CaseCard extends StatelessWidget {
  const _CaseCard({required this.item, required this.onOpen});

  final _ModerationCase item;
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
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(item.status, color: item.color), const SizedBox(height: 8), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onOpen, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _ModerationCase {
  const _ModerationCase({required this.title, required this.body, required this.status, required this.icon, required this.color});

  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}
