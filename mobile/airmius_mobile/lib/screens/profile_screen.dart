import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_auth_state.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'badge_detail_screen.dart';
import 'conversations_center_screen.dart';
import 'edit_form_screen.dart';
import 'feed_center_screen.dart';
import 'sport_profile_detail_screen.dart';
import 'ui_action_result_screen.dart';
import 'user_profile_detail_screen.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  String _activeTab = 'overview';

  @override
  Widget build(BuildContext context) {
    final authState = AirmiusServicesScope.of(context).authState;
    final scope = AirmiusScope.of(context);
    return AnimatedBuilder(
      animation: authState,
      builder: (context, _) {
        final hasAuthUser = authState.user != null;
        final useGuestFallback = !authState.isAuthenticated;
        final user = hasAuthUser
            ? authState.user!
            : useGuestFallback
                ? const AirmiusUser(id: 0, name: 'Gast', email: 'guest@airmius.local', role: 'guest')
                : const AirmiusUser(
                    id: -1,
                    name: 'Nutzerdaten werden geladen...',
                    email: 'Bitte neu laden',
                    role: 'mitglied',
                  );
        final role = hasAuthUser ? _roleLabel(user.role) : 'Nutzer';
        final isLoading = authState.phase == AirmiusAuthPhase.loading || authState.phase == AirmiusAuthPhase.booting;

        return PageFrame(
          title: scope.t('profile.title'),
          subtitle: scope.t('profile.subtitle'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _ProfileHero(
                user: user,
                role: role,
                isLoading: isLoading,
                onOpenProfile: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => UserProfileDetailScreen(
                      name: user.name,
                      body: '${user.email} - Vereinsmitgliedschaft offen',
                      status: role,
                      context: 'Eigenes Profil',
                      ownProfile: true,
                    ),
                  ),
                ),
                onEdit: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const EditFormScreen(title: 'Profilinformationen', subtitle: 'Name, Bio, Foto, Sportprofil und Skills bearbeiten.', mode: EditFormMode.profile))),
                onMessage: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ConversationsCenterScreen())),
                onMore: () => _openMoreActions(context, authState),
              ),
              const SizedBox(height: 14),
              const Row(
                children: [
                  Expanded(child: MetricCard(value: '18', label: 'Follower')),
                  SizedBox(width: 10),
                  Expanded(child: MetricCard(value: '12', label: 'Folgt')),
                  SizedBox(width: 10),
                  Expanded(child: MetricCard(value: '9', label: 'Badges')),
                ],
              ),
              if (authState.error != null) ...[
                const SizedBox(height: 12),
                AirmiusPanel(
                  borderColor: AirmiusColors.red.withValues(alpha: .5),
                  child: Text(authState.error!, style: const TextStyle(color: AirmiusColors.muted)),
                ),
              ],
              if (authState.isAuthenticated && !hasAuthUser) ...[
                const SizedBox(height: 12),
                AirmiusPanel(
                  borderColor: AirmiusColors.blue.withValues(alpha: 0.45),
                  child: const Text(
                    'Wir haben noch keine Benutzerdaten geladen. Bitte oben auf "Daten aktualisieren" tippen.',
                    style: TextStyle(color: AirmiusColors.muted),
                  ),
                ),
              ],
              const SizedBox(height: 14),
              _ProfileTabs(activeTab: _activeTab, onChanged: (tab) => setState(() => _activeTab = tab)),
              const SizedBox(height: 14),
              _ProfileTabBody(
                activeTab: _activeTab,
                user: user,
                role: role,
                isLoading: isLoading,
                onRefresh: isLoading ? null : authState.refreshUser,
                onSignOut: authState.signOut,
              ),
            ],
          ),
        );
      },
    );
  }

  void _openMoreActions(BuildContext context, AirmiusAuthState authState) {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: AirmiusColors.card,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text('Weitere Aktionen', style: TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
                const SizedBox(height: 14),
                AirmiusButton(label: 'Profil melden', icon: Icons.flag_outlined, secondary: true, onPressed: () => Navigator.pop(sheetContext)),
                const SizedBox(height: 10),
                AirmiusButton(label: 'Daten aktualisieren', icon: Icons.refresh_outlined, secondary: true, onPressed: authState.refreshUser),
                const SizedBox(height: 10),
                AirmiusButton(label: 'Abmelden', icon: Icons.logout_outlined, danger: true, onPressed: authState.signOut),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _ProfileHero extends StatelessWidget {
  const _ProfileHero({
    required this.user,
    required this.role,
    required this.isLoading,
    required this.onOpenProfile,
    required this.onEdit,
    required this.onMessage,
    required this.onMore,
  });

  final AirmiusUser user;
  final String role;
  final bool isLoading;
  final VoidCallback onOpenProfile;
  final VoidCallback onEdit;
  final VoidCallback onMessage;
  final VoidCallback onMore;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      padding: const EdgeInsets.all(0),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(22),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Container(
              height: 132,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    AirmiusColors.blue.withValues(alpha: 0.45),
                    AirmiusColors.green.withValues(alpha: 0.20),
                    AirmiusColors.pink.withValues(alpha: 0.26),
                  ],
                ),
              ),
              child: Stack(
                children: [
                  Positioned(right: -30, top: -40, child: _Glow(size: 130, color: AirmiusColors.blue)),
                  Positioned(left: -35, bottom: -45, child: _Glow(size: 120, color: AirmiusColors.green)),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Transform.translate(
                    offset: const Offset(0, -34),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        AirmiusAvatar(user.name, imageUrl: user.avatarUrl, large: true),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Padding(
                            padding: const EdgeInsets.only(bottom: 5),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(user.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900)),
                                const SizedBox(height: 3),
                                Text(user.email, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  Transform.translate(
                    offset: const Offset(0, -18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            const StatusPill('Profil 82%'),
                            const StatusPill('DE'),
                            StatusPill(role),
                            if (isLoading) const StatusPill('Synchronisiert'),
                          ],
                        ),
                        const SizedBox(height: 14),
                        Wrap(
                          spacing: 9,
                          runSpacing: 9,
                          children: [
                            AirmiusButton(label: 'Bearbeiten', icon: Icons.edit_outlined, onPressed: onEdit),
                            AirmiusButton(label: 'Nachricht', icon: Icons.chat_bubble_outline, secondary: true, onPressed: onMessage),
                            AirmiusButton(label: 'Mehr', icon: Icons.more_horiz, secondary: true, onPressed: onMore),
                          ],
                        ),
                      ],
                    ),
                  ),
                  Material(
                    color: Colors.transparent,
                    child: InkWell(
                      onTap: onOpenProfile,
                      borderRadius: BorderRadius.circular(16),
                      child: Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AirmiusColors.input,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: AirmiusColors.border),
                        ),
                        child: const Row(
                          children: [
                            Icon(Icons.visibility_outlined, color: AirmiusColors.blue),
                            SizedBox(width: 10),
                            Expanded(child: Text('Profilvorschau, Sichtbarkeit und oeffentliche Karte oeffnen.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700, height: 1.35))),
                            Icon(Icons.chevron_right, color: AirmiusColors.muted),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Glow extends StatelessWidget {
  const _Glow({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(shape: BoxShape.circle, color: color.withValues(alpha: 0.22)),
    );
  }
}

class _ProfileTabs extends StatelessWidget {
  const _ProfileTabs({required this.activeTab, required this.onChanged});

  final String activeTab;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      padding: const EdgeInsets.all(8),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            for (final tab in _tabs)
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: _TabChip(
                  tab: tab,
                  active: activeTab == tab.key,
                  onTap: () => onChanged(tab.key),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _TabChip extends StatelessWidget {
  const _TabChip({required this.tab, required this.active, required this.onTap});

  final _ProfileTab tab;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 10),
          decoration: BoxDecoration(
            color: active ? AirmiusColors.blue.withValues(alpha: 0.16) : AirmiusColors.cardSoft,
            borderRadius: BorderRadius.circular(999),
            border: Border.all(color: active ? AirmiusColors.blue.withValues(alpha: 0.65) : AirmiusColors.border),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(tab.icon, size: 17, color: active ? AirmiusColors.blue : AirmiusColors.muted),
              const SizedBox(width: 7),
              Text(tab.label, style: TextStyle(color: active ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900, fontSize: 12)),
            ],
          ),
        ),
      ),
    );
  }
}

class _ProfileTabBody extends StatelessWidget {
  const _ProfileTabBody({
    required this.activeTab,
    required this.user,
    required this.role,
    required this.isLoading,
    required this.onRefresh,
    required this.onSignOut,
  });

  final String activeTab;
  final AirmiusUser user;
  final String role;
  final bool isLoading;
  final VoidCallback? onRefresh;
  final VoidCallback onSignOut;

  @override
  Widget build(BuildContext context) {
    return switch (activeTab) {
      'sports' => _SportsSection(),
      'posts' => _PostsSection(),
      'network' => _NetworkSection(),
      'recommendations' => _RecommendationsSection(),
      _ => _OverviewSection(user: user, role: role, isLoading: isLoading, onRefresh: onRefresh, onSignOut: onSignOut),
    };
  }
}

class _OverviewSection extends StatelessWidget {
  const _OverviewSection({required this.user, required this.role, required this.isLoading, required this.onRefresh, required this.onSignOut});

  final AirmiusUser user;
  final String role;
  final bool isLoading;
  final VoidCallback? onRefresh;
  final VoidCallback onSignOut;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const _SectionHeader(title: 'Uebersicht', subtitle: 'Profilstatus, Bio, Rollen und Schnellzugriff.'),
              const SizedBox(height: 12),
              _InfoRow(icon: Icons.person_outline, title: 'Rolle', body: role),
              _InfoRow(icon: Icons.email_outlined, title: 'E-Mail', body: user.email),
              _InfoRow(icon: Icons.verified_user_outlined, title: 'Sichtbarkeit', body: 'Oeffentliches Profil mit Netzwerk- und Sportdaten.'),
              const SizedBox(height: 12),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  AirmiusButton(label: isLoading ? 'Lade...' : 'Daten aktualisieren', icon: Icons.refresh_outlined, secondary: true, onPressed: onRefresh),
                  AirmiusButton(label: 'Abmelden', icon: Icons.logout_outlined, danger: true, onPressed: onSignOut),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        _PrivacyMatrix(),
      ],
    );
  }
}

