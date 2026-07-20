import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'club_policy_documents_screen.dart';
import 'club_visibility_settings_screen.dart';
import 'file_operations_screen.dart';
import 'sports_operations_screen.dart';
import 'trust_operations_screen.dart';

class ClubProfileEditorScreen extends StatefulWidget {
  const ClubProfileEditorScreen({super.key, this.initialTab = 'Profil'});

  final String initialTab;

  @override
  State<ClubProfileEditorScreen> createState() => _ClubProfileEditorScreenState();
}

class _ClubProfileEditorScreenState extends State<ClubProfileEditorScreen> {
  late String _tab = widget.initialTab;
  bool _verified = false;
  bool _profilePublic = true;
  bool _contactForm = true;
  bool _showAddress = true;
  bool _acceptsRequests = true;
  bool _logoUploaded = true;
  bool _bannerUploaded = false;

  @override
  Widget build(BuildContext context) {
    final sections = _sections.where((section) => section.area == _tab).toList();
    final completion = [_verified, _profilePublic, _contactForm, _showAddress, _acceptsRequests, _logoUploaded, _bannerUploaded].where((item) => item).length;
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Vereinsprofil bearbeiten', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Vereinsprofil bearbeiten',
        subtitle: 'Stammdaten, Logo, Banner, Kontakt, Adresse, Sportarten, Social Links und Public Preview',
        trailing: StatusPill('$completion/7', color: AirmiusColors.green),
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
                  const Text('Das Vereinsprofil soll mobil genauso vertraut wirken wie in der Web-App.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  const SizedBox(height: 8),
                  const Text('Admins pflegen Stammdaten, Medien, Kontakt, Adresse, Sportarten, Sichtbarkeit und Verifizierung in klaren mobilen Sections. Laravel speichert später jede Section als Vereinsprofil-Update.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
                  const SizedBox(height: 14),
                  Row(children: [Expanded(child: MetricCard(value: '$completion/7', label: 'Status')), const SizedBox(width: 10), const Expanded(child: MetricCard(value: 'ZBB', label: 'Verein')), const SizedBox(width: 10), Expanded(child: MetricCard(value: _profilePublic ? 'Public' : 'Privat', label: 'Sichtbar'))]),
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
            _ClubProfilePreview(acceptsRequests: _acceptsRequests, verified: _verified, showAddress: _showAddress),
            const SizedBox(height: 16),
            _ClubProfileSwitches(
              verified: _verified,
              profilePublic: _profilePublic,
              contactForm: _contactForm,
              showAddress: _showAddress,
              acceptsRequests: _acceptsRequests,
              logoUploaded: _logoUploaded,
              bannerUploaded: _bannerUploaded,
              onVerified: (value) => setState(() => _verified = value),
              onPublic: (value) => setState(() => _profilePublic = value),
              onContact: (value) => setState(() => _contactForm = value),
              onAddress: (value) => setState(() => _showAddress = value),
              onRequests: (value) => setState(() => _acceptsRequests = value),
              onLogo: (value) => setState(() => _logoUploaded = value),
              onBanner: (value) => setState(() => _bannerUploaded = value),
            ),
            const SizedBox(height: 16),
            for (final section in sections) ...[
              _ClubProfileSectionCard(section: section),
              const SizedBox(height: 12),
            ],
            _ClubProfileWorkflow(tab: _tab),
          ],
        ),
      ),
    );
  }
}

class _ClubProfilePreview extends StatelessWidget {
  const _ClubProfilePreview({required this.acceptsRequests, required this.verified, required this.showAddress});

