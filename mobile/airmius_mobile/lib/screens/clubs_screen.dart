import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';
import 'application_screen.dart';

class ClubsScreen extends StatefulWidget {
  const ClubsScreen({
    super.key,
    required this.requestedClubIds,
    required this.onRequestClub,
    required this.onWithdrawClub,
  });

  final Set<int> requestedClubIds;
  final ValueChanged<ClubSummary> onRequestClub;
  final ValueChanged<ClubSummary> onWithdrawClub;

  @override
  State<ClubsScreen> createState() => _ClubsScreenState();
}

class _ClubsScreenState extends State<ClubsScreen> {
  String _query = '';
  bool _onlyJoinable = false;
  Future<List<ClubSummary>>? _clubsFuture;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _clubsFuture ??= _loadClubs();
  }

  Future<List<ClubSummary>> _loadClubs() async {
    final services = AirmiusServicesScope.of(context);
    final page = await services.repositories.clubs.searchClubs();
    return page.items.map(ClubSummary.fromAirmiusClub).toList();
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);

    return PageFrame(
      title: scope.t('clubs'),
      subtitle: 'Vereine, Teams und Mitgliedschaft',
      showHeader: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const _ClubsHero(),
          const SizedBox(height: 14),
          SearchBox(hint: scope.t('search'), onChanged: (value) => setState(() => _query = value)),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _FilterChip(label: 'Alle Vereine', selected: !_onlyJoinable, onTap: () => setState(() => _onlyJoinable = false)),
              _FilterChip(label: 'Beitritt moeglich', selected: _onlyJoinable, onTap: () => setState(() => _onlyJoinable = true)),
            ],
          ),
          const SizedBox(height: 14),
          FutureBuilder<List<ClubSummary>>(
            future: _clubsFuture,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const AirmiusPanel(child: Center(child: Padding(padding: EdgeInsets.all(18), child: Text('Vereine werden geladen...', style: TextStyle(color: AirmiusColors.muted)))));
              }
              if (snapshot.hasError) {
                return AirmiusPanel(
                  borderColor: AirmiusColors.red.withValues(alpha: .5),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const Text('Vereine konnten nicht geladen werden.', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                      const SizedBox(height: 8),
                      Text('${snapshot.error}', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                      const SizedBox(height: 12),
                      AirmiusButton(label: 'Erneut laden', icon: Icons.refresh_outlined, secondary: true, onPressed: () => setState(() => _clubsFuture = _loadClubs())),
                    ],
                  ),
                );
              }
              final clubs = (snapshot.data ?? demoClubs).where((club) {
                final query = _query.trim().toLowerCase();
                final requested = widget.requestedClubIds.contains(club.id) || club.hasPendingMembershipRequest;
                if (_onlyJoinable && (!club.acceptsMemberships || requested || club.isMember)) return false;
                return query.isEmpty || club.name.toLowerCase().contains(query) || club.city.toLowerCase().contains(query);
              }).toList();
              if (clubs.isEmpty) {
                return const AirmiusPanel(child: Center(child: Padding(padding: EdgeInsets.all(18), child: Text('Keine Vereine gefunden.', style: TextStyle(color: AirmiusColors.muted)))));
              }
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (final club in clubs) ...[
                    _ClubCard(
                      club: club,
                      requested: widget.requestedClubIds.contains(club.id) || club.hasPendingMembershipRequest,
                      onRequest: widget.onRequestClub,
                      onWithdraw: widget.onWithdrawClub,
                    ),
                    const SizedBox(height: 12),
                  ],
                ],
              );
            },
          ),
        ],
      ),
    );
  }
}

class _ClubsHero extends StatelessWidget {
  const _ClubsHero();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('Vereinsbereich'),
          const SizedBox(height: 8),
          const Text('Finde deinen Verein und verwalte alles wie im Web-Cockpit.', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900, height: 1.12)),
          const SizedBox(height: 8),
          const Text('Mitgliedschaft, Teams, Dokumente und sichtbare Vereinsbeitraege in einer mobilen Ansicht.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: const [
              StatusPill('Profil'),
              StatusPill('Teams'),
              StatusPill('Dokumente'),
              StatusPill('Feed'),
            ],
          ),
        ],
      ),
    );
  }
}