class _SportsSection extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const _SectionHeader(title: 'Sportliches Profil', subtitle: 'Sportarten, Ziele und Erfahrungslevel.'),
          const SizedBox(height: 12),
          _SportCard(icon: Icons.directions_run, title: 'Laufen', status: 'Betreibe ich', level: 'Fortgeschritten', metrics: const [('3x', 'Training/Woche'), ('12 km', 'Bestdistanz')]),
          const SizedBox(height: 10),
          _SportCard(icon: Icons.fitness_center, title: 'Fitness', status: 'Kraft & Stabilitaet', level: 'Erfahren', metrics: const [('4', 'Skills'), ('82%', 'Profil')]),
          const SizedBox(height: 12),
          AirmiusButton(label: 'Sportart hinzufuegen', icon: Icons.add, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SportProfileDetailScreen(title: 'Sportart hinzufuegen', status: 'Neu')))),
        ],
      ),
    );
  }
}

class _PostsSection extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const _SectionHeader(title: 'Beitraege', subtitle: 'Eigene Posts, Reaktionen und Community-Aktivitaet.'),
          const SizedBox(height: 12),
          _InfoRow(icon: Icons.dynamic_feed_outlined, title: 'Feed-Beitraege', body: '9 sichtbare Beitraege mit Likes, Kommentaren und Hilfreich-Markierungen.'),
          _InfoRow(icon: Icons.forum_outlined, title: 'Kommentare', body: 'Direkt im Feed wie in Inertia schreiben und lesen.'),
          const SizedBox(height: 12),
          AirmiusButton(label: 'Feed oeffnen', icon: Icons.dynamic_feed_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const FeedCenterScreen()))),
        ],
      ),
    );
  }
}

