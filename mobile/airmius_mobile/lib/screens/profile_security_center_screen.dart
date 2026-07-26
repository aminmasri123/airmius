import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'data_rights_request_screen.dart';
import 'support_helpdesk_screen.dart';

class ProfileSecurityCenterScreen extends StatefulWidget {
  const ProfileSecurityCenterScreen({super.key});

  @override
  State<ProfileSecurityCenterScreen> createState() =>
      _ProfileSecurityCenterScreenState();
}

class _ProfileSecurityCenterScreenState
    extends State<ProfileSecurityCenterScreen> {
  bool _profileInfo = true;
  bool _passwordStrong = true;
  bool _twoFactor = true;
  bool _sessionReview = true;
  bool _deleteAvailable = true;

  final List<_ProfileSecurityItem> _items = const [
    _ProfileSecurityItem(
      title: 'Profilinformationen',
      body:
          'Name, E-Mail, Sprache, Rolle, Avatar und sichtbare Profildaten aktualisieren.',
      status: 'Aktuell',
      icon: Icons.manage_accounts_outlined,
      color: AirmiusColors.blue,
    ),
    _ProfileSecurityItem(
      title: 'Passwort ändern',
      body:
          'UpdatePasswordForm mit aktuellem Passwort, neuem Passwort und Sicherheitsfeedback.',
      status: 'Sicher',
      icon: Icons.lock_reset_outlined,
      color: AirmiusColors.green,
    ),
    _ProfileSecurityItem(
      title: 'Two-Factor Authentication',
      body:
          '2FA aktivieren, QR-Code, Recovery-Codes und Challenge-Status als mobile UI.',
      status: 'Aktiv',
      icon: Icons.phonelink_lock_outlined,
      color: AirmiusColors.amber,
    ),
    _ProfileSecurityItem(
      title: 'Andere Sessions abmelden',
      body:
          'LogoutOtherBrowserSessionsForm mit Geräten, Zeitpunkt und Abmeldeaktion.',
      status: '2 Sessions',
      icon: Icons.devices_other_outlined,
      color: AirmiusColors.blueDeep,
    ),
    _ProfileSecurityItem(
      title: 'Konto löschen',
      body:
          'DeleteUserForm mit Warnung, Datenrechte, Bestätigung und Supportkontakt.',
      status: 'Sensibel',
      icon: Icons.delete_forever_outlined,
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
                          title: 'Profil & Sicherheit',
                          subtitle:
                              'Profilinformationen, Passwort, 2FA, Browser-Sessions, Datenrechte und Konto löschen.',
                        ),
                        const SizedBox(height: 16),
                        _ProfileHero(
                          onSave: () => _toast('Profil speichern vorbereitet'),
                        ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Sicherheitsbereiche',
                          child: Column(
                            children: [
                              _SwitchRow(
                                title: 'Profilinformationen aktiv',
                                subtitle:
                                    'UpdateProfileInformationForm als mobile UI sichtbar.',
                                value: _profileInfo,
                                onChanged: (value) =>
                                    setState(() => _profileInfo = value),
                              ),
                              _SwitchRow(
                                title: 'Passwort stark',
                                subtitle:
                                    'UpdatePasswordForm und Sicherheitsfeedback aktiv.',
                                value: _passwordStrong,
                                onChanged: (value) =>
                                    setState(() => _passwordStrong = value),
                              ),
                              _SwitchRow(
                                title: '2FA eingeschaltet',
                                subtitle:
                                    'TwoFactorAuthenticationForm mit Recovery-Codes aktiv.',
                                value: _twoFactor,
                                onChanged: (value) =>
                                    setState(() => _twoFactor = value),
                              ),
                              _SwitchRow(
                                title: 'Sessions prüfen',
                                subtitle:
                                    'Andere Browser-Sessions anzeigen und abmelden.',
                                value: _sessionReview,
                                onChanged: (value) =>
                                    setState(() => _sessionReview = value),
                              ),
                              _SwitchRow(
                                title: 'Konto löschen sichtbar',
                                subtitle:
                                    'DeleteUserForm mit Warnung und Datenrechte-Hinweis.',
                                value: _deleteAvailable,
                                onChanged: (value) =>
                                    setState(() => _deleteAvailable = value),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in _items) ...[
                          _ProfileSecurityCard(
                            item: item,
                            onOpen: () =>
                                _toast('${item.title}: Detail vorbereitet'),
                          ),
                          const SizedBox(height: 12),
                        ],
                        AirmiusPanel(
                          title: 'Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(
                                label: 'Profil speichern',
                                icon: Icons.save_outlined,
                                onPressed: () =>
                                    _toast('Profil speichern vorbereitet'),
                              ),
                              AirmiusButton(
                                label: 'Datenrechte',
                                icon: Icons.manage_search_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => DataRightsRequestScreen(),
                                  ),
                                ),
                              ),
                              AirmiusButton(
                                label: 'Support',
                                icon: Icons.support_agent_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => SupportHelpdeskScreen(),
                                  ),
                                ),
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

class _ProfileHero extends StatelessWidget {
  const _ProfileHero({required this.onSave});

  final VoidCallback onSave;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF10243B), Color(0xFF0B111B)],
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
              const AirmiusAvatar('ZK', large: true),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Eyebrow('PROFILE SECURITY'),
                    SizedBox(height: 4),
                    Text(
                      'Konto sicher verwalten',
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
                label: 'Speichern',
                icon: Icons.save_outlined,
                onPressed: onSave,
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            'Die Profil-Partial-Webseiten werden als mobile UI gebuendelt: Profilinfo, Passwort, 2FA, andere Sessions und Konto löschen.',
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.45,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          const Row(
            children: [
              Expanded(
                child: MetricCard(value: '5', label: 'Bereiche'),
              ),
              SizedBox(width: 10),
              Expanded(
                child: MetricCard(value: '2', label: 'Sessions'),
              ),
              SizedBox(width: 10),
              Expanded(
                child: MetricCard(value: '1', label: '2FA'),
              ),
            ],
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

class _ProfileSecurityCard extends StatelessWidget {
  const _ProfileSecurityCard({required this.item, required this.onOpen});

  final _ProfileSecurityItem item;
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

class _ProfileSecurityItem {
  const _ProfileSecurityItem({
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
