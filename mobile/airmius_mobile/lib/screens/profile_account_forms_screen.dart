import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'data_rights_request_screen.dart';
import 'support_helpdesk_screen.dart';

class ProfileAccountFormsScreen extends StatefulWidget {
  const ProfileAccountFormsScreen({super.key});

  @override
  State<ProfileAccountFormsScreen> createState() => _ProfileAccountFormsScreenState();
}

class _ProfileAccountFormsScreenState extends State<ProfileAccountFormsScreen> {
  String _filter = 'Alle';
  bool _showProfile = true;
  bool _showSecurity = true;
  bool _showDanger = true;

  final List<_ProfileForm> _forms = const [
    _ProfileForm(
      title: 'Profilinformationen',
      area: 'Profil',
      status: 'Bearbeiten',
      body: 'Name, Benutzername, E-Mail, Rolle, Standort und sichtbare Profilfelder wie in der Web-App.',
      icon: Icons.badge_outlined,
      color: Color(0xFF5BA7FF),
    ),
    _ProfileForm(
      title: 'Passwort aktualisieren',
      area: 'Security',
      status: 'Sicher',
      body: 'Aktuelles Passwort, neues Passwort, Bestätigung und Sicherheitsfeedback als mobile Form.',
      icon: Icons.password_outlined,
      color: Color(0xFFF8B84E),
    ),
    _ProfileForm(
      title: 'Zwei-Faktor Authentifizierung',
      area: 'Security',
      status: '2FA',
      body: '2FA aktivieren, QR-Code-Hinweis, Recovery-Codes und Statusanzeige für den Accountschutz.',
      icon: Icons.phonelink_lock_outlined,
      color: Color(0xFF2EE59D),
    ),
    _ProfileForm(
      title: 'Andere Browser-Sessions',
      area: 'Security',
      status: 'Sessions',
      body: 'Geräte und Sessions ansehen, abmelden und verdächtige Logins erkennen.',
      icon: Icons.devices_other_outlined,
      color: Color(0xFFB084FF),
    ),
    _ProfileForm(
      title: 'Konto löschen',
      area: 'Danger',
      status: 'Kritisch',
      body: 'Warnhinweise, Passwortbestätigung, Datenfolgen und Supportpfad vor endgültiger Löschung.',
      icon: Icons.delete_forever_outlined,
      color: Color(0xFFFF6B6B),
    ),
  ];

  List<_ProfileForm> get _visibleForms {
    if (_filter == 'Alle') return _forms;
    return _forms.where((form) => form.area == _filter).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF070B12),
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
              sliver: SliverToBoxAdapter(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _Header(onSupport: () => _openSupport(context)),
                    const SizedBox(height: 18),
                    const _Hero(),
                    const SizedBox(height: 18),
                    Row(
                      children: const [
                        Expanded(child: _Metric(value: '5', label: 'Formulare')),
                        SizedBox(width: 10),
                        Expanded(child: _Metric(value: '3', label: 'Security')),
                        SizedBox(width: 10),
                        Expanded(child: _Metric(value: '1', label: 'Kritisch')),
                      ],
                    ),
                    const SizedBox(height: 18),
                    _Tabs(
                      value: _filter,
                      values: const ['Alle', 'Profil', 'Security', 'Danger'],
                      onChanged: (value) => setState(() => _filter = value),
                    ),
                    const SizedBox(height: 14),
                    _VisibilityPanel(
                      showProfile: _showProfile,
                      showSecurity: _showSecurity,
                      showDanger: _showDanger,
                      onProfile: (value) => setState(() => _showProfile = value),
                      onSecurity: (value) => setState(() => _showSecurity = value),
                      onDanger: (value) => setState(() => _showDanger = value),
                    ),
                    const SizedBox(height: 14),
                    for (final form in _visibleForms.where(_isVisible)) ...[
                      _ProfileFormCard(form: form),
                      const SizedBox(height: 12),
                    ],
                    _ActionPanel(
                      onSave: () => openUiAction(
                        context,
                        title: 'Profil speichern',
                        message: 'Die mobile Formularstruktur ist bereit; Laravel speichert später Profil- und Security-Daten.',
                      ),
                      onDataRights: () => Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => DataRightsRequestScreen()),
                      ),
                      onSupport: () => _openSupport(context),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  bool _isVisible(_ProfileForm form) {
    if (form.area == 'Profil') return _showProfile;
    if (form.area == 'Security') return _showSecurity;
    return _showDanger;
  }

  void _openSupport(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()));
  }
}

class _ProfileForm {
  const _ProfileForm({
    required this.title,
    required this.area,
    required this.status,
    required this.body,
    required this.icon,
    required this.color,
  });

  final String title;
  final String area;
  final String status;
  final String body;
  final IconData icon;
  final Color color;
}