class _ClubCard extends StatelessWidget {
  const _ClubCard({required this.club, required this.requested, required this.onRequest, required this.onWithdraw});

  final ClubSummary club;
  final bool requested;
  final ValueChanged<ClubSummary> onRequest;
  final ValueChanged<ClubSummary> onWithdraw;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => ClubProfileScreen(club: club, requested: requested, onRequest: onRequest, onWithdraw: onWithdraw),
        ),
      ),
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _ClubCover(club: club, height: 86, compact: true),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Transform.translate(
                  offset: const Offset(0, -22),
                  child: AirmiusAvatar(club.name, imageUrl: club.logoUrl, large: true),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(child: Text(club.name, style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900))),
                            if (club.verified) const Icon(Icons.verified, color: AirmiusColors.blue, size: 18),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Text('${club.city} - ${club.members} ${scope.t('members')}', style: const TextStyle(color: AirmiusColors.muted)),
                        const SizedBox(height: 10),
                        Wrap(
                          spacing: 7,
                          runSpacing: 7,
                          children: [
                            StatusPill(club.isMember ? 'Mitglied' : (requested ? scope.t('sent') : (club.acceptsMemberships ? 'Nimmt Anfragen an' : 'Oeffentlich')), color: club.isMember ? AirmiusColors.green : requested ? AirmiusColors.green : AirmiusColors.blue),
                            StatusPill('${club.teams} Teams'),
                            StatusPill('${club.posts} Posts'),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                const Padding(
                  padding: EdgeInsets.only(top: 18),
                  child: Icon(Icons.chevron_right, color: AirmiusColors.muted),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class ClubProfileScreen extends StatefulWidget {
  const ClubProfileScreen({super.key, required this.club, required this.requested, required this.onRequest, required this.onWithdraw});

  final ClubSummary club;
  final bool requested;
  final ValueChanged<ClubSummary> onRequest;
  final ValueChanged<ClubSummary> onWithdraw;

  @override
  State<ClubProfileScreen> createState() => _ClubProfileScreenState();
}

class _ClubProfileScreenState extends State<ClubProfileScreen> {
  String _activeTab = 'struktur';
  Future<ClubSummary>? _clubDetailFuture;

  ClubSummary get club => widget.club;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _clubDetailFuture ??= _loadClubDetail();
  }

  Future<ClubSummary> _loadClubDetail() async {
    final services = AirmiusServicesScope.of(context);
    final detail = await services.repositories.clubs.club(widget.club.id);
    return ClubSummary.fromAirmiusClub(detail);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: Text(club.name, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: club.name,
        subtitle: 'Vereinsprofil, Teams, Rollen und sichtbare Beitraege',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            FutureBuilder<ClubSummary>(
              future: _clubDetailFuture,
              builder: (context, snapshot) {
                final profileClub = snapshot.data ?? club;
                final requested = widget.requested || profileClub.hasPendingMembershipRequest;
                return _ClubProfileHero(
                  club: profileClub,
                  requested: requested,
                  onJoin: profileClub.acceptsMemberships && !profileClub.isMember && !requested ? () => _openApplication(context, profileClub) : null,
                  onWithdraw: requested ? () => _withdraw(context, profileClub) : null,
                );
              },
            ),
            const SizedBox(height: 14),
            FutureBuilder<ClubSummary>(
              future: _clubDetailFuture,
              builder: (context, snapshot) => _ClubStats(club: snapshot.data ?? club),
            ),
            const SizedBox(height: 14),
            _ClubWorkspaceTabs(active: _activeTab, onSelect: (value) => setState(() => _activeTab = value)),
            const SizedBox(height: 14),
            FutureBuilder<ClubSummary>(
              future: _clubDetailFuture,
              builder: (context, snapshot) {
                final profileClub = snapshot.data ?? club;
                final requested = widget.requested || profileClub.hasPendingMembershipRequest;
                return _ClubTabBody(
                  tab: _activeTab,
                  club: profileClub,
                  requested: requested,
                  onJoin: profileClub.acceptsMemberships && !profileClub.isMember && !requested ? () => _openApplication(context, profileClub) : null,
                  onWithdraw: requested ? () => _withdraw(context, profileClub) : null,
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openApplication(BuildContext context, ClubSummary selectedClub) async {
    final sent = await Navigator.push<bool>(context, MaterialPageRoute(fullscreenDialog: true, builder: (_) => ApplicationScreen(club: selectedClub)));
    if (sent == true && context.mounted) {
      widget.onRequest(selectedClub);
      Navigator.pop(context);
    }
  }

  Future<void> _withdraw(BuildContext context, ClubSummary selectedClub) async {
    final ok = await confirmDanger(context, 'Anfrage zurueckziehen', 'Moechtest du deine Mitgliedschaftsanfrage bei ${selectedClub.name} wirklich zurueckziehen?');
    if (ok && context.mounted) {
      try {
        final services = AirmiusServicesScope.of(context);
        await services.repositories.memberships.withdrawClubRequest(selectedClub.id);
        widget.onWithdraw(selectedClub);
        Navigator.pop(context);
      } catch (error) {
        if (!context.mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Anfrage konnte nicht zurueckgezogen werden: $error')));
      }
    }
  }
}

class _ClubProfileHero extends StatelessWidget {
  const _ClubProfileHero({required this.club, required this.requested, required this.onJoin, required this.onWithdraw});

  final ClubSummary club;
  final bool requested;
  final VoidCallback? onJoin;
  final VoidCallback? onWithdraw;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Stack(
            clipBehavior: Clip.none,
            children: [
              _ClubCover(club: club, height: 150),
              Positioned(
                left: 18,
                bottom: -34,
                child: AirmiusAvatar(club.name, imageUrl: club.logoUrl, large: true),
              ),
              Positioned(
                right: 12,
                bottom: 12,
                child: Wrap(
                  spacing: 8,
                  children: [
                    _RoundAction(icon: Icons.camera_alt_outlined, onTap: () => openUiAction(context, title: 'Cover aktualisieren', body: 'Cover-Bild wie im Web-Cockpit vorbereiten.', icon: Icons.image_outlined)),
                    _RoundAction(icon: Icons.more_horiz, onTap: () => _openMoreSheet(context)),
                  ],
                ),
              ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 46, 18, 18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(child: Text(club.name, style: const TextStyle(color: AirmiusColors.text, fontSize: 25, fontWeight: FontWeight.w900, height: 1.05))),
                              if (club.verified) const Icon(Icons.verified, color: AirmiusColors.blue),
                            ],
                          ),
                          const SizedBox(height: 5),
                          Text('${club.city} - Vereinsprofil', style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(club.isMember ? 'Mitglied' : (requested ? scope.t('sent') : (club.acceptsMemberships ? 'Mitgliedschaft offen' : 'Nur Ansicht')), color: club.isMember ? AirmiusColors.green : requested ? AirmiusColors.green : AirmiusColors.blue),
                    const StatusPill('Struktur'),
                    const StatusPill('Mobile Cockpit'),
                  ],
                ),
                const SizedBox(height: 16),
                if (club.isMember)
                  AirmiusButton(label: 'Mitglied', icon: Icons.verified_user_outlined, secondary: true, onPressed: null)
                else if (requested)
                  AirmiusButton(label: scope.t('withdraw'), icon: Icons.undo_outlined, danger: true, onPressed: onWithdraw)
                else
                  AirmiusButton(label: club.acceptsMemberships ? scope.t('join') : 'Teams ansehen', icon: Icons.assignment_outlined, onPressed: onJoin),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _openMoreSheet(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: AirmiusColors.card,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (_) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text('Vereinsaktionen', style: TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
              const SizedBox(height: 12),
              AirmiusButton(label: 'Nachricht senden', icon: Icons.chat_bubble_outline, secondary: true, onPressed: () => Navigator.pop(context)),
              const SizedBox(height: 10),
              AirmiusButton(label: 'Verein melden', icon: Icons.flag_outlined, danger: true, onPressed: () => Navigator.pop(context)),
            ],
          ),
        ),
      ),
    );
  }
}

class _ClubCover extends StatelessWidget {
  const _ClubCover({required this.club, required this.height, this.compact = false});

  final ClubSummary club;
  final double height;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final imageUrl = club.bannerUrl;
    return ClipRRect(
      borderRadius: BorderRadius.vertical(top: Radius.circular(compact ? 18 : 18)),
      child: SizedBox(
        height: height,
        child: Stack(
          fit: StackFit.expand,
          children: [
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    AirmiusColors.blue.withValues(alpha: 0.80),
                    AirmiusColors.cardSoft,
                    AirmiusColors.amber.withValues(alpha: 0.42),
                  ],
                ),
              ),
            ),
            if (imageUrl != null && imageUrl.isNotEmpty)
              Image.network(
                imageUrl,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => const SizedBox.shrink(),
              ),
            Container(color: Colors.black.withValues(alpha: compact ? 0.16 : 0.24)),
          ],
        ),
      ),
    );
  }
}

