import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class MobileTableActionParitySuiteScreen extends StatefulWidget {
  const MobileTableActionParitySuiteScreen({super.key});

  @override
  State<MobileTableActionParitySuiteScreen> createState() =>
      _MobileTableActionParitySuiteScreenState();
}

class _MobileTableActionParitySuiteScreenState
    extends State<MobileTableActionParitySuiteScreen> {
  String _area = 'Verein';
  String _sort = 'Neueste';
  bool _bulkMode = false;
  bool _showExports = true;
  bool _showDangerActions = false;

  static const _areas = ['Verein', 'Admin', 'Commerce', 'Content', 'Sport'];
  static const _sorts = ['Neueste', 'Status', 'Name', 'Faelligkeit'];

  static const _rows = <_MobileRow>[
    _MobileRow(
      area: 'Verein',
      title: 'Mitgliedschaftsanfrage ZBB Konto',
      meta: 'ClubMemberships/Index',
      status: 'Offen',
      body:
          'Antrag, Formularfelder, Dokumente, Zahlweise, Rückzug und Adminentscheidung als mobile Listenkarte.',
      icon: Icons.assignment_ind_outlined,
      primary: 'Antrag prüfen',
      secondary: 'Dokumente',
      color: AirmiusColors.green,
    ),
    _MobileRow(
      area: 'Verein',
      title: 'Mitglied Max Mustermann',
      meta: 'Users/Profile + Club Member Directory',
      status: 'Aktiv',
      body:
          'Rolle, Team, Zahlstatus, Dateien, Notizen, Audit und schnelle Statusänderung ohne breite Tabelle.',
      icon: Icons.people_outline,
      primary: 'Profil',
      secondary: 'Status',
      color: AirmiusColors.blue,
    ),
    _MobileRow(
      area: 'Admin',
      title: 'Vereinsverifizierung Airmius Running Club',
      meta: 'Admin/ClubVerifications',
      status: 'Review',
      body:
          'Dokumente, Impressum, Kontakt, Entscheidung, Ablehnung, Kommentar und Benachrichtigung als Admin-Karte.',
      icon: Icons.verified_user_outlined,
      primary: 'Freigeben',
      secondary: 'Ablehnen',
      color: AirmiusColors.amber,
    ),
    _MobileRow(
      area: 'Admin',
      title: 'Moderationsmeldung Beitrag #482',
      meta: 'Admin/Moderation',
      status: 'Eskalation',
      body:
          'Reportgrund, Autor, Inhalt, Maturity, Aktion, Sperre, Audit und Rückmeldung im mobilen Action-Sheet.',
      icon: Icons.flag_outlined,
      primary: 'Entscheiden',
      secondary: 'Audit',
      color: AirmiusColors.red,
    ),
    _MobileRow(
      area: 'Commerce',
      title: 'Bestellung Marketplace #A-1042',
      meta: 'MarketplaceOrderStatus',
      status: 'Banktransfer',
      body:
          'Zahlstatus, Bankdaten, Rechnung, Lieferung, Support und Rückkehr zum Produkt als mobile Statuskarte.',
      icon: Icons.receipt_long_outlined,
      primary: 'Status',
      secondary: 'Beleg',
      color: AirmiusColors.green,
    ),
    _MobileRow(
      area: 'Commerce',
      title: 'Produkt Vereins-Shirt',
      meta: 'Commerce/ProductShow',
      status: 'Aktiv',
      body:
          'Preis, Varianten, Bestand, Anbieter, Wishlist, Warenkorb und Adminaktionen ohne Desktop-Tabelle.',
      icon: Icons.inventory_2_outlined,
      primary: 'Produkt',
      secondary: 'Bestand',
      color: AirmiusColors.blue,
    ),
    _MobileRow(
      area: 'Content',
      title: 'Blogbeitrag Trainingsplanung',
      meta: 'Blogs/Index + Categories',
      status: 'Entwurf',
      body:
          'Kategorie, Autor, Public Preview, Medien, Freigabe, SEO und Publishing als Karten-Workflow.',
      icon: Icons.article_outlined,
      primary: 'Bearbeiten',
      secondary: 'Preview',
      color: AirmiusColors.blue,
    ),
    _MobileRow(
      area: 'Content',
      title: 'Datei Datenschutzordnung.pdf',
      meta: 'Files/Index + ClubPolicyDocuments',
      status: 'Verknuepft',
      body:
          'Upload, Zweck, Sichtbarkeit, Link, Pflichtdokument, Version und Dateimanager-Zuordnung als mobile Zeile.',
      icon: Icons.folder_outlined,
      primary: 'Öffnen',
      secondary: 'Verknuepfen',
      color: AirmiusColors.amber,
    ),
    _MobileRow(
      area: 'Sport',
      title: 'Training Event Samstag',
      meta: 'Events/Index + Show',
      status: '24 Zusagen',
      body:
          'Teilnahme, Warteliste, Team, Ort, Guardian-Gate, Kalender und Traineraktion in einer mobilen Karte.',
      icon: Icons.event_available_outlined,
      primary: 'Teilnahme',
      secondary: 'Details',
      color: AirmiusColors.green,
    ),
    _MobileRow(
      area: 'Sport',
      title: 'Trainingslog Intervall',
      meta: 'Training/LogShow',
      status: 'Feedback',
      body:
          'Leistungswerte, Coach-Kommentar, Sichtbarkeit, Medien und Planbezug als kompakte Detailkarte.',
      icon: Icons.fitness_center_outlined,
      primary: 'Log',
      secondary: 'Feedback',
      color: AirmiusColors.blue,
    ),
  ];

  List<_MobileRow> get _visibleRows =>
      _rows.where((row) => row.area == _area).toList();

  @override
  Widget build(BuildContext context) {
    final selectedCount = _bulkMode ? _visibleRows.length : 0;

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const AirmiusLogo(compact: true),
      ),
      body: SafeArea(
        child: PageFrame(
          title: 'Mobile Table Action Parity',
          subtitle:
              'Web-Tabellen werden mobile Karten, Filter und Action-Sheets.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Hero(
                area: _area,
                sort: _sort,
                selectedCount: selectedCount,
                showExports: _showExports,
              ),
              const SizedBox(height: 16),
              _FilterPanel(
                areas: _areas,
                activeArea: _area,
                sorts: _sorts,
                activeSort: _sort,
                onArea: (value) => setState(() => _area = value),
                onSort: (value) => setState(() => _sort = value),
              ),
              const SizedBox(height: 16),
              _ModePanel(
                bulkMode: _bulkMode,
                showExports: _showExports,
                showDangerActions: _showDangerActions,
                onBulk: (value) => setState(() => _bulkMode = value),
                onExports: (value) => setState(() => _showExports = value),
                onDanger: (value) => setState(() => _showDangerActions = value),
              ),
              const SizedBox(height: 16),
              if (_bulkMode)
                _BulkBar(
                  count: selectedCount,
                  showExports: _showExports,
                  onAction: () => openUiAction(
                    context,
                    title: 'Bulk-Aktion',
                    body:
                        'Mehrfachauswahl für $_area: Statuswechsel, Export, Benachrichtigung oder Rollenaktion vorbereiten.',
                    status: 'Bulk',
                    icon: Icons.select_all_outlined,
                  ),
                ),
              if (_bulkMode) const SizedBox(height: 12),
              for (final row in _visibleRows) ...[
                _MobileRowCard(
                  row: row,
                  bulkMode: _bulkMode,
                  showDangerActions: _showDangerActions,
                ),
                const SizedBox(height: 12),
              ],
              const SizedBox(height: 4),
              _Checklist(
                onOpen: () => openUiAction(
                  context,
                  title: 'Table Action Parity',
                  body:
                      'Desktop-Tabellenmuster wurden für mobile Karten, Filter, Sortierung, Pagination, Bulk-Auswahl, Export und Action-Sheets vorbereitet.',
                  status: 'Mobile Tables',
                  icon: Icons.table_rows_outlined,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({
    required this.area,
    required this.sort,
    required this.selectedCount,
    required this.showExports,
  });

  final String area;
  final String sort;
  final int selectedCount;
  final bool showExports;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('MOBILE LISTEN'),
          const SizedBox(height: 8),
          Text(
            'Tabellen werden zu Karten, nicht zu Mini-Excel.',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Viele Webmodule nutzen Tabellen. In der App werden daraus mobile Listen mit Filterchips, Status-Pills, Detail-CTA, Bulk-Bar und sicheren Action-Sheets.',
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.45,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _Metric(value: area, label: 'Bereich'),
              _Metric(value: sort, label: 'Sortierung'),
              _Metric(value: '$selectedCount', label: 'Auswahl'),
              _Metric(value: showExports ? 'An' : 'Aus', label: 'Export'),
            ],
          ),
        ],
      ),
    );
  }
}