class _Header extends StatelessWidget {
  const _Header({required this.onSupport});

  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const AirmiusLogo(markOnly: true, size: 34),
        const SizedBox(width: 10),
        const Expanded(child: Text('Airmius', style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900))),
        IconButton(onPressed: onSupport, icon: const Icon(Icons.support_agent_outlined, color: Color(0xFFAFC0D8))),
      ],
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFF26364D)),
        gradient: const LinearGradient(colors: [Color(0xFF121A27), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: const [
          Text('PROFILE SETTINGS', style: TextStyle(color: Color(0xFF5BA7FF), fontSize: 12, fontWeight: FontWeight.w900)),
          SizedBox(height: 8),
          Text('Account-Formulare', style: TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900)),
          SizedBox(height: 8),
          Text(
            'Native Mobile-UI für Profilinformationen, Passwort, Zwei-Faktor, Sessions und Konto-Löschung.',
            style: TextStyle(color: Color(0xFFAFC0D8), height: 1.45, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: const Color(0xFF101722), borderRadius: BorderRadius.circular(18), border: Border.all(color: const Color(0xFF26364D))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(value, style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text(label, style: const TextStyle(color: Color(0xFFAFC0D8), fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}

class _Tabs extends StatelessWidget {
  const _Tabs({required this.value, required this.values, required this.onChanged});

  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 42,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: values.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final item = values[index];
          final active = item == value;
          return ChoiceChip(
            label: Text(item),
            selected: active,
            onSelected: (_) => onChanged(item),
            labelStyle: TextStyle(color: active ? Colors.white : const Color(0xFFAFC0D8), fontWeight: FontWeight.w900),
            selectedColor: const Color(0xFF173D68),
            backgroundColor: const Color(0xFF101722),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999), side: const BorderSide(color: Color(0xFF26364D))),
          );
        },
      ),
    );
  }
}

class _VisibilityPanel extends StatelessWidget {
  const _VisibilityPanel({
    required this.showProfile,
    required this.showSecurity,
    required this.showDanger,
    required this.onProfile,
    required this.onSecurity,
    required this.onDanger,
  });

  final bool showProfile;
  final bool showSecurity;
  final bool showDanger;
  final ValueChanged<bool> onProfile;
  final ValueChanged<bool> onSecurity;
  final ValueChanged<bool> onDanger;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Formularbereiche',
      child: Column(
        children: [
          _SwitchRow(label: 'Profil anzeigen', value: showProfile, onChanged: onProfile),
          _SwitchRow(label: 'Security anzeigen', value: showSecurity, onChanged: onSecurity),
          _SwitchRow(label: 'Kritische Aktionen anzeigen', value: showDanger, onChanged: onDanger),
        ],
      ),
    );
  }
}

class _ProfileFormCard extends StatelessWidget {
  const _ProfileFormCard({required this.form});

  final _ProfileForm form;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: const Color(0xFF101722), borderRadius: BorderRadius.circular(24), border: Border.all(color: const Color(0xFF26364D))),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(color: form.color.withValues(alpha: .14), borderRadius: BorderRadius.circular(16), border: Border.all(color: form.color.withValues(alpha: .45))),
            child: Icon(form.icon, color: form.color, size: 28),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(form.title, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900))),
                    _Pill(label: form.status, color: form.color),
                  ],
                ),
                const SizedBox(height: 8),
                Text(form.body, style: const TextStyle(color: Color(0xFFDDE7F5), height: 1.45, fontWeight: FontWeight.w600)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionPanel extends StatelessWidget {
  const _ActionPanel({required this.onSave, required this.onDataRights, required this.onSupport});

  final VoidCallback onSave;
  final VoidCallback onDataRights;
  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Schnellaktionen',
      child: Column(
        children: [
          _ActionButton(icon: Icons.save_outlined, label: 'Profil speichern', onTap: onSave),
          const SizedBox(height: 10),
          _ActionButton(icon: Icons.privacy_tip_outlined, label: 'Datenrechte öffnen', onTap: onDataRights),
          const SizedBox(height: 10),
          _ActionButton(icon: Icons.support_agent_outlined, label: 'Support kontaktieren', onTap: onSupport),
        ],
      ),
    );
  }
}

class _Panel extends StatelessWidget {
  const _Panel({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: const Color(0xFF0D131D), borderRadius: BorderRadius.circular(22), border: Border.all(color: const Color(0xFF26364D))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900)),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.label, required this.value, required this.onChanged});

  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      value: value,
      onChanged: onChanged,
      dense: true,
      contentPadding: EdgeInsets.zero,
      activeThumbColor: const Color(0xFF5BA7FF),
      title: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: const Color(0xFF111A27), borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFF26364D))),
        child: Row(
          children: [
            Icon(icon, color: AirmiusColors.blue),
            const SizedBox(width: 12),
            Expanded(child: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900))),
            const Icon(Icons.chevron_right, color: Color(0xFFAFC0D8)),
          ],
        ),
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(999), border: Border.all(color: color.withValues(alpha: .55))),
      child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w900)),
    );
  }
}
