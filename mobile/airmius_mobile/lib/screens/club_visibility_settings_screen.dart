import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'club_policy_documents_screen.dart';
import 'club_request_inbox_screen.dart';
import 'search_operations_screen.dart';

class ClubVisibilitySettingsScreen extends StatefulWidget {
  const ClubVisibilitySettingsScreen({super.key, this.initialTab = 'Public'});

  final String initialTab;

  @override
  State<ClubVisibilitySettingsScreen> createState() => _ClubVisibilitySettingsScreenState();
}

class _ClubVisibilitySettingsScreenState extends State<ClubVisibilitySettingsScreen> {
  late String _tab = widget.initialTab;
  bool _publicProfile = true;
  bool _showAddress = true;
  bool _showContact = false;
  bool _showAdmins = true;
  bool _showMembers = false;
  bool _showTeams = true;
  bool _showPosts = true;
  bool _showDocuments = true;
  bool _showFees = true;
  bool _joinEnabled = true;
  bool _sponsorVisible = true;
  bool _galleryVisible = false;

  @override
  Widget build(BuildContext context) {
    final items = _items.where((item) => item.area == _tab).toList();
    final activeCount = [_publicProfile, _showAddress, _showContact, _showAdmins, _showMembers, _showTeams, _showPosts, _showDocuments, _showFees, _joinEnabled, _sponsorVisible, _galleryVisible].where((item) => item).length;

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Vereinssichtbarkeit', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Vereinssichtbarkeit',
        subtitle: 'Public-Profil, Kontakt, Teams, Mitglieder, Beiträge, Dokumente und Beitrittsbutton steuern',
        trailing: StatusPill('$activeCount aktiv', color: AirmiusColors.green),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const AirmiusLogo(),
                  const SizedBox(height: 14),
                  const Text('Vereine bestimmen selbst, was Nutzer sehen.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  const SizedBox(height: 8),
                  const Text('Diese UI übersetzt die Web-App-Sichtbarkeit in mobile Schalter: Profil öffentlich, Adresse, Kontakt, Admins, Mitglieder, Teams, Beiträge, Regeln, Dokumente und Beitrittsanfrage.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
                  const SizedBox(height: 14),
                  Row(children: [Expanded(child: MetricCard(value: '$activeCount', label: 'Sichtbar')), const SizedBox(width: 10), const Expanded(child: MetricCard(value: 'Club', label: 'Owner')), const SizedBox(width: 10), const Expanded(child: MetricCard(value: 'Public', label: 'Preview'))]),
                  const SizedBox(height: 14),
                  Wrap(spacing: 8, runSpacing: 8, children: [
                    for (final tab in _tabs)
                      ChoiceChip(
                        label: Text(tab),
                        selected: _tab == tab,
                        onSelected: (_) => setState(() => _tab = tab),
                        selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.cardSoft,
                        side: BorderSide(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      ),
                  ]),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _VisibilitySwitchPanel(
              publicProfile: _publicProfile,
              showAddress: _showAddress,
              showContact: _showContact,
              showAdmins: _showAdmins,
              showMembers: _showMembers,
              showTeams: _showTeams,
              showPosts: _showPosts,
              showDocuments: _showDocuments,
              showFees: _showFees,
              joinEnabled: _joinEnabled,
              sponsorVisible: _sponsorVisible,
              galleryVisible: _galleryVisible,
              onPublicProfile: (value) => setState(() => _publicProfile = value),
              onAddress: (value) => setState(() => _showAddress = value),
              onContact: (value) => setState(() => _showContact = value),
              onAdmins: (value) => setState(() => _showAdmins = value),
              onMembers: (value) => setState(() => _showMembers = value),
              onTeams: (value) => setState(() => _showTeams = value),
              onPosts: (value) => setState(() => _showPosts = value),
              onDocuments: (value) => setState(() => _showDocuments = value),
              onFees: (value) => setState(() => _showFees = value),
              onJoin: (value) => setState(() => _joinEnabled = value),
              onSponsor: (value) => setState(() => _sponsorVisible = value),
              onGallery: (value) => setState(() => _galleryVisible = value),
            ),
            const SizedBox(height: 16),
            _PublicPreviewCard(
              publicProfile: _publicProfile,
              showAddress: _showAddress,
              showContact: _showContact,
              showAdmins: _showAdmins,
              showMembers: _showMembers,
              showTeams: _showTeams,
              showPosts: _showPosts,
              showDocuments: _showDocuments,
              showFees: _showFees,
              joinEnabled: _joinEnabled,
            ),
            const SizedBox(height: 16),
            for (final item in items) ...[
              _VisibilityItemCard(item: item),
              const SizedBox(height: 12),
            ],
            _VisibilityWorkflowPanel(tab: _tab),
          ],
        ),
      ),
    );
  }
}

