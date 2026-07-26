import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ExactPageFlowParitySuiteScreen extends StatefulWidget {
  const ExactPageFlowParitySuiteScreen({super.key});

  @override
  State<ExactPageFlowParitySuiteScreen> createState() =>
      _ExactPageFlowParitySuiteScreenState();
}

class _ExactPageFlowParitySuiteScreenState
    extends State<ExactPageFlowParitySuiteScreen> {
  String _area = 'Dashboard';
  bool _showAdmin = true;
  bool _showPublic = true;
  bool _showStatusPages = true;

  static const _areas = [
    'Dashboard',
    'Commerce',
    'Training',
    'Admin',
    'Public',
  ];

  static const _flows = <_ExactFlow>[
    _ExactFlow(
      area: 'Dashboard',
      title: 'User Create / Edit / Profile',
      route: 'Auth/Dashboard/Users/Create, Edit, Profile',
      body:
          'Nutzeranlage, Bearbeitung, Rollen, Status, Profildaten, Sperren, Audit und Rücknavigation als kompakte Mobile-Formulare.',
      status: 'Create/Edit',
      icon: Icons.person_add_alt_1_outlined,
      primary: 'User-Formular',
      secondary: 'Profilstatus',
    ),
    _ExactFlow(
      area: 'Dashboard',
      title: 'Team Profile',
      route: 'Auth/Dashboard/Teams/Profile',
      body:
          'Team-Hero, Kader, Rollen, Trainings, Dateien, Chatrechte und Join-Status wie mobile Web-App-Detailseite.',
      status: 'Show',
      icon: Icons.groups_outlined,
      primary: 'Team ansehen',
      secondary: 'Kader prüfen',
    ),
    _ExactFlow(
      area: 'Dashboard',
      title: 'Badge Show / User Index',
      route: 'Auth/Dashboard/Badges/Show, UserIndex',
      body:
          'Badge-Detail, Fortschritt, Regeln, Datenschutz, User-Badges und Leaderboard-Teilzustand als native Karten.',
      status: 'Detail',
      icon: Icons.workspace_premium_outlined,
      primary: 'Badge öffnen',
      secondary: 'User-Badges',
    ),
    _ExactFlow(
      area: 'Dashboard',
      title: 'Blog Categories',
      route: 'Auth/Dashboard/Blogs/Categories',
      body:
          'Kategorieverwaltung, Sichtbarkeit, Reihenfolge, Slug, Moderationsstatus und Public Preview für Editorial Admins.',
      status: 'Admin UI',
      icon: Icons.category_outlined,
      primary: 'Kategorie bearbeiten',
      secondary: 'Preview',
    ),
    _ExactFlow(
      area: 'Commerce',
      title: 'Cart / Checkout / BankTransfer',
      route: 'Commerce/Cart, BankTransfer, Subscriptions/BankTransfer',
      body:
          'Warenkorb, Zahlungsart, Bankdaten, Verwendungszweck, Rechnungsstatus und Rückkehr zur Bestellung mobil abbilden.',
      status: 'Checkout',
      icon: Icons.shopping_cart_outlined,
      primary: 'Checkout öffnen',
      secondary: 'Banktransfer',
    ),
    _ExactFlow(
      area: 'Commerce',
      title: 'Product Show / Order Status',
      route: 'Commerce/ProductShow, Guest/MarketplaceOrderStatus',
      body:
          'Produktdetail, Varianten, Anbieter, Wunschliste, Bestellung, Status, Zahlungshinweis und Support-CTA.',
      status: 'Status',
      icon: Icons.inventory_2_outlined,
      primary: 'Produktdetail',
      secondary: 'Order Status',
    ),
    _ExactFlow(
      area: 'Commerce',
      title: 'Provider Show / Wishlist',
      route: 'Guest/MarketplaceProviderShow, Wishlist',
      body:
          'Anbieterprofil, Bewertungen, Produkte, Kontakt, Merkliste, Datenschutz und Public-Kaufstrecke.',
      status: 'Public Shop',
      icon: Icons.storefront_outlined,
      primary: 'Provider',
      secondary: 'Wishlist',
    ),
    _ExactFlow(
      area: 'Training',
      title: 'Event Show / Attendance',
      route: 'Auth/Dashboard/Events/Show',
      body:
          'Eventdetail, Teilnahme, Abmeldung, Warteliste, Guardian-Gate, Kalender und Team-/Vereinskontext.',
      status: 'Event',
      icon: Icons.event_available_outlined,
      primary: 'Teilnahme',
      secondary: 'Kalender',
    ),
    _ExactFlow(
      area: 'Training',
      title: 'Training Log Create / Show',
      route: 'Training/LogCreate, LogShow',
      body:
          'Training erfassen, Werte prüfen, Medien, Coach-Feedback, Sichtbarkeit und nachtraegliche Detailansicht.',
      status: 'Log',
      icon: Icons.edit_calendar_outlined,
      primary: 'Log erstellen',
      secondary: 'Log ansehen',
    ),
    _ExactFlow(
      area: 'Training',
      title: 'Training Plan Item Show',
      route: 'Training/PlanItemShow',
      body:
          'Planpunkt, Tagesziel, Uebungen, Satz-/Wiederholungswerte, Notizen, Abhaken und Coach-Kommentar.',
      status: 'Plan',
      icon: Icons.checklist_outlined,
      primary: 'Planpunkt',
      secondary: 'Feedback',
    ),
    _ExactFlow(
      area: 'Admin',
      title: 'Admin Invoices / Payments',
      route: 'Admin/Invoices, Payments, SubscriptionInvoices',
      body:
          'Rechnungsliste, Zahlungsfilter, Providerstatus, Export, Detailstatus, Mahnung und Korrekturaktion.',
      status: 'Finance',
      icon: Icons.receipt_long_outlined,
      primary: 'Rechnungen',
      secondary: 'Zahlungen',
    ),
    _ExactFlow(
      area: 'Admin',
      title: 'Provider Costs / Operating Contracts',
      route: 'Admin/ProviderCosts, OperatingContracts',
      body:
          'Kostenstellen, Vertrage, Laufzeiten, Warnungen, Verantwortliche und Admin-Review als mobile Kontrollansicht.',
      status: 'Ops',
      icon: Icons.assignment_outlined,
      primary: 'Vertrag',
      secondary: 'Kosten',
    ),
    _ExactFlow(
      area: 'Public',
      title: 'Learning Course Show / Certificate Verify',
      route: 'Guest/LearningCourseShow, LearningCertificateVerify',
      body:
          'Public Kursdetail, Lektionen, Preis/Freigabe, Zertifikatscode, Ergebnis, Download und Meldung.',
      status: 'Public Learning',
      icon: Icons.school_outlined,
      primary: 'Kursdetail',
      secondary: 'Zertifikat',
    ),
    _ExactFlow(
      area: 'Public',
      title: 'Blog Show / Top Inhalte',
      route: 'Guest/Blog/Show, Top-Inhalte',
      body:
          'Public Artikel, Autorenbox, Share, Related Content, Meldung, SEO-Hinweis und kuratierte Top-Inhalte.',
      status: 'Public Content',
      icon: Icons.article_outlined,
      primary: 'Artikel',
      secondary: 'Top Inhalte',
    ),
  ];

  List<_ExactFlow> get _visibleFlows {
    return _flows.where((flow) {
      if (flow.area == 'Admin' && !_showAdmin) return false;
      if (flow.area == 'Public' && !_showPublic) return false;
      if ((flow.status == 'Status' || flow.status == 'Checkout') &&
          !_showStatusPages) {
        return false;
      }
      return flow.area == _area;
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
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
          title: 'Exact Page Flow Parity',
          subtitle: 'Feine Web-Seitenzustaende als native mobile Flow-Karten.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Hero(
                area: _area,
                showAdmin: _showAdmin,
                showPublic: _showPublic,
                showStatusPages: _showStatusPages,
              ),
              const SizedBox(height: 16),
              _AreaTabs(
                areas: _areas,
                active: _area,
                onChanged: (value) => setState(() => _area = value),
              ),
              const SizedBox(height: 16),
              _TogglePanel(
                showAdmin: _showAdmin,
                showPublic: _showPublic,
                showStatusPages: _showStatusPages,
                onAdmin: (value) => setState(() => _showAdmin = value),
                onPublic: (value) => setState(() => _showPublic = value),
                onStatus: (value) => setState(() => _showStatusPages = value),
              ),
              const SizedBox(height: 16),
              for (final flow in _visibleFlows) ...[
                _ExactFlowCard(flow: flow),
                const SizedBox(height: 12),
              ],
              if (_visibleFlows.isEmpty)
                const EmptyPanel(
                  'Keine Page-Flows für diesen Filter sichtbar.',
                ),
              const SizedBox(height: 4),
              _ParityNote(
                onOpen: () => openUiAction(
                  context,
                  title: 'Exact Page Flow Parity',
                  body:
                      'Detail-, Create-, Edit-, Show-, Checkout-, BankTransfer- und Statusseiten sind als mobile UI-Flows dokumentiert. Backenddaten werden später per Laravel API verbunden.',
                  status: 'Parity',
                  icon: Icons.fact_check_outlined,
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
    required this.showAdmin,
    required this.showPublic,
    required this.showStatusPages,
  });

  final String area;
  final bool showAdmin;
  final bool showPublic;
  final bool showStatusPages;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('WEB PAGE PARITY'),
          const SizedBox(height: 8),
          Text(
            'Kleine Seiten, große Wirkung.',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Die Web-App hat viele Detail-, Status- und Formularseiten. Diese Suite sorgt dafür, dass sie in Flutter nicht als Nebenprodukt verloren gehen, sondern als eigene Mobile-Flows sichtbar bleiben.',
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
              _Metric(value: area, label: 'Aktiver Bereich'),
              _Metric(value: showAdmin ? 'An' : 'Aus', label: 'Admin-Flows'),
              _Metric(value: showPublic ? 'An' : 'Aus', label: 'Public-Flows'),
              _Metric(
                value: showStatusPages ? 'An' : 'Aus',
                label: 'Statusseiten',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _AreaTabs extends StatelessWidget {
  const _AreaTabs({
    required this.areas,
    required this.active,
    required this.onChanged,
  });

  final List<String> areas;
  final String active;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: areas
            .map(
              (area) => Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  label: Text(area),
                  selected: active == area,
                  onSelected: (_) => onChanged(area),
                  selectedColor: airmiusAccentColor(
                    context,
                  ).withValues(alpha: .25),
                  backgroundColor: airmiusSurfaceSoftColor(context),
                  side: BorderSide(
                    color: active == area
                        ? airmiusAccentColor(context)
                        : airmiusBorderColor(context),
                  ),
                  labelStyle: TextStyle(
                    color: active == area
                        ? airmiusTextColor(context)
                        : airmiusMutedColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            )
            .toList(),
      ),
    );
  }
}

class _TogglePanel extends StatelessWidget {
  const _TogglePanel({
    required this.showAdmin,
    required this.showPublic,
    required this.showStatusPages,
    required this.onAdmin,
    required this.onPublic,
    required this.onStatus,
  });

  final bool showAdmin;
  final bool showPublic;
  final bool showStatusPages;
  final ValueChanged<bool> onAdmin;
  final ValueChanged<bool> onPublic;
  final ValueChanged<bool> onStatus;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Flow-Filter',
      subtitle:
          'Später kommen diese Flags aus Rolle, Route, API-Status und Store-Device-Kontext.',
      children: [
        _SwitchRow(
          title: 'Admin-Detailseiten zeigen',
          value: showAdmin,
          onChanged: onAdmin,
        ),
        _SwitchRow(
          title: 'Public-Detailseiten zeigen',
          value: showPublic,
          onChanged: onPublic,
        ),
        _SwitchRow(
          title: 'Checkout- und Statusseiten zeigen',
          value: showStatusPages,
          onChanged: onStatus,
        ),
      ],
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({
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

class _ExactFlowCard extends StatelessWidget {
  const _ExactFlowCard({required this.flow});

  final _ExactFlow flow;

  @override
  Widget build(BuildContext context) {
    final color = airmiusSemanticColor(
      context,
      flow.area == 'Admin'
          ? AirmiusColors.amber
          : flow.area == 'Commerce'
          ? AirmiusColors.green
          : AirmiusColors.blue,
    );
    return AirmiusPanel(
      borderColor: color.withValues(alpha: .55),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: color.withValues(alpha: .55)),
                ),
                child: Icon(flow.icon, color: color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      flow.title,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      flow.route,
                      style: TextStyle(
                        color: airmiusAccentColor(context),
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              StatusPill(flow.status, color: color),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            flow.body,
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
                label: flow.primary,
                icon: flow.icon,
                onPressed: () => openUiAction(
                  context,
                  title: flow.primary,
                  body:
                      '${flow.title}: ${flow.body}\n\nRoute-Gruppe: ${flow.route}',
                  status: flow.status,
                  icon: flow.icon,
                ),
              ),
              AirmiusButton(
                label: flow.secondary,
                icon: Icons.manage_search_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: flow.secondary,
                  body:
                      'Detailstatus, API-Fehler, Ladezustand, Empty-State, Berechtigung und Rücknavigation für ${flow.route} anzeigen.',
                  status: 'Detail Flow',
                  icon: Icons.manage_search_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ParityNote extends StatelessWidget {
  const _ParityNote({required this.onOpen});

  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Warum diese Suite wichtig ist',
      subtitle: 'Hauptmodule reichen nicht aus, wenn Detailseiten fehlen.',
      children: [
        const _NoteLine(
          'Show-Seiten brauchen eigene mobile Statuskarten statt nur Listen-Navigation.',
        ),
        const _NoteLine(
          'Create/Edit-Seiten brauchen Formularzustand, Fehler, Pflichtfelder und Speichern-CTA.',
        ),
        const _NoteLine(
          'BankTransfer/Checkout/OrderStatus brauchen klare Zahlungs- und Rückkehrzustaende.',
        ),
        const _NoteLine(
          'Admin- und Public-Detailseiten bleiben getrennt, aber im gleichen Airmius-Designsystem.',
        ),
        const SizedBox(height: 12),
        AirmiusButton(
          label: 'Paritaet markieren',
          icon: Icons.fact_check_outlined,
          onPressed: onOpen,
        ),
      ],
    );
  }
}

class _NoteLine extends StatelessWidget {
  const _NoteLine(this.text);

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

class _ExactFlow {
  const _ExactFlow({
    required this.area,
    required this.title,
    required this.route,
    required this.body,
    required this.status,
    required this.icon,
    required this.primary,
    required this.secondary,
  });

  final String area;
  final String title;
  final String route;
  final String body;
  final String status;
  final IconData icon;
  final String primary;
  final String secondary;
}
