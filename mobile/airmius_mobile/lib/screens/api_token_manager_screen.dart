import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'api_connection_screen.dart';
import 'system_admin_operations_screen.dart';

class ApiTokenManagerScreen extends StatefulWidget {
  const ApiTokenManagerScreen({super.key});

  @override
  State<ApiTokenManagerScreen> createState() => _ApiTokenManagerScreenState();
}

class _ApiTokenManagerScreenState extends State<ApiTokenManagerScreen> {
  String _scope = 'Clubs';
  bool _readAccess = true;
  bool _writeAccess = false;
  bool _webhookAccess = true;
  bool _expires = true;
  bool _rotateRequired = true;

  final _tokenName = TextEditingController(text: 'Mobile Laravel API');

  final List<_TokenItem> _tokens = const [
    _TokenItem(title: 'Mobile App Token', body: 'Clubs, Teams, Mitgliedsanträge, Feed und Notifications für Flutter-App.', status: 'Aktiv', scope: 'Clubs, Feed', icon: Icons.phone_iphone_outlined, color: AirmiusColors.blue),
    _TokenItem(title: 'Admin Dashboard Token', body: 'Admin-Module, Rechnungen, Verifizierungen, Providerkosten und Moderation.', status: 'Sensibel', scope: 'Admin', icon: Icons.admin_panel_settings_outlined, color: AirmiusColors.red),
    _TokenItem(title: 'Webhook Receiver', body: 'Payments, SubscriptionInvoices, Supporttickets und Eventupdates empfangen.', status: 'Webhook', scope: 'Payments', icon: Icons.webhook_outlined, color: AirmiusColors.green),
    _TokenItem(title: 'Read-only Reporting', body: 'Reports, Analytics, Clubauswertungen und Vorstandsexport.', status: 'Read', scope: 'Reports', icon: Icons.insights_outlined, color: AirmiusColors.amber),
  ];

  @override
  void dispose() {
    _tokenName.dispose();
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
                        const PageTitle(title: 'API Token Manager', subtitle: 'Token, Scopes, Ablauf, Rotation, Webhooks, Read/Write-Zugriff und Laravel-API-Anbindung.'),
                        const SizedBox(height: 16),
                        _ApiHero(onCreate: () => _toast('API-Token erstellen vorbereitet')),
                        const SizedBox(height: 16),
                        AirmiusPanel(title: 'Token erstellen', child: AirmiusTextField(label: 'Tokenname', controller: _tokenName)),
                        const SizedBox(height: 12),
                        _ChoicePanel(title: 'Scope', value: _scope, values: const ['Clubs', 'Members', 'Feed', 'Payments', 'Admin', 'Reports'], onChanged: (value) => setState(() => _scope = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Zugriff & Sicherheit',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Read-Zugriff', subtitle: 'Daten lesen für UI, Reports und Detailseiten.', value: _readAccess, onChanged: (value) => setState(() => _readAccess = value)),
                              _SwitchRow(title: 'Write-Zugriff', subtitle: 'Aktionen wie Antrag senden, Rückzug, Update und Adminfreigaben.', value: _writeAccess, onChanged: (value) => setState(() => _writeAccess = value)),
                              _SwitchRow(title: 'Webhook-Zugriff', subtitle: 'Payments, Notifications und Eventupdates empfangen.', value: _webhookAccess, onChanged: (value) => setState(() => _webhookAccess = value)),
                              _SwitchRow(title: 'Ablaufdatum setzen', subtitle: 'Token läuft automatisch ab und muss erneuert werden.', value: _expires, onChanged: (value) => setState(() => _expires = value)),
                              _SwitchRow(title: 'Rotation erforderlich', subtitle: 'Regelmaessige Token-Rotation für Store- und API-Sicherheit.', value: _rotateRequired, onChanged: (value) => setState(() => _rotateRequired = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final token in _tokens) ...[
                          _TokenCard(token: token, onOpen: () => _toast('${token.title}: Token-Detail vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        AirmiusPanel(
                          title: 'API-Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Token erstellen', icon: Icons.key_outlined, onPressed: () => _toast('Token erstellen vorbereitet')),
                              AirmiusButton(label: 'API Verbindung', icon: Icons.api_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ApiConnectionScreen()))),
                              AirmiusButton(label: 'System Admin', icon: Icons.admin_panel_settings_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SystemAdminOperationsScreen()))),
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

class _ApiHero extends StatelessWidget {
  const _ApiHero({required this.onCreate});

  final VoidCallback onCreate;

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
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('API TOKENS'), SizedBox(height: 4), Text('Laravel API sicher anbinden', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Neu', icon: Icons.key_outlined, onPressed: onCreate),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Die API-Webseite und der Token Manager werden als mobile UI vorbereitet: Scopes, Rechte, Webhooks, Ablauf und Rotation für die spätere Laravel-Anbindung.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '4', label: 'Tokens')), SizedBox(width: 10), Expanded(child: MetricCard(value: '6', label: 'Scopes')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Risiken'))]),
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

class _TokenCard extends StatelessWidget {
  const _TokenCard({required this.token, required this.onOpen});

  final _TokenItem token;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: token.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(width: 48, height: 48, decoration: BoxDecoration(color: token.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(16), border: Border.all(color: token.color.withValues(alpha: .5))), child: Icon(token.icon, color: token.color)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(token.status, color: token.color), const SizedBox(height: 8), Text(token.scope, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)), const SizedBox(height: 6), Text(token.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onOpen, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _TokenItem {
  const _TokenItem({required this.title, required this.body, required this.status, required this.scope, required this.icon, required this.color});

  final String title;
  final String body;
  final String status;
  final String scope;
  final IconData icon;
  final Color color;
}