class _VisibilitySwitchPanel extends StatelessWidget {
  const _VisibilitySwitchPanel({required this.publicProfile, required this.showAddress, required this.showContact, required this.showAdmins, required this.showMembers, required this.showTeams, required this.showPosts, required this.showDocuments, required this.showFees, required this.joinEnabled, required this.sponsorVisible, required this.galleryVisible, required this.onPublicProfile, required this.onAddress, required this.onContact, required this.onAdmins, required this.onMembers, required this.onTeams, required this.onPosts, required this.onDocuments, required this.onFees, required this.onJoin, required this.onSponsor, required this.onGallery});

  final bool publicProfile;
  final bool showAddress;
  final bool showContact;
  final bool showAdmins;
  final bool showMembers;
  final bool showTeams;
  final bool showPosts;
  final bool showDocuments;
  final bool showFees;
  final bool joinEnabled;
  final bool sponsorVisible;
  final bool galleryVisible;
  final ValueChanged<bool> onPublicProfile;
  final ValueChanged<bool> onAddress;
  final ValueChanged<bool> onContact;
  final ValueChanged<bool> onAdmins;
  final ValueChanged<bool> onMembers;
  final ValueChanged<bool> onTeams;
  final ValueChanged<bool> onPosts;
  final ValueChanged<bool> onDocuments;
  final ValueChanged<bool> onFees;
  final ValueChanged<bool> onJoin;
  final ValueChanged<bool> onSponsor;
  final ValueChanged<bool> onGallery;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: AirmiusColors.blue.withValues(alpha: .44),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const Eyebrow('Sichtbarkeitsschalter'),
          const SizedBox(height: 8),
          const Text('Diese Schalter bilden ab, was der Verein später serverseitig speichern kann. Flutter zeigt nur, was laut Verein, Rolle und Datenschutz erlaubt ist.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
          const SizedBox(height: 10),
          _VisibilitySwitch(icon: Icons.public_outlined, title: 'Public-Profil aktiv', body: 'Vereinsprofil ist für Gastseite und globale Suche sichtbar.', value: publicProfile, onChanged: onPublicProfile, color: AirmiusColors.green),
          _VisibilitySwitch(icon: Icons.location_on_outlined, title: 'Adresse anzeigen', body: 'Ort, PLZ, Straße oder nur Region im Vereinsprofil anzeigen.', value: showAddress, onChanged: onAddress, color: AirmiusColors.blue),
          _VisibilitySwitch(icon: Icons.contact_mail_outlined, title: 'Kontakt anzeigen', body: 'E-Mail, Telefon oder Kontaktformular öffentlich oder nur für Mitglieder.', value: showContact, onChanged: onContact, color: AirmiusColors.amber),
          _VisibilitySwitch(icon: Icons.admin_panel_settings_outlined, title: 'Admins anzeigen', body: 'Adminliste im Profil sichtbar oder nur intern im Vereinsbereich.', value: showAdmins, onChanged: onAdmins, color: AirmiusColors.green),
          _VisibilitySwitch(icon: Icons.people_outline, title: 'Mitglieder anzeigen', body: 'Mitgliederliste komplett, anonymisiert, nur Anzahl oder versteckt.', value: showMembers, onChanged: onMembers, color: AirmiusColors.blue),
          _VisibilitySwitch(icon: Icons.groups_2_outlined, title: 'Teams anzeigen', body: 'Teams im Profil sichtbar machen und Teamseiten verlinken.', value: showTeams, onChanged: onTeams, color: AirmiusColors.green),
          _VisibilitySwitch(icon: Icons.dynamic_feed_outlined, title: 'Beiträge anzeigen', body: 'Sichtbare Vereinsbeiträge, Public Feed oder nur interne Posts.', value: showPosts, onChanged: onPosts, color: AirmiusColors.blue),
          _VisibilitySwitch(icon: Icons.rule_folder_outlined, title: 'Regeln & Dokumente anzeigen', body: 'Datenschutz, Beitragsordnung, Satzung oder Medienregeln im Profil/Antrag sichtbar.', value: showDocuments, onChanged: onDocuments, color: AirmiusColors.amber),
          _VisibilitySwitch(icon: Icons.receipt_long_outlined, title: 'Beiträge anzeigen', body: 'Beitragshoehen und Zahlungsrhythmus transparent vor Antrag anzeigen.', value: showFees, onChanged: onFees, color: AirmiusColors.green),
          _VisibilitySwitch(icon: Icons.assignment_ind_outlined, title: 'Beitrittsanfrage erlauben', body: 'Button für Mitgliedschaftsanfrage aktivieren oder nur Teams ansehen.', value: joinEnabled, onChanged: onJoin, color: AirmiusColors.blue),
          _VisibilitySwitch(icon: Icons.handshake_outlined, title: 'Sponsoren anzeigen', body: 'Sponsorlogos und Kampagnen im Vereinsprofil sichtbar machen.', value: sponsorVisible, onChanged: onSponsor, color: AirmiusColors.amber),
          _VisibilitySwitch(icon: Icons.photo_library_outlined, title: 'Galerie anzeigen', body: 'Medienbereich nur mit Foto-/Guardian-Freigabe anzeigen.', value: galleryVisible, onChanged: onGallery, color: AirmiusColors.green),
        ]),
      );
}