  final bool acceptsRequests;
  final bool verified;
  final bool showAddress;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: AirmiusColors.green.withValues(alpha: .44),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const Eyebrow('Public Preview'),
          const SizedBox(height: 12),
          Container(
            constraints: const BoxConstraints(minHeight: 168),
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(gradient: const LinearGradient(colors: [Color(0xFFEFF5FF), Color(0xFF5BA7FF)], begin: Alignment.topLeft, end: Alignment.bottomRight), borderRadius: BorderRadius.circular(18), border: Border.all(color: AirmiusColors.border)),
            child: Column(mainAxisAlignment: MainAxisAlignment.end, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Row(children: [const AirmiusAvatar('ZBB', large: true), const SizedBox(width: 14), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Row(children: [const Text('ZBB', style: TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w900, shadows: [Shadow(color: Colors.black54, blurRadius: 5)])), if (verified) const Padding(padding: EdgeInsets.only(left: 8), child: Icon(Icons.verified, color: Colors.white, size: 18))]), Text(showAddress ? 'Kleinblittersdorf - Verein' : 'Verein - Profil', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, shadows: [Shadow(color: Colors.black54, blurRadius: 5)]))]))]),
              const SizedBox(height: 14),
              Align(alignment: Alignment.centerRight, child: OutlinedButton(onPressed: null, style: OutlinedButton.styleFrom(side: BorderSide(color: acceptsRequests ? AirmiusColors.blue : AirmiusColors.muted), foregroundColor: acceptsRequests ? AirmiusColors.blue : AirmiusColors.muted, backgroundColor: AirmiusColors.bg.withValues(alpha: .86), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))), child: Text(acceptsRequests ? 'Mitgliedschaft anfragen' : 'Teams ansehen', style: const TextStyle(fontWeight: FontWeight.w900)))),
            ]),
          ),
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(verified ? 'Verifiziert' : 'Nicht verifiziert', color: verified ? AirmiusColors.green : AirmiusColors.amber), StatusPill(showAddress ? 'Adresse sichtbar' : 'Adresse versteckt'), StatusPill(acceptsRequests ? 'Nimmt Anfragen an' : 'Nur Teams')]),
        ]),
      );
}

class _ClubProfileSwitches extends StatelessWidget {
  const _ClubProfileSwitches({required this.verified, required this.profilePublic, required this.contactForm, required this.showAddress, required this.acceptsRequests, required this.logoUploaded, required this.bannerUploaded, required this.onVerified, required this.onPublic, required this.onContact, required this.onAddress, required this.onRequests, required this.onLogo, required this.onBanner});

  final bool verified;
  final bool profilePublic;
  final bool contactForm;
  final bool showAddress;
  final bool acceptsRequests;
  final bool logoUploaded;
  final bool bannerUploaded;
  final ValueChanged<bool> onVerified;
  final ValueChanged<bool> onPublic;
  final ValueChanged<bool> onContact;
  final ValueChanged<bool> onAddress;
  final ValueChanged<bool> onRequests;
  final ValueChanged<bool> onLogo;
  final ValueChanged<bool> onBanner;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: AirmiusColors.blue.withValues(alpha: .44),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const Eyebrow('Profil-Schalter'),
          const SizedBox(height: 8),
          const Text('Diese mobilen Schalter bilden die wichtigsten Web-App-Profiloptionen ab. Später kommen echte Uploads, Validierung und Rollenrechte dazu.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
          const SizedBox(height: 10),
          _ProfileSwitch(icon: Icons.verified_outlined, title: 'Verifizierungsstatus anzeigen', body: 'Verifizierte Vereine werden in Suche, Profil und Public-Bereichen hervorgehoben.', value: verified, onChanged: onVerified, color: AirmiusColors.green),
          _ProfileSwitch(icon: Icons.public_outlined, title: 'Profil öffentlich', body: 'Clubseite erscheint auf Gastseite, Suche und Public Discovery.', value: profilePublic, onChanged: onPublic, color: AirmiusColors.blue),
          _ProfileSwitch(icon: Icons.contact_mail_outlined, title: 'Kontaktformular aktiv', body: 'Nutzer können Kontakt aufnehmen, ohne E-Mail direkt zu sehen.', value: contactForm, onChanged: onContact, color: AirmiusColors.green),
          _ProfileSwitch(icon: Icons.location_on_outlined, title: 'Adresse anzeigen', body: 'Vollstaendige Adresse oder nur Ort/Region im Profil zeigen.', value: showAddress, onChanged: onAddress, color: AirmiusColors.amber),
          _ProfileSwitch(icon: Icons.assignment_ind_outlined, title: 'Mitgliedschaftsanfragen aktiv', body: 'Button für Beitrittsanfrage anzeigen und mit Formularschema verbinden.', value: acceptsRequests, onChanged: onRequests, color: AirmiusColors.blue),
          _ProfileSwitch(icon: Icons.image_outlined, title: 'Logo hochgeladen', body: 'Vereinslogo in Profil, Suche, Teamkarten und Rechnungen nutzen.', value: logoUploaded, onChanged: onLogo, color: AirmiusColors.green),
          _ProfileSwitch(icon: Icons.panorama_outlined, title: 'Banner hochgeladen', body: 'Hero-Bild im Web-App-Stil für Clubprofil und Public Preview.', value: bannerUploaded, onChanged: onBanner, color: AirmiusColors.amber),
        ]),
      );
}