class _FilterPanel extends StatelessWidget {
  const _FilterPanel({
    required this.areas,
    required this.activeArea,
    required this.sorts,
    required this.activeSort,
    required this.onArea,
    required this.onSort,
  });

  final List<String> areas;
  final String activeArea;
  final List<String> sorts;
  final String activeSort;
  final ValueChanged<String> onArea;
  final ValueChanged<String> onSort;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Filter und Sortierung',
      subtitle:
          'Desktop-Tabellenfilter werden zu horizontalen Chips und klaren mobilen Suchzustaenden.',
      children: [
        const Eyebrow('Bereich'),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: areas
              .map(
                (area) => _Chip(
                  label: area,
                  selected: activeArea == area,
                  onTap: () => onArea(area),
                ),
              )
              .toList(),
        ),
        const SizedBox(height: 14),
        const Eyebrow('Sortieren nach'),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: sorts
              .map(
                (sort) => _Chip(
                  label: sort,
                  selected: activeSort == sort,
                  onTap: () => onSort(sort),
                  green: true,
                ),
              )
              .toList(),
        ),
      ],
    );
  }
}

class _ModePanel extends StatelessWidget {
  const _ModePanel({
    required this.bulkMode,
    required this.showExports,
    required this.showDangerActions,
    required this.onBulk,
    required this.onExports,
    required this.onDanger,
  });