class _PublicPreviewCard extends StatelessWidget {
  const _PublicPreviewCard({required this.publicProfile, required this.showAddress, required this.showContact, required this.showAdmins, required this.showMembers, required this.showTeams, required this.showPosts, required this.showDocuments, required this.showFees, required this.joinEnabled});

  final bool publicProfile;
  final bool showAddress;
  final bool showContact;
  final bool showAdmins;
  final bool showMembers;
  final bool showTeams;
  final bool showPosts;
  final bool showDocuments;
  final bool showFees;
  final bool joinEnabled;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: AirmiusColors.green.withValues(alpha: .44),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const Eyebrow('Mobile Preview'),
          const SizedBox(height: 12),
          Container(
            constraints: const BoxConstraints(minHeight: 150),
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(gradient: const LinearGradient(colors: [Color(0xFFEFF5FF), Color(0xFF5BA7FF)], begin: Alignment.topLeft, end: Alignment.bottomRight), borderRadius: BorderRadius.circular(18), border: Border.all(color: AirmiusColors.border)),
            child: Column(mainAxisAlignment: MainAxisAlignment.end, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Row(children: const [AirmiusAvatar('ZBB', large: true), SizedBox(width: 14), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('ZBB', style: TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w900, shadows: [Shadow(color: Colors.black54, blurRadius: 5)])), Text('Verein - Profil', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, shadows: [Shadow(color: Colors.black54, blurRadius: 5)]))]))]),
              const SizedBox(height: 14),
              Align(alignment: Alignment.centerRight, child: OutlinedButton(onPressed: null, style: OutlinedButton.styleFrom(side: BorderSide(color: joinEnabled ? AirmiusColors.blue : AirmiusColors.muted), foregroundColor: joinEnabled ? AirmiusColors.blue : AirmiusColors.muted, backgroundColor: AirmiusColors.bg.withValues(alpha: .86), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))), child: Text(joinEnabled ? 'Mitgliedschaft anfragen' : 'Teams ansehen', style: const TextStyle(fontWeight: FontWeight.w900)))),
            ]),
          ),
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, children: [
            StatusPill(publicProfile ? 'Public' : 'Privat', color: publicProfile ? AirmiusColors.green : AirmiusColors.red),
            if (showAddress) const StatusPill('Adresse'),
            if (showContact) const StatusPill('Kontakt'),
            if (showAdmins) const StatusPill('Admins'),
            if (showMembers) const StatusPill('Mitglieder'),
            if (showTeams) const StatusPill('Teams'),
            if (showPosts) const StatusPill('Beiträge'),
            if (showDocuments) const StatusPill('Dokumente'),
            if (showFees) const StatusPill('Beitrag'),
          ]),
        ]),
      );
}