class _ClubProfileSectionCard extends StatelessWidget {
  const _ClubProfileSectionCard({required this.section});

  final _ProfileSection section;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: section.color.withValues(alpha: .44),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Container(width: 50, height: 50, decoration: BoxDecoration(color: section.color.withValues(alpha: .13), borderRadius: BorderRadius.circular(16), border: Border.all(color: section.color.withValues(alpha: .45))), child: Icon(section.icon, color: section.color)),
            const SizedBox(width: 14),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(section.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
              const SizedBox(height: 5),
              Text(section.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              const SizedBox(height: 10),
              Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(section.area, color: section.color), StatusPill(section.status), StatusPill(section.owner, color: section.color)]),
            ])),
          ]),
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, children: [
            AirmiusButton(label: 'Bearbeiten', icon: Icons.edit_outlined, onPressed: () => openUiAction(context, title: '${section.title} bearbeiten', body: 'Mobile Formularsection für ${section.title}: speichern, validieren, Rollenrechte prüfen und Public Preview aktualisieren.', status: 'Profil', icon: Icons.edit_outlined)),
            if (section.area == 'Medien') AirmiusButton(label: 'Dateien', icon: Icons.folder_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FileOperationsScreen()))),
            if (section.area == 'Sport') AirmiusButton(label: 'Sportarten', icon: Icons.sports_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SportsOperationsScreen()))),
          ]),
        ]),
      );
}

class _ClubProfileWorkflow extends StatelessWidget {
  const _ClubProfileWorkflow({required this.tab});

  final String tab;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: AirmiusColors.green.withValues(alpha: .44),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const Eyebrow('Profil-Workflow'),
          const SizedBox(height: 8),
          Text('Aktuelle Section: $tab. Später speichert Laravel Stammdaten, Medien, Kontakt, Adresse, Sportarten, Social Links, Sichtbarkeit und Verifizierungsstatus pro Verein.', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, children: [
            AirmiusButton(label: 'Profil speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Vereinsprofil speichern', body: 'Alle sichtbaren Profilsections speichern, Public Preview aktualisieren und Admin-Audit vormerken.', status: 'Club Profile', icon: Icons.save_outlined)),
            AirmiusButton(label: 'Sichtbarkeit', icon: Icons.visibility_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubVisibilitySettingsScreen()))),
            AirmiusButton(label: 'Dokumente', icon: Icons.rule_folder_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubPolicyDocumentsScreen()))),
            AirmiusButton(label: 'Verifizierung', icon: Icons.verified_user_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TrustOperationsScreen(initialTab: 'Verifizierung')))),
          ]),
        ]),
      );
}

class _ProfileSwitch extends StatelessWidget {
  const _ProfileSwitch({required this.icon, required this.title, required this.body, required this.value, required this.onChanged, required this.color});

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

class _ProfileSection {
  const _ProfileSection({required this.area, required this.title, required this.body, required this.status, required this.owner, required this.icon, required this.color});