  final bool bulkMode;
  final bool showExports;
  final bool showDangerActions;
  final ValueChanged<bool> onBulk;
  final ValueChanged<bool> onExports;
  final ValueChanged<bool> onDanger;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Aktionen',
      subtitle:
          'Listenaktionen werden auf Mobile sichtbar, aber nicht überladen.',
      children: [
        _SwitchLine(
          title: 'Bulk-Auswahl aktivieren',
          value: bulkMode,
          onChanged: onBulk,
        ),
        _SwitchLine(
          title: 'Export-/Download-CTAs zeigen',
          value: showExports,
          onChanged: onExports,
        ),
        _SwitchLine(
          title: 'Gefaehrliche Aktionen sichtbar',
          value: showDangerActions,
          onChanged: onDanger,
        ),
      ],
    );
  }
}

class _BulkBar extends StatelessWidget {
  const _BulkBar({
    required this.count,
    required this.showExports,
    required this.onAction,
  });

  final int count;
  final bool showExports;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: Theme.of(context).colorScheme.secondary,
      child: Wrap(
        spacing: 10,
        runSpacing: 10,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          StatusPill(
            '$count ausgewählt',
            color: Theme.of(context).colorScheme.secondary,
          ),
          AirmiusButton(
            label: 'Status wechseln',
            icon: Icons.swap_horiz_outlined,
            onPressed: onAction,
          ),
          AirmiusButton(
            label: 'Benachrichtigen',
            icon: Icons.mark_email_read_outlined,
            secondary: true,
            onPressed: onAction,
          ),
          if (showExports)
            AirmiusButton(
              label: 'Export',
              icon: Icons.download_outlined,
              secondary: true,
              onPressed: onAction,
            ),
        ],
      ),
    );
  }
}

class _MobileRowCard extends StatelessWidget {
  const _MobileRowCard({
    required this.row,
    required this.bulkMode,
    required this.showDangerActions,
  });

  final _MobileRow row;
  final bool bulkMode;
  final bool showDangerActions;

