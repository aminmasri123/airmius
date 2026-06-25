import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_auth_state.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'badge_detail_screen.dart';
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

        return DefaultTextStyle.merge(
          style: const TextStyle(fontSize: 14, decoration: TextDecoration.none),
          child: PageFrame(
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
                onMore: () => _openMoreActions(context, authState),
              ),
              const SizedBox(height: 14),
              _ProfileStatsGrid(user: user),
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
    required this.onMore,
  });

  final AirmiusUser user;
  final String role;
  final bool isLoading;
  final VoidCallback onOpenProfile;
  final VoidCallback onEdit;
  final VoidCallback onMore;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      padding: const EdgeInsets.all(0),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(18),
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
                  Positioned.fill(
                    bottom: 0,
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: [
                            Colors.transparent,
                            AirmiusColors.card.withValues(alpha: 0.96),
                          ],
                        ),
                      ),
                    ),
                  ),
                  Positioned(right: -28, top: -42, child: _Glow(size: 126, color: AirmiusColors.blue)),
                  Positioned(left: -34, bottom: -48, child: _Glow(size: 118, color: AirmiusColors.green)),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Transform.translate(
                    offset: const Offset(0, -54),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _ProfilePhoto(name: user.name, imageUrl: user.avatarUrl),
                        const SizedBox(height: 14),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            StatusPill(_visibilityLabel(user.profileVisibility), color: AirmiusColors.muted),
                            if (user.gamification != null) StatusPill('Level ${user.gamification!.level}'),
                            StatusPill(role),
                            if (isLoading) const StatusPill('Synchronisiert'),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Text(user.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontSize: 30, fontWeight: FontWeight.w900, height: 1.05)),
                        const SizedBox(height: 10),
                        Wrap(
                          spacing: 14,
                          runSpacing: 8,
                          children: [
                            if (user.email.isNotEmpty) _ProfileMeta(icon: Icons.mail_outline, label: user.email),
                            if (user.clubs.isNotEmpty || user.teams.isNotEmpty) _ProfileMeta(icon: Icons.groups_outlined, label: '${user.clubs.length + user.teams.length} Bereiche'),
                          ],
                        ),
                        if (user.sportProfiles.isNotEmpty) ...[
                          const SizedBox(height: 14),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              for (final profile in user.sportProfiles)
                                _ProfileTag('${profile.sportName}${profile.experienceLevel == null ? '' : ' - ${_levelLabel(profile.experienceLevel!)}'}'),
                            ],
                          ),
                        ],
                      ],
                    ),
                  ),
                  Transform.translate(
                    offset: const Offset(0, -34),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Wrap(
                          spacing: 9,
                          runSpacing: 9,
                          children: [
                            AirmiusButton(label: 'Bearbeiten', icon: Icons.edit_outlined, onPressed: onEdit),
                            AirmiusButton(label: 'Mehr', icon: Icons.more_horiz, secondary: true, onPressed: onMore),
                          ],
                        ),
                      ],
                    ),
                  ),
                  Transform.translate(
                    offset: const Offset(0, -20),
                    child: Material(
                      color: Colors.transparent,
                      child: InkWell(
                        onTap: onOpenProfile,
                        borderRadius: BorderRadius.circular(12),
                        child: Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: AirmiusColors.input,
                            borderRadius: BorderRadius.circular(12),
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

class _ProfilePhoto extends StatelessWidget {
  const _ProfilePhoto({required this.name, this.imageUrl});

  final String name;
  final String? imageUrl;

  @override
  Widget build(BuildContext context) {
    final resolvedImageUrl = resolveAirmiusImageUrl(imageUrl);
    final initials = initialsFromName(name, fallback: '??');

    return Container(
      width: 112,
      height: 112,
      decoration: BoxDecoration(
        color: AirmiusColors.blue,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AirmiusColors.card, width: 4),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: .28), blurRadius: 18, offset: const Offset(0, 8)),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: resolvedImageUrl == null
          ? Center(child: Text(initials, style: const TextStyle(color: Colors.white, fontSize: 34, fontWeight: FontWeight.w900)))
          : Image.network(
              resolvedImageUrl,
              fit: BoxFit.cover,
              webHtmlElementStrategy: WebHtmlElementStrategy.prefer,
              errorBuilder: (_, __, ___) => Center(child: Text(initials, style: const TextStyle(color: Colors.white, fontSize: 34, fontWeight: FontWeight.w900))),
            ),
    );
  }
}

class _ProfileMeta extends StatelessWidget {
  const _ProfileMeta({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 17, color: AirmiusColors.muted),
        const SizedBox(width: 5),
        Text(label, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700, fontSize: 13)),
      ],
    );
  }
}