class _NetworkSection extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const _SectionHeader(title: 'Netzwerk', subtitle: 'Freunde, Follower, Vereine und Teams.'),
          const SizedBox(height: 12),
          _InfoRow(icon: Icons.people_alt_outlined, title: 'Freunde', body: '18 Kontakte, 2 offene Anfragen.'),
          _InfoRow(icon: Icons.groups_outlined, title: 'Mitgliedschaften', body: 'ZBB offen, Airmius Running Club sichtbar.'),
          _InfoRow(icon: Icons.visibility_outlined, title: 'Sichtbarkeit', body: 'Netzwerkdaten koennen granular gesteuert werden.'),
        ],
      ),
    );
  }
}

class _RecommendationsSection extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const _SectionHeader(title: 'Empfehlungen', subtitle: 'Badges, Nachweise und Profilstaerken.'),
          const SizedBox(height: 12),
          _InfoRow(icon: Icons.workspace_premium_outlined, title: 'Badges', body: '9 Badges sichtbar, 1 neue Auszeichnung.'),
          _InfoRow(icon: Icons.recommend_outlined, title: 'Empfehlungen', body: '2 offene Empfehlungen zur Freigabe.'),
          const SizedBox(height: 12),
          AirmiusButton(label: 'Badges ansehen', icon: Icons.workspace_premium_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BadgeDetailScreen(title: 'Badges', body: 'Freigaben, Badges und sichtbare Profilnachweise.', status: '9 aktiv')))),
        ],
      ),
    );
  }
}

