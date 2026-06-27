import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'privacy_consent_center_screen.dart';
import 'support_helpdesk_screen.dart';

class LegalStatusCenterScreen extends StatefulWidget {
  const LegalStatusCenterScreen({super.key});

  @override
  State<LegalStatusCenterScreen> createState() => _LegalStatusCenterScreenState();
}

class _LegalStatusCenterScreenState extends State<LegalStatusCenterScreen> {
  String _tab = 'Legal';
  bool _showPrivacy = true;
  bool _showTerms = true;
  bool _showMaintenance = true;
  bool _showForbidden = true;

  final List<_LegalItem> _items = const [
    _LegalItem(title: 'Datenschutzerklaerung', area: 'Legal', body: 'PrivacyPolicy, Datenverarbeitung, Mitgliedschaft, Verein, Zahlung und App-Nutzung.', status: 'Aktuell', icon: Icons.privacy_tip_outlined, color: AirmiusColors.blue),
    _LegalItem(title: 'Nutzungsbedingungen', area: 'Legal', body: 'TermsOfService, Regeln für Nutzer, Vereine, Trainer, Marketplace und Plattform.', status: 'Aktuell', icon: Icons.article_outlined, color: AirmiusColors.green),
    _LegalItem(title: 'Legal Dokument', area: 'Legal', body: 'Legal/Show für dynamische rechtliche Inhalte, Versionen und Dokumenttypen.', status: 'Versioniert', icon: Icons.gavel_outlined, color: AirmiusColors.amber),
    _LegalItem(title: 'Wartungsmodus', area: 'Status', body: 'Maintenance-Seite mit Status, Hinweis, erwarteter Dauer und Supportkontakt.', status: 'Bereit', icon: Icons.construction_outlined, color: AirmiusColors.blueDeep),
    _LegalItem(title: 'Zugriff verweigert', area: 'Status', body: 'Forbidden-Seite für fehlende Rechte, Rollen, Vereinszugriff oder gesperrte Bereiche.', status: 'Sicher', icon: Icons.block_outlined, color: AirmiusColors.red),
  ];

  List<_LegalItem> get _visibleItems => _items.where((item) => _tab == 'Alle' || item.area == _tab).toList();

  @override
  Widget build(BuildContext context) {
    final items = _visibleItems;

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
                        const PageTitle(title: 'Recht & Systemstatus', subtitle: 'Datenschutz, Nutzungsbedingungen, Legal-Dokumente, Wartungsmodus und Zugriff verweigert.'),
                        const SizedBox(height: 16),
                        _LegalHero(onOpenPrivacy: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PrivacyConsentCenterScreen()))),
                        const SizedBox(height: 16),
                        _ChoicePanel(title: 'Bereich', value: _tab, values: const ['Alle', 'Legal', 'Status'], onChanged: (value) => setState(() => _tab = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Sichtbare Seiten',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Datenschutz anzeigen', subtitle: 'PrivacyPolicy und Einwilligungscenter in der App sichtbar machen.', value: _showPrivacy, onChanged: (value) => setState(() => _showPrivacy = value)),
                              _SwitchRow(title: 'Nutzungsbedingungen anzeigen', subtitle: 'TermsOfService, Regeln und Plattformbedingungen darstellen.', value: _showTerms, onChanged: (value) => setState(() => _showTerms = value)),
                              _SwitchRow(title: 'Wartungsmodus anzeigen', subtitle: 'Maintenance-Hinweis für technische Arbeiten vorbereiten.', value: _showMaintenance, onChanged: (value) => setState(() => _showMaintenance = value)),
                              _SwitchRow(title: 'Forbidden anzeigen', subtitle: 'Zugriff verweigert für Rollen- und Rechtefaelle abbilden.', value: _showForbidden, onChanged: (value) => setState(() => _showForbidden = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in items) ...[
                          _LegalCard(item: item, onOpen: () => _toast('${item.title}: Detail vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        if (items.isEmpty) const EmptyPanel('Keine Seiten für diesen Bereich gefunden.'),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Privacy Center', icon: Icons.privacy_tip_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PrivacyConsentCenterScreen()))),
                              AirmiusButton(label: 'Support', icon: Icons.support_agent_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()))),
                              AirmiusButton(label: 'Version prüfen', icon: Icons.history_outlined, onPressed: () => _toast('Dokumentversion prüfen vorbereitet')),
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

class _LegalHero extends StatelessWidget {
  const _LegalHero({required this.onOpenPrivacy});

  final VoidCallback onOpenPrivacy;

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
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('LEGAL & STATUS'), SizedBox(height: 4), Text('Rechtliche Seiten mobil abbilden', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Privacy', icon: Icons.privacy_tip_outlined, onPressed: onOpenPrivacy),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Die Webseiten PrivacyPolicy, TermsOfService, Legal/Show, Maintenance und Forbidden werden als mobile UI für App und Store-Readiness vorbereitet.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '3', label: 'Legal')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Status')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Support'))]),
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

class _LegalCard extends StatelessWidget {
  const _LegalCard({required this.item, required this.onOpen});

  final _LegalItem item;
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
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(item.status, color: item.color), const SizedBox(height: 8), Text(item.area, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)), const SizedBox(height: 6), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onOpen, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _LegalItem {
  const _LegalItem({required this.title, required this.area, required this.body, required this.status, required this.icon, required this.color});

  final String title;
  final String area;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}