class _ProfileTag extends StatelessWidget {
  const _ProfileTag(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(999), border: Border.all(color: AirmiusColors.border)),
      child: Text(label, style: const TextStyle(color: AirmiusColors.text, fontSize: 12, fontWeight: FontWeight.w800)),
    );
  }
}

class _ProfileStatsGrid extends StatelessWidget {
  const _ProfileStatsGrid({required this.user});

  final AirmiusUser user;

  @override
  Widget build(BuildContext context) {
    final stats = [
      if (user.followersCount != null) ('${user.followersCount}', 'Follower'),
      if (user.followingCount != null) ('${user.followingCount}', 'Folgt'),
      if (user.postsCount != null) ('${user.postsCount}', 'Beitraege'),
      if (user.gamification != null) ('${user.gamification!.level}', 'Level'),
      if (user.gamification != null) ('${user.gamification!.earnedToday}', 'Heute XP'),
    ];
    if (stats.isEmpty) return const SizedBox.shrink();

    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 640 ? 5 : 2;
        final width = (constraints.maxWidth - (columns - 1) * 8) / columns;

        return Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final stat in stats)
              SizedBox(
                width: width,
                child: AirmiusPanel(
                  padding: const EdgeInsets.all(13),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(stat.$1, style: const TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
                      const SizedBox(height: 3),
                      Text(stat.$2.toUpperCase(), style: const TextStyle(color: AirmiusColors.muted, fontSize: 11, fontWeight: FontWeight.w900)),
                    ],
                  ),
                ),
              ),
          ],
        );
      },
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
      child: LayoutBuilder(
        builder: (context, constraints) {
          final columns = constraints.maxWidth >= 560 ? 5 : 2;
          final width = (constraints.maxWidth - (columns - 1) * 8) / columns;

          return Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final tab in _tabs)
                SizedBox(
                  width: width,
                  child: _TabChip(
                    tab: tab,
                    active: activeTab == tab.key,
                    onTap: () => onChanged(tab.key),
                  ),
                ),
            ],
          );
        },
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
        borderRadius: BorderRadius.circular(10),
        child: Container(
          constraints: const BoxConstraints(minHeight: 40),
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
          decoration: BoxDecoration(
            color: active ? AirmiusColors.blue : Colors.transparent,
            borderRadius: BorderRadius.circular(10),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(tab.icon, size: 17, color: active ? Colors.white : AirmiusColors.muted),
              const SizedBox(width: 7),
              Flexible(child: Text(tab.label, overflow: TextOverflow.ellipsis, style: TextStyle(color: active ? Colors.white : AirmiusColors.muted, fontWeight: FontWeight.w900, fontSize: 12))),
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
      'sports' => _SportsSection(user: user),
      'posts' => _PostsSection(user: user),
      'network' => _NetworkSection(user: user),
      'recommendations' => _RecommendationsSection(user: user),
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
        if (user.gamification != null) ...[
          _GamificationOverview(gamification: user.gamification!, badges: user.badges),
          const SizedBox(height: 14),
        ],
        AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const _SectionHeader(title: 'Profil', subtitle: 'Bio, Sportarten und oeffentliche Einordnung.'),
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: AirmiusColors.bg, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border, style: BorderStyle.solid)),
                child: Text(user.bio?.trim().isNotEmpty == true ? user.bio!.trim() : 'Dieses Profil hat noch keine Bio.', style: const TextStyle(color: AirmiusColors.muted, fontSize: 15, height: 1.45, fontWeight: FontWeight.w700)),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            AirmiusButton(label: isLoading ? 'Lade...' : 'Daten aktualisieren', icon: Icons.refresh_outlined, secondary: true, onPressed: onRefresh),
            AirmiusButton(label: 'Abmelden', icon: Icons.logout_outlined, danger: true, onPressed: onSignOut),
          ],
        ),
      ],
    );
  }
}

class _GamificationOverview extends StatelessWidget {
  const _GamificationOverview({required this.gamification, required this.badges});