class _VisibilityItemCard extends StatelessWidget {
  const _VisibilityItemCard({required this.item});

  final _VisibilityItem item;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: item.color.withValues(alpha: .44),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Container(width: 50, height: 50, decoration: BoxDecoration(color: item.color.withValues(alpha: .13), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withValues(alpha: .45))), child: Icon(item.icon, color: item.color)),
            const SizedBox(width: 14),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
              const SizedBox(height: 5),
              Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              const SizedBox(height: 10),
              Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(item.area, color: item.color), StatusPill(item.mode), StatusPill(item.audience, color: item.color)]),
            ])),
          ]),
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, children: [
            AirmiusButton(label: 'Konfigurieren', icon: Icons.tune_outlined, onPressed: () => openUiAction(context, title: '${item.title} konfigurieren', body: 'Sichtbarkeit, Zielgruppe, Rollenrechte, Public-Preview und Datenschutz für diesen Vereinsbereich speichern.', status: 'Sichtbarkeit', icon: Icons.tune_outlined)),
            AirmiusButton(label: 'Preview', icon: Icons.visibility_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Preview', body: 'Mobile Vorschau für ${item.audience}: ${item.body}', status: 'Preview', icon: Icons.visibility_outlined)),
          ]),
        ]),
      );
}

class _VisibilityWorkflowPanel extends StatelessWidget {
  const _VisibilityWorkflowPanel({required this.tab});

  final String tab;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: AirmiusColors.blue.withValues(alpha: .44),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const Eyebrow('Sichtbarkeits-Workflow'),
          const SizedBox(height: 8),
          Text('Aktueller Bereich: $tab. Später speichert Laravel diese Schalter pro Verein und liefert sie als capability flags für Public-Profil, Suche, Mitgliedsantrag und Vereinsbereich.', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, children: [
            AirmiusButton(label: 'Speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Sichtbarkeit speichern', body: 'Public-Profil, Kontakt, Mitglieder, Teams, Dokumente, Beiträge, Sponsoren und Antragsschalter für den Verein speichern.', status: 'Visibility', icon: Icons.save_outlined)),
            AirmiusButton(label: 'Suche prüfen', icon: Icons.manage_search_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SearchOperationsScreen()))),
            AirmiusButton(label: 'Anfragen', icon: Icons.inbox_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubRequestInboxScreen()))),
            AirmiusButton(label: 'Regeln', icon: Icons.rule_folder_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubPolicyDocumentsScreen()))),
          ]),
        ]),
      );
}

class _VisibilitySwitch extends StatelessWidget {
  const _VisibilitySwitch({required this.icon, required this.title, required this.body, required this.value, required this.onChanged, required this.color});

  final IconData icon;
  final String title;
  final String body;
  final bool value;
  final ValueChanged<bool> onChanged;
  final Color color;

  @override
  Widget build(BuildContext context) => SwitchListTile(
        value: value,
        onChanged: onChanged,
        activeThumbColor: color,
        contentPadding: EdgeInsets.zero,
        secondary: Icon(icon, color: color),
        title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
        subtitle: Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)),
      );
}

class _VisibilityItem {
  const _VisibilityItem({required this.area, required this.title, required this.body, required this.mode, required this.audience, required this.icon, required this.color});