class _ClubStats extends StatelessWidget {
  const _ClubStats({required this.club});

  final ClubSummary club;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Row(
      children: [
        Expanded(child: MetricCard(value: '${club.members}', label: scope.t('members'))),
        const SizedBox(width: 10),
        Expanded(child: MetricCard(value: '${club.teams}', label: scope.t('teams'))),
        const SizedBox(width: 10),
        Expanded(child: MetricCard(value: '${club.posts}', label: scope.t('posts'))),
      ],
    );
  }
}

class _ClubWorkspaceTabs extends StatelessWidget {
  const _ClubWorkspaceTabs({required this.active, required this.onSelect});

  final String active;
  final ValueChanged<String> onSelect;

  static const tabs = [
    _ClubTab('struktur', 'Struktur', Icons.account_tree_outlined),
    _ClubTab('beitritt', 'Beitritt', Icons.assignment_outlined),
    _ClubTab('beitraege', 'Beitraege', Icons.forum_outlined),
    _ClubTab('dokumente', 'Dokumente', Icons.folder_open_outlined),
  ];

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final tab in tabs) ...[
            _ClubTabChip(tab: tab, selected: active == tab.id, onTap: () => onSelect(tab.id)),
            const SizedBox(width: 8),
          ],
        ],
      ),
    );
  }
}