  final AirmiusGamification gamification;
  final List<AirmiusUserBadge> badges;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      padding: const EdgeInsets.all(0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    if (gamification.title?.isNotEmpty == true) StatusPill(gamification.title!),
                    if (gamification.streakDays > 0) StatusPill('${gamification.streakDays} Tage Streak', color: AirmiusColors.muted),
                    if (gamification.healthLabel?.isNotEmpty == true) StatusPill(gamification.healthLabel!, color: AirmiusColors.muted),
                  ],
                ),
                const SizedBox(height: 18),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('FORTSCHRITT', style: TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w900)),
                          const SizedBox(height: 5),
                          Text('Level ${gamification.level}', style: const TextStyle(color: AirmiusColors.text, fontSize: 30, fontWeight: FontWeight.w900)),
                          const SizedBox(height: 4),
                          Text('${gamification.xp} XP von ${gamification.nextLevelXp} XP', style: const TextStyle(color: AirmiusColors.muted, fontSize: 20, fontWeight: FontWeight.w800)),
                          const SizedBox(height: 3),
                          Text('Noch ${gamification.xpToNextLevel} XP bis zum naechsten Level', style: const TextStyle(color: AirmiusColors.muted, fontSize: 11, fontWeight: FontWeight.w900)),
                        ],
                      ),
                    ),
                    if (gamification.trustScore != null)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                        decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(12), border: Border.all(color: AirmiusColors.border)),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('${gamification.trustScore}', style: const TextStyle(color: AirmiusColors.green, fontSize: 24, fontWeight: FontWeight.w900)),
                            const Text('TRUST SCORE', style: TextStyle(color: AirmiusColors.muted, fontSize: 10, fontWeight: FontWeight.w900)),
                            const SizedBox(height: 3),
                            Text('Heute ${gamification.earnedToday} XP', style: const TextStyle(color: AirmiusColors.muted, fontSize: 11, fontWeight: FontWeight.w700)),
                          ],
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 18),
                ClipRRect(
                  borderRadius: BorderRadius.circular(999),
                  child: LinearProgressIndicator(
                    value: (gamification.progress.clamp(0, 100)).toDouble() / 100,
                    minHeight: 12,
                    backgroundColor: AirmiusColors.input,
                    color: AirmiusColors.blue,
                  ),
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.all(18),
            decoration: const BoxDecoration(
              color: AirmiusColors.bg,
              border: Border(top: BorderSide(color: AirmiusColors.border)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text('BADGES', style: TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w900)),
                const SizedBox(height: 12),
                if (badges.isEmpty)
                  const Text('Noch keine Badges vorhanden.', style: TextStyle(color: AirmiusColors.muted, fontSize: 14, fontWeight: FontWeight.w700))
                else
                  GridView.count(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    crossAxisCount: 2,
                    mainAxisSpacing: 10,
                    crossAxisSpacing: 10,
                    childAspectRatio: 2.2,
                    children: [
                      for (final badge in badges) _BadgeTile(icon: _badgeIcon(badge.icon), label: badge.name),
                    ],
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _BadgeTile extends StatelessWidget {
  const _BadgeTile({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(color: AirmiusColors.card, borderRadius: BorderRadius.circular(12), border: Border.all(color: AirmiusColors.border)),
      child: Row(
        children: [
          Icon(icon, color: AirmiusColors.text, size: 23),
          const SizedBox(width: 9),
          Expanded(child: Text(label, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800, fontSize: 13))),
        ],
      ),
    );
  }
}

class _SportsSection extends StatelessWidget {
  const _SportsSection({required this.user});

  final AirmiusUser user;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const _SectionHeader(title: 'Sportliches Profil', subtitle: 'Sportarten, Ziele und Erfahrungslevel.'),
          const SizedBox(height: 12),
          if (user.sportProfiles.isEmpty)
            const _EmptyProfileState(icon: Icons.sports_outlined, text: 'Noch keine Sportarten im Profil.')
          else
            for (final profile in user.sportProfiles) ...[
              _SportCard(
                icon: Icons.sports_outlined,
                title: profile.sportName,
                status: _statusLabel(profile.status),
                level: _levelLabel(profile.experienceLevel),
                metrics: _profileMetrics(profile.metrics),
              ),
              const SizedBox(height: 10),
            ],
          const SizedBox(height: 12),
          AirmiusButton(label: 'Sportart hinzufuegen', icon: Icons.add, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SportProfileDetailScreen(title: 'Sportart hinzufuegen', status: 'Neu')))),
        ],
      ),
    );
  }
}

class _PostsSection extends StatelessWidget {
  const _PostsSection({required this.user});

  final AirmiusUser user;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const _SectionHeader(title: 'Beitraege', subtitle: 'Eigene Posts, Reaktionen und Community-Aktivitaet.'),
          const SizedBox(height: 12),
          if (user.postsCount == null)
            const _EmptyProfileState(icon: Icons.dynamic_feed_outlined, text: 'Beitragsdaten wurden noch nicht geladen.')
          else
            _InfoRow(icon: Icons.dynamic_feed_outlined, title: 'Feed-Beitraege', body: '${user.postsCount} sichtbare Beitraege.'),
          const SizedBox(height: 12),
          AirmiusButton(label: 'Feed oeffnen', icon: Icons.dynamic_feed_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const FeedCenterScreen()))),
        ],
      ),
    );
  }
}

class _NetworkSection extends StatelessWidget {
  const _NetworkSection({required this.user});