  final String area;
  final String title;
  final String body;
  final String mode;
  final String audience;
  final IconData icon;
  final Color color;
}

const _tabs = ['Public', 'Profil', 'Mitglieder', 'Content', 'Kontakt', 'Antrag'];

const _items = <_VisibilityItem>[
  _VisibilityItem(area: 'Public', title: 'Globale Suche', body: 'Verein erscheint in Suchvorschlägen, Clublisten und Public Discovery.', mode: 'Ein/Aus', audience: 'Gäste', icon: Icons.manage_search_outlined, color: AirmiusColors.blue),
  _VisibilityItem(area: 'Public', title: 'Gastseiten-Profil', body: 'Hero, Vereinsname, Ort, Mitgliedszahl, Teams und Anfragebutton für externe Nutzer.', mode: 'Public', audience: 'Gäste', icon: Icons.public_outlined, color: AirmiusColors.green),
  _VisibilityItem(area: 'Profil', title: 'Vereinsdaten', body: 'Name, Beschreibung, Logo, Banner, Sportarten, Standort und Kontakt sichtbar steuern.', mode: 'Profil', audience: 'Alle', icon: Icons.badge_outlined, color: AirmiusColors.blue),
  _VisibilityItem(area: 'Profil', title: 'Admins', body: 'Adminliste sichtbar, anonymisiert oder nur intern im Vereinsbereich anzeigen.', mode: 'Rollen', audience: 'Mitglieder', icon: Icons.admin_panel_settings_outlined, color: AirmiusColors.green),
  _VisibilityItem(area: 'Mitglieder', title: 'Mitgliederliste', body: 'Komplette Liste, nur Anzahl, nur Teams oder versteckt abbilden.', mode: 'Datenschutz', audience: 'Mitglieder', icon: Icons.people_outline, color: AirmiusColors.amber),
  _VisibilityItem(area: 'Mitglieder', title: 'Teams & Kader', body: 'Teams, Kader, Trainer, Captain und Join Requests sichtbar steuern.', mode: 'Team', audience: 'Team', icon: Icons.groups_2_outlined, color: AirmiusColors.green),
  _VisibilityItem(area: 'Content', title: 'Sichtbare Beiträge', body: 'Public Posts, Vereinsbeiträge, Medienfreigaben und Moderationsstatus.', mode: 'Feed', audience: 'Public/Mitglieder', icon: Icons.dynamic_feed_outlined, color: AirmiusColors.blue),
  _VisibilityItem(area: 'Content', title: 'Galerie & Sponsoren', body: 'Bilder, Sponsorlogos, Kampagnen und Medienrechte sichtbar machen.', mode: 'Media', audience: 'Public', icon: Icons.photo_library_outlined, color: AirmiusColors.amber),
  _VisibilityItem(area: 'Kontakt', title: 'Kontaktwege', body: 'E-Mail, Telefon, Kontaktformular, Adminchat oder nur Anfrageformular.', mode: 'Kontakt', audience: 'Gäste', icon: Icons.contact_mail_outlined, color: AirmiusColors.green),
  _VisibilityItem(area: 'Kontakt', title: 'Adresse & Region', body: 'Volle Adresse, nur Stadt, nur Region oder versteckt.', mode: 'Adresse', audience: 'Public', icon: Icons.location_on_outlined, color: AirmiusColors.blue),
  _VisibilityItem(area: 'Antrag', title: 'Mitgliedschaftsanfrage', body: 'Button sichtbar, gesperrt, nur bestimmte Typen oder nur nach Login.', mode: 'Join', audience: 'Nutzer', icon: Icons.assignment_ind_outlined, color: AirmiusColors.green),
  _VisibilityItem(area: 'Antrag', title: 'Beiträge & Regeln vor Antrag', body: 'Beitragshoehen, Zahlrhythmus, Dokumentpflicht und Consent vor Absenden anzeigen.', mode: 'Consent', audience: 'Nutzer', icon: Icons.rule_folder_outlined, color: AirmiusColors.amber),
];