class _ClubTabBody extends StatelessWidget {
  const _ClubTabBody({required this.tab, required this.club, required this.requested, required this.onJoin, required this.onWithdraw});

  final String tab;
  final ClubSummary club;
  final bool requested;
  final VoidCallback? onJoin;
  final VoidCallback? onWithdraw;

  @override
  Widget build(BuildContext context) {
    return switch (tab) {
      'beitritt' => _MembershipPanel(club: club, requested: requested, onJoin: onJoin, onWithdraw: onWithdraw),
      'beitraege' => _ClubPostsPanel(club: club),
      'dokumente' => const _DocumentsPanel(),
      _ => _StructurePanel(club: club),
    };
  }
}

class _StructurePanel extends StatelessWidget {
  const _StructurePanel({required this.club});

  final ClubSummary club;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Eyebrow('Vereinsdaten'),
              const SizedBox(height: 12),
              _DetailLine(icon: Icons.location_on_outlined, label: 'Standort', value: club.city.isEmpty ? 'Noch nicht hinterlegt' : club.city),
              _DetailLine(icon: Icons.badge_outlined, label: 'Status', value: club.verified ? 'Verifiziert' : 'Profil in Pruefung'),
              _DetailLine(icon: Icons.groups_outlined, label: 'Mitglieder', value: '${club.members} aktive Kontakte'),
            ],
          ),
        ),
        const SizedBox(height: 14),
        _InfoPanel(
          title: 'Admins',
          rows: [
            _InfoRowData('VA', club.name, club.verified ? 'Verein-Admin' : 'Profilverantwortlich'),
          ],
        ),
        const SizedBox(height: 14),
        _TeamsPanel(club: club),
      ],
    );
  }
}

