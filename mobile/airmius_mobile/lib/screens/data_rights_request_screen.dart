import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'notification_chat_operations_screen.dart';

class DataRightsRequestScreen extends StatefulWidget {
  const DataRightsRequestScreen({super.key});

  @override
  State<DataRightsRequestScreen> createState() => _DataRightsRequestScreenState();
}

class _DataRightsRequestScreenState extends State<DataRightsRequestScreen> {
  String _selectedRight = 'Datenauskunft';
  bool _includeAccount = true;
  bool _includeClub = true;
  bool _includePayments = true;
  bool _includeMessages = false;

  final _reason = TextEditingController(text: 'Ich moechte meine gespeicherten Daten einsehen.');

  final List<_RightsItem> _rights = const [
    _RightsItem(title: 'Datenauskunft', body: 'Export der gespeicherten Konto-, Profil-, Vereins- und Mitgliedschaftsdaten.', status: 'Export', icon: Icons.download_outlined, color: AirmiusColors.blue),
    _RightsItem(title: 'Daten korrigieren', body: 'Falsche Adresse, Kontaktdaten, Profilfelder oder Vereinsdaten berichtigen lassen.', status: 'Korrektur', icon: Icons.edit_note_outlined, color: AirmiusColors.green),
    _RightsItem(title: 'Verarbeitung einschraenken', body: 'Bestimmte optionale Verarbeitungen deaktivieren oder einfrieren.', status: 'Limit', icon: Icons.block_outlined, color: AirmiusColors.amber),
    _RightsItem(title: 'Loeschanfrage', body: 'Konto- oder Vereinsdaten loeschen lassen, soweit keine Pflichtaufbewahrung besteht.', status: 'Sensibel', icon: Icons.delete_outline, color: AirmiusColors.red),
  ];

  @override
  void dispose() {
    _reason.dispose();
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
                        const PageTitle(title: 'Datenrechte', subtitle: 'Auskunft, Export, Korrektur, Einschraenkung, Loeschanfrage und Rueckfrage.'),
                        const SizedBox(height: 16),
                        _RightsHero(onSubmit: _submit),
                        const SizedBox(height: 16),
                        _RightsPicker(value: _selectedRight, values: _rights.map((item) => item.title).toList(), onChanged: (value) => setState(() => _selectedRight = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Datenumfang',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Kontodaten', subtitle: 'Login, Profil, Sprache, Sicherheit und Einstellungen.', value: _includeAccount, onChanged: (value) => setState(() => _includeAccount = value)),
                              _SwitchRow(title: 'Vereinsdaten', subtitle: 'Mitgliedsanfragen, Rollen, Teams, Events und Clubzuordnung.', value: _includeClub, onChanged: (value) => setState(() => _includeClub = value)),
                              _SwitchRow(title: 'Zahlungsdaten', subtitle: 'Zahlmethode, Beitragsintervall, Status und Finanzhinweise.', value: _includePayments, onChanged: (value) => setState(() => _includePayments = value)),
                              _SwitchRow(title: 'Nachrichten', subtitle: 'Chat- und Rueckfrage-Kontext fuer Datenschutzanfrage einbeziehen.', value: _includeMessages, onChanged: (value) => setState(() => _includeMessages = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Begruendung',
                          child: AirmiusTextField(label: 'Nachricht an Support / Verein', controller: _reason),
                        ),
                        const SizedBox(height: 16),
                        for (final item in _rights) ...[
                          _RightsCard(item: item, selected: _selectedRight == item.title, onSelect: () => setState(() => _selectedRight = item.title)),
                          const SizedBox(height: 12),
                        ],
                        AirmiusPanel(
                          title: 'Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Anfrage senden', icon: Icons.send_outlined, onPressed: _submit),
                              AirmiusButton(label: 'Rueckfrage', icon: Icons.forum_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationChatOperationsScreen(initialTab: 'Chat')))),
                              AirmiusButton(label: 'Einwilligungen', icon: Icons.privacy_tip_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Einwilligungen', body: 'Einwilligungscenter ist ueber Einstellungen und Operations Hub erreichbar.', status: 'UI bereit', icon: Icons.info_outline)),
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
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$_selectedRight vorbereiten')));
  }
}

class _RightsHero extends StatelessWidget {
  const _RightsHero({required this.onSubmit});

  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF122238), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
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
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('DATENRECHTE'), SizedBox(height: 4), Text('Daten transparent verwalten', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Senden', icon: Icons.send_outlined, onPressed: onSubmit),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Die mobile UI bereitet Datenschutzanfragen fuer Laravel-API-Prozesse vor: Export, Korrektur, Einschraenkung und Loeschanfrage.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '4', label: 'Rechte')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Datenarten')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Anfrage'))]),
        ],
      ),
    );
  }
}

class _RightsPicker extends StatelessWidget {
  const _RightsPicker({required this.value, required this.values, required this.onChanged});

  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Recht auswaehlen',
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
      child: Row(
        children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w700))])),
          Switch.adaptive(value: value, onChanged: onChanged, activeColor: AirmiusColors.blue),
        ],
      ),
    );
  }
}

class _RightsCard extends StatelessWidget {
  const _RightsCard({required this.item, required this.selected, required this.onSelect});

  final _RightsItem item;
  final bool selected;
  final VoidCallback onSelect;

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
          IconButton(onPressed: onSelect, icon: Icon(selected ? Icons.radio_button_checked : Icons.radio_button_unchecked, color: selected ? AirmiusColors.blue : AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _RightsItem {
  const _RightsItem({required this.title, required this.body, required this.status, required this.icon, required this.color});

  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}