  @override
  Widget build(BuildContext context) {
    final color = airmiusSemanticColor(context, row.color);
    return AirmiusPanel(
      borderColor: color.withValues(alpha: .5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (bulkMode) ...[
                Checkbox(
                  value: true,
                  activeColor: Theme.of(context).colorScheme.secondary,
                  onChanged: (_) {},
                ),
                const SizedBox(width: 8),
              ],
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: color.withValues(alpha: .55)),
                ),
                child: Icon(row.icon, color: color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      row.title,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      row.meta,
                      style: TextStyle(
                        color: airmiusAccentColor(context),
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              StatusPill(row.status, color: color),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            row.body,
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.42,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: row.primary,
                icon: row.icon,
                onPressed: () => openUiAction(
                  context,
                  title: row.primary,
                  body:
                      '${row.title}: ${row.body}\n\nMobile Tabellenzeile für ${row.meta}.',
                  status: row.status,
                  icon: row.icon,
                ),
              ),
              AirmiusButton(
                label: row.secondary,
                icon: Icons.more_horiz_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: row.secondary,
                  body:
                      'Action-Sheet mit Details, Rollen, Dokumenten, Export, Benachrichtigung und Audit für ${row.title}.',
                  status: 'Action Sheet',
                  icon: Icons.more_horiz_outlined,
                ),
              ),
              if (showDangerActions)
                AirmiusButton(
                  label: 'Sperren',
                  icon: Icons.block_outlined,
                  danger: true,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Gefaehrliche Aktion',
                    body:
                        'Danger-Aktion für ${row.title}: Bestätigung, Grund, Audit und Rückmeldung erforderlich.',
                    status: 'Danger',
                    icon: Icons.block_outlined,
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Checklist extends StatelessWidget {
  const _Checklist({required this.onOpen});

  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Tabellen-Paritaet',
      subtitle: 'Was aus Desktop-Listen mobil übersetzt wird.',
      children: [
        const _CheckLine(
          'Jede Tabellenzeile wird eine lesbare Karte mit Status, Kontext und CTA.',
        ),
        const _CheckLine(
          'Filter, Suche und Sortierung werden als Chips und kompakte Panels gefuehrt.',
        ),
        const _CheckLine(
          'Bulk-Auswahl, Export und Benachrichtigungen bleiben mobil bedienbar.',
        ),
        const _CheckLine(
          'Gefaehrliche Aktionen bleiben hinter expliziten Action-Sheets und Audit-Hinweisen.',
        ),
        const SizedBox(height: 12),
        AirmiusButton(
          label: 'Tabellen-Paritaet markieren',
          icon: Icons.fact_check_outlined,
          onPressed: onOpen,
        ),
      ],
    );
  }
}

class _SwitchLine extends StatelessWidget {
  const _SwitchLine({
    required this.title,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: airmiusInputColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          Switch(
            value: value,
            activeThumbColor: Theme.of(context).colorScheme.secondary,
            onChanged: onChanged,
          ),
        ],
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({
    required this.label,
    required this.selected,
    required this.onTap,
    this.green = false,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;
  final bool green;

  @override
  Widget build(BuildContext context) {
    final color = green
        ? Theme.of(context).colorScheme.secondary
        : airmiusAccentColor(context);
    return ChoiceChip(
      selected: selected,
      label: Text(label),
      onSelected: (_) => onTap(),
      selectedColor: color.withValues(alpha: .25),
      backgroundColor: airmiusSurfaceSoftColor(context),
      side: BorderSide(color: selected ? color : airmiusBorderColor(context)),
      labelStyle: TextStyle(
        color: selected
            ? airmiusTextColor(context)
            : airmiusMutedColor(context),
        fontWeight: FontWeight.w900,
      ),
    );
  }
}

class _CheckLine extends StatelessWidget {
  const _CheckLine(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.check_circle_outline,
            color: Theme.of(context).colorScheme.secondary,
            size: 19,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
                height: 1.35,
              ),
            ),
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
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: airmiusSurfaceColor(context).withValues(alpha: .55),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _MobileRow {
  const _MobileRow({
    required this.area,
    required this.title,
    required this.meta,
    required this.status,
    required this.body,
    required this.icon,
    required this.primary,
    required this.secondary,
    required this.color,
  });

  final String area;
  final String title;
  final String meta;
  final String status;
  final String body;
  final IconData icon;
  final String primary;
  final String secondary;
  final Color color;
}