class _PrivacyMatrix extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const _SectionHeader(title: 'Sichtbarkeit & Datenschutz', subtitle: 'Wer darf welche Profilbereiche sehen?'),
          const SizedBox(height: 12),
          _VisibilityLine(label: 'Profil', value: 'Oeffentlich', color: AirmiusColors.blue),
          _VisibilityLine(label: 'Sportdaten', value: 'Netzwerk', color: AirmiusColors.green),
          _VisibilityLine(label: 'Vereine', value: 'Mitglieder', color: AirmiusColors.amber),
          const SizedBox(height: 12),
          AirmiusButton(label: 'Datenschutz bearbeiten', icon: Icons.visibility_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const EditFormScreen(title: 'Sichtbarkeit & Datenschutz', subtitle: 'Wer darf Profil, Sportdaten und Vereine sehen?', mode: EditFormMode.privacy)))),
        ],
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: const TextStyle(color: AirmiusColors.text, fontSize: 19, fontWeight: FontWeight.w900)),
        const SizedBox(height: 4),
        Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, height: 1.35, fontWeight: FontWeight.w700)),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.icon, required this.title, required this.body});

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 9),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AirmiusColors.blue, size: 21),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 3),
                Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SportCard extends StatelessWidget {
  const _SportCard({required this.icon, required this.title, required this.status, required this.level, required this.metrics});

  final IconData icon;
  final String title;
  final String status;
  final String level;
  final List<(String, String)> metrics;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(18), border: Border.all(color: AirmiusColors.border)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, color: AirmiusColors.blue),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 3),
                    Text(status, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
                  ],
                ),
              ),
              StatusPill(level),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              for (final metric in metrics)
                Expanded(
                  child: Column(
                    children: [
                      Text(metric.$1, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                      const SizedBox(height: 2),
                      Text(metric.$2, textAlign: TextAlign.center, style: const TextStyle(color: AirmiusColors.muted, fontSize: 11, fontWeight: FontWeight.w700)),
                    ],
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _VisibilityLine extends StatelessWidget {
  const _VisibilityLine({required this.label, required this.value, required this.color});

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(
        children: [
          Expanded(child: Text(label, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
          StatusPill(value, color: color),
        ],
      ),
    );
  }
}

class _ProfileTab {
  const _ProfileTab({required this.key, required this.label, required this.icon});

  final String key;
  final String label;
  final IconData icon;
}

const _tabs = [
  _ProfileTab(key: 'overview', label: 'Uebersicht', icon: Icons.dashboard_outlined),
  _ProfileTab(key: 'sports', label: 'Sport', icon: Icons.sports_outlined),
  _ProfileTab(key: 'posts', label: 'Beitraege', icon: Icons.dynamic_feed_outlined),
  _ProfileTab(key: 'network', label: 'Netzwerk', icon: Icons.people_alt_outlined),
  _ProfileTab(key: 'recommendations', label: 'Empfehlungen', icon: Icons.workspace_premium_outlined),
];

String _roleLabel(String role) {
  final normalized = role.toLowerCase();
  if (normalized == 'player') return 'Player';
  if (normalized == 'admin') return 'Admin';
  if (normalized == 'club_admin') return 'Vereinsadmin';
  if (normalized == 'guest') return 'Gast';
  return role.isEmpty ? 'Mitglied' : role;
}