class _MembershipPanel extends StatelessWidget {
  const _MembershipPanel({required this.club, required this.requested, required this.onJoin, required this.onWithdraw});

  final ClubSummary club;
  final bool requested;
  final VoidCallback? onJoin;
  final VoidCallback? onWithdraw;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      borderColor: requested ? AirmiusColors.green.withValues(alpha: 0.60) : null,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Mitgliedschaft'),
          const SizedBox(height: 8),
          Text(
            club.isMember ? 'Du bist Mitglied in diesem Verein.' : requested ? 'Deine Mitgliedschaftsanfrage ist beim Verein angekommen.' : 'Starte eine Anfrage mit Nachricht, Dokumenten und Zahlungswunsch wie im Web.',
            style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800, height: 1.35),
          ),
          const SizedBox(height: 12),
          const _MembershipOption(title: 'Standard-Mitgliedschaft', meta: 'Jaehrlich - Dokumente erforderlich'),
          const _MembershipOption(title: 'Foerdermitgliedschaft', meta: 'Optional - Verein prueft manuell'),
          const SizedBox(height: 14),
          if (club.isMember)
            AirmiusButton(label: 'Mitglied', icon: Icons.verified_user_outlined, secondary: true, onPressed: null)
          else if (requested)
            AirmiusButton(label: scope.t('withdraw'), icon: Icons.undo_outlined, danger: true, onPressed: onWithdraw)
          else
            AirmiusButton(label: club.acceptsMemberships ? scope.t('join') : 'Anfragen geschlossen', icon: Icons.assignment_outlined, onPressed: onJoin),
        ],
      ),
    );
  }
}

class _ClubPostsPanel extends StatelessWidget {
  const _ClubPostsPanel({required this.club});

  final ClubSummary club;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Vereinsbeitraege'),
          const SizedBox(height: 8),
          Text('${club.posts} sichtbare Beitraege fuer Mitglieder und Community.', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
          const SizedBox(height: 14),
          _FeedPreviewLine(title: 'Willkommen im Vereinsfeed', meta: 'Ankuendigungen, Bilder und Videos erscheinen hier.'),
          _FeedPreviewLine(title: 'Training & Termine', meta: 'Team-Updates koennen im Feed verknuepft werden.'),
        ],
      ),
    );
  }
}

class _TeamsPanel extends StatelessWidget {
  const _TeamsPanel({required this.club});

  final ClubSummary club;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('Teams'),
          const SizedBox(height: 8),
          if (club.teamList.isEmpty && club.teams == 0)
            const Text('Noch keine Teams sichtbar.', style: TextStyle(color: AirmiusColors.muted))
          else if (club.teamList.isNotEmpty)
            for (final team in club.teamList)
              _TeamLine(title: team.name, meta: team.meta)
          else
            for (var index = 1; index <= club.teams.clamp(1, 3); index++)
              _TeamLine(title: 'Team $index', meta: index == 1 ? 'Hauptteam' : 'Training & Spielbetrieb'),
        ],
      ),
    );
  }
}

class _DocumentsPanel extends StatelessWidget {
  const _DocumentsPanel();

  @override
  Widget build(BuildContext context) {
    return const AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow('Vereinsdokumente'),
          SizedBox(height: 8),
          Text('Datenschutz, Beitragsordnung und Vereinsregeln werden wie im Web-Cockpit fuer die mobile Anmeldung sichtbar gemacht.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
          SizedBox(height: 12),
          _DocumentLine(title: 'Datenschutz', requiredDoc: true),
          _DocumentLine(title: 'Beitragsordnung', requiredDoc: true),
          _DocumentLine(title: 'Vereinsregeln', requiredDoc: false),
        ],
      ),
    );
  }
}