  final String area;
  final String title;
  final String body;
  final String status;
  final String owner;
  final IconData icon;
  final Color color;
}

const _tabs = ['Profil', 'Medien', 'Kontakt', 'Adresse', 'Sport', 'Social', 'Trust'];

const _sections = <_ProfileSection>[
  _ProfileSection(area: 'Profil', title: 'Stammdaten', body: 'Vereinsname, Kurzname, Beschreibung, Gruendungsjahr, Vereinsnummer und interne Notiz.', status: 'Pflicht', owner: 'Admin', icon: Icons.badge_outlined, color: AirmiusColors.blue),
  _ProfileSection(area: 'Profil', title: 'Vereinsbeschreibung', body: 'Public-Beschreibung, Zielgruppe, Trainingsangebot, Aufnahmehinweise und Vereinswerte.', status: 'Public', owner: 'Club', icon: Icons.description_outlined, color: AirmiusColors.green),
  _ProfileSection(area: 'Medien', title: 'Logo', body: 'Logo hochladen, zuschneiden und für Suche, Profil, Teams, Rechnungen und Dokumente nutzen.', status: 'Upload', owner: 'Files', icon: Icons.image_outlined, color: AirmiusColors.green),
  _ProfileSection(area: 'Medien', title: 'Banner / Hero', body: 'Headerbild für Clubprofil mit Web-App-Gradient-Fallback und mobiler Preview.', status: 'Optional', owner: 'Files', icon: Icons.panorama_outlined, color: AirmiusColors.amber),
  _ProfileSection(area: 'Kontakt', title: 'Kontaktformular', body: 'Kontaktart, E-Mail, Telefon, Ansprechpartner und Sichtbarkeit pro Rolle steuern.', status: 'Sichtbar', owner: 'Privacy', icon: Icons.contact_mail_outlined, color: AirmiusColors.blue),
  _ProfileSection(area: 'Kontakt', title: 'Admin-Kontakte', body: 'Vereinsadmins, Rollen, öffentliche Anzeige, interne Notizen und Chat-Einstieg.', status: 'Rollen', owner: 'Access', icon: Icons.admin_panel_settings_outlined, color: AirmiusColors.green),
  _ProfileSection(area: 'Adresse', title: 'Vereinsadresse', body: 'Land, Straße, Hausnummer, PLZ, Stadt, Region und Sichtbarkeit.', status: 'Ort', owner: 'Club', icon: Icons.location_on_outlined, color: AirmiusColors.amber),
  _ProfileSection(area: 'Adresse', title: 'Trainingsorte', body: 'Sportorte, Hallen, Plaetze, Treffpunkte und Karte für Public oder Mitglieder.', status: 'Map', owner: 'Sportkarte', icon: Icons.map_outlined, color: AirmiusColors.blue),
  _ProfileSection(area: 'Sport', title: 'Sportarten', body: 'Hauptsportarten, Disziplinen, Altersgruppen, Teams und Trainingsangebote.', status: 'Sport', owner: 'Club', icon: Icons.sports_outlined, color: AirmiusColors.green),
  _ProfileSection(area: 'Sport', title: 'Aufnahmebedingungen', body: 'Mindestalter, Vorerfahrung, Probetraining, Lizenznummer und erforderliche Dokumente.', status: 'Antrag', owner: 'Membership', icon: Icons.assignment_ind_outlined, color: AirmiusColors.amber),
  _ProfileSection(area: 'Social', title: 'Social Links', body: 'Website, Instagram, Facebook, YouTube, TikTok, Vereinsnewsletter und externe Links.', status: 'Links', owner: 'Public', icon: Icons.link_outlined, color: AirmiusColors.blue),
  _ProfileSection(area: 'Social', title: 'Sponsoren Preview', body: 'Sponsorlogos, aktive Kampagnen und Public-Sponsorbereich im Vereinsprofil anzeigen.', status: 'Ads', owner: 'Sponsors', icon: Icons.handshake_outlined, color: AirmiusColors.green),
  _ProfileSection(area: 'Trust', title: 'Club-Verifizierung', body: 'Nachweis, Registerdaten, Adminprüfung, Status, Ablehnungsgrund und Public Badge.', status: 'Trust', owner: 'Admin', icon: Icons.verified_user_outlined, color: AirmiusColors.green),
  _ProfileSection(area: 'Trust', title: 'Profil-Audit', body: 'Letzte Bearbeitung, verantwortlicher Admin, Versionen, Datenschutzstatus und Review-Hinweise.', status: 'Audit', owner: 'System', icon: Icons.history_outlined, color: AirmiusColors.amber),
];