  final AirmiusUser user;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const _SectionHeader(title: 'Netzwerk', subtitle: 'Freunde, Follower, Vereine und Teams.'),
          const SizedBox(height: 12),
          if (user.followersCount != null) _InfoRow(icon: Icons.people_alt_outlined, title: 'Follower', body: '${user.followersCount} Follower.'),
          if (user.followingCount != null) _InfoRow(icon: Icons.person_add_alt_1_outlined, title: 'Folgt', body: '${user.followingCount} gefolgte Profile.'),
          if (user.clubs.isEmpty && user.teams.isEmpty)
            const _EmptyProfileState(icon: Icons.groups_outlined, text: 'Keine Vereine oder Teams geladen.')
          else ...[
            for (final club in user.clubs) _InfoRow(icon: Icons.apartment_outlined, title: club.name, body: club.subtitle ?? 'Verein'),
            for (final team in user.teams) _InfoRow(icon: Icons.groups_outlined, title: team.name, body: team.subtitle ?? 'Team'),
          ],
        ],
      ),
    );
  }
}

class _RecommendationsSection extends StatelessWidget {
  const _RecommendationsSection({required this.user});

  final AirmiusUser user;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const _SectionHeader(title: 'Empfehlungen', subtitle: 'Badges, Nachweise und Profilstaerken.'),
          const SizedBox(height: 12),
          if (user.badges.isEmpty)
            const _EmptyProfileState(icon: Icons.workspace_premium_outlined, text: 'Noch keine Badges vorhanden.')
          else
            for (final badge in user.badges) _InfoRow(icon: _badgeIcon(badge.icon), title: badge.name, body: badge.description ?? 'Badge'),
          const SizedBox(height: 12),
          AirmiusButton(label: 'Badges ansehen', icon: Icons.workspace_premium_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BadgeDetailScreen(title: 'Badges', body: user.badges.isEmpty ? 'Noch keine Badges vorhanden.' : user.badges.map((badge) => badge.name).join(', '), status: '${user.badges.length} aktiv')))),
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
        Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 13, height: 1.35, fontWeight: FontWeight.w700)),
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
                Text(body, style: const TextStyle(color: AirmiusColors.muted, fontSize: 13, height: 1.3, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyProfileState extends StatelessWidget {
  const _EmptyProfileState({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
      child: Row(
        children: [
          Icon(icon, color: AirmiusColors.blue),
          const SizedBox(width: 10),
          Expanded(child: Text(text, style: const TextStyle(color: AirmiusColors.muted, fontSize: 13, fontWeight: FontWeight.w700, height: 1.35))),
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
              if (level.isNotEmpty) StatusPill(level),
            ],
          ),
          if (metrics.isNotEmpty) ...[
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

String _visibilityLabel(String? visibility) {
  return switch ((visibility ?? 'public').toLowerCase()) {
    'public' => 'Oeffentliches Profil',
    'members' => 'Nur Mitglieder',
    'friends' => 'Nur Kontakte',
    'private' => 'Privates Profil',
    final value when value.isNotEmpty => value,
    _ => 'Profil',
  };
}

String _statusLabel(String? status) {
  return switch ((status ?? '').toLowerCase()) {
    'active' => 'Aktiv',
    'competing' => 'Wettkampf',
    'training' => 'Training',
    'paused' => 'Pausiert',
    final value when value.isNotEmpty => value,
    _ => 'Sportprofil',
  };
}

String _levelLabel(String? level) {
  return switch ((level ?? '').toLowerCase()) {
    'beginner' => 'Anfaenger',
    'intermediate' => 'Fortgeschritten',
    'advanced' => 'Fortgeschritten',
    'expert' => 'Erfahren',
    'elite' => 'Elite',
    final value when value.isNotEmpty => value,
    _ => '',
  };
}

List<(String, String)> _profileMetrics(JsonMap metrics) {
  final result = <(String, String)>[];
  for (final entry in metrics.entries) {
    final value = entry.value;
    if (value == null || '$value'.trim().isEmpty) continue;
    result.add(('$value', _metricLabel(entry.key)));
    if (result.length == 2) break;
  }
  return result;
}

String _metricLabel(String key) {
  return key.replaceAll('_', ' ').trim();
}

IconData _badgeIcon(String? icon) {
  final normalized = (icon ?? '').toLowerCase();
  if (normalized.contains('team') || normalized.contains('group')) return Icons.groups_2_outlined;
  if (normalized.contains('run') || normalized.contains('sport')) return Icons.directions_run_outlined;
  if (normalized.contains('verify') || normalized.contains('check')) return Icons.verified_outlined;
  if (normalized.contains('star')) return Icons.star_outline;
  return Icons.workspace_premium_outlined;
}