class _DocumentLine extends StatelessWidget {
  const _DocumentLine({required this.title, required this.requiredDoc});

  final String title;
  final bool requiredDoc;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(
        children: [
          const Icon(Icons.description_outlined, color: AirmiusColors.blue),
          const SizedBox(width: 12),
          Expanded(child: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800))),
          StatusPill(requiredDoc ? 'Pflicht' : 'Optional'),
        ],
      ),
    );
  }
}

class _InfoPanel extends StatelessWidget {
  const _InfoPanel({required this.title, required this.rows});

  final String title;
  final List<_InfoRowData> rows;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Eyebrow(title),
          if (rows.isEmpty) const Padding(padding: EdgeInsets.only(top: 14), child: Text('Noch keine Eintraege.', style: TextStyle(color: AirmiusColors.muted))),
          for (final row in rows) ...[
            const SizedBox(height: 14),
            Row(
              children: [
                UserBubble(label: row.initials, small: true),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(row.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                      Text(row.meta, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12)),
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

class _InfoRowData {
  const _InfoRowData(this.initials, this.title, this.meta);

  final String initials;
  final String title;
  final String meta;
}

class _MembershipOption extends StatelessWidget {
  const _MembershipOption({required this.title, required this.meta});

  final String title;
  final String meta;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(
        children: [
          const Icon(Icons.fact_check_outlined, color: AirmiusColors.green),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                Text(meta, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _FeedPreviewLine extends StatelessWidget {
  const _FeedPreviewLine({required this.title, required this.meta});

  final String title;
  final String meta;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(
        children: [
          const Icon(Icons.dynamic_feed_outlined, color: AirmiusColors.amber),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                Text(meta, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _TeamLine extends StatelessWidget {
  const _TeamLine({required this.title, required this.meta});

  final String title;
  final String meta;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Row(
        children: [
          const UserBubble(label: 'T', small: true),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                Text(meta, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12)),
              ],
            ),
          ),
          const Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ],
      ),
    );
  }
}

class _DetailLine extends StatelessWidget {
  const _DetailLine({required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(
        children: [
          Icon(icon, color: AirmiusColors.blue, size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w800)),
                Text(value, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _RoundAction extends StatelessWidget {
  const _RoundAction({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        width: 42,
        height: 42,
        decoration: BoxDecoration(
          color: AirmiusColors.card.withValues(alpha: 0.88),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AirmiusColors.border),
        ),
        child: Icon(icon, color: AirmiusColors.text, size: 20),
      ),
    );
  }
}

class _ClubTab {
  const _ClubTab(this.id, this.label, this.icon);

  final String id;
  final String label;
  final IconData icon;
}

class _ClubTabChip extends StatelessWidget {
  const _ClubTabChip({required this.tab, required this.selected, required this.onTap});

  final _ClubTab tab;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      selected: selected,
      label: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(tab.icon, size: 16, color: selected ? AirmiusColors.text : AirmiusColors.muted),
          const SizedBox(width: 6),
          Text(tab.label),
        ],
      ),
      onSelected: (_) => onTap(),
      selectedColor: AirmiusColors.blue.withValues(alpha: 0.24),
      backgroundColor: AirmiusColors.cardSoft,
      side: BorderSide(color: selected ? AirmiusColors.blue : AirmiusColors.border),
      labelStyle: TextStyle(color: selected ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      selected: selected,
      label: Text(label),
      onSelected: (_) => onTap(),
      selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
      backgroundColor: AirmiusColors.cardSoft,
      side: BorderSide(color: selected ? AirmiusColors.blue : AirmiusColors.border),
      labelStyle: TextStyle(color: selected ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
    );
  }
}
