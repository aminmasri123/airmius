import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class LaravelApiEndpointMappingSuiteScreen extends StatefulWidget {
  const LaravelApiEndpointMappingSuiteScreen({super.key});

  @override
  State<LaravelApiEndpointMappingSuiteScreen> createState() =>
      _LaravelApiEndpointMappingSuiteScreenState();
}

class _LaravelApiEndpointMappingSuiteScreenState
    extends State<LaravelApiEndpointMappingSuiteScreen> {
  String _area = 'Club';
  bool _authHeaders = true;
  bool _pagination = true;
  bool _errorContract = true;
  bool _offlineQueue = false;

  @override
  Widget build(BuildContext context) {
    final endpoints = _endpoints.where((item) => item.area == _area).toList();

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const Text(
          'API Endpoint Mapping',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: 'Laravel API Endpoint Mapping',
        subtitle:
            'Mobile UI-Matrix für Web-Routen, Flutter-Screens, API-Methoden, Payloads und Fehlerzustaende.',
        trailing: StatusPill('Laravel v1', color: airmiusAccentColor(context)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('API BRIDGE'),
                  const SizedBox(height: 8),
                  Text(
                    'Die Flutter-App bekommt eine klare Laravel-Landkarte.',
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                      height: 1.08,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Jeder wichtige Web-App-Flow wird einem mobilen Screen, einem API-Endpunkt, Auth-Regeln, Payloads und Feedback-Zustaenden zugeordnet.',
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.42,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children:
                        [
                          'Auth',
                          'Club',
                          'Membership',
                          'Files',
                          'Social',
                          'Commerce',
                          'Admin',
                        ].map((item) {
                          return ChoiceChip(
                            selected: _area == item,
                            label: Text(item),
                            onSelected: (_) => setState(() => _area = item),
                            selectedColor: airmiusAccentColor(
                              context,
                            ).withValues(alpha: .22),
                            backgroundColor: airmiusSurfaceSoftColor(context),
                            side: BorderSide(
                              color: _area == item
                                  ? airmiusAccentColor(context)
                                  : airmiusBorderColor(context),
                            ),
                            labelStyle: TextStyle(
                              color: _area == item
                                  ? airmiusTextColor(context)
                                  : airmiusMutedColor(context),
                              fontWeight: FontWeight.w900,
                            ),
                          );
                        }).toList(),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(
                  child: MetricCard(value: '7', label: 'Areas'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: 'v1', label: 'API'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: 'UI', label: 'Mapped'),
                ),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      const Expanded(child: Eyebrow('API CONTRACT FLAGS')),
                      StatusPill(_area, color: airmiusAccentColor(context)),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _ContractToggle(
                    icon: Icons.lock_outline,
                    title: 'Auth Header & Rollen',
                    body:
                        'Bearer Token, Workspace, Club-Rolle, Admin-Rolle und Guardian-Kontext werden pro Request mitgedacht.',
                    value: _authHeaders,
                    onChanged: (value) => setState(() => _authHeaders = value),
                  ),
                  _ContractToggle(
                    icon: Icons.view_list_outlined,
                    title: 'Pagination & Filter',
                    body:
                        'Listen wie Vereine, Mitglieder, Dateien, Bestellungen und Reports bekommen Cursor, Sortierung und Filterchips.',
                    value: _pagination,
                    onChanged: (value) => setState(() => _pagination = value),
                  ),
                  _ContractToggle(
                    icon: Icons.sync_problem_outlined,
                    title: 'Fehlervertrag',
                    body:
                        'Validation, Unauthorized, Forbidden, Not Found, Rate Limit und Serverfehler werden als mobile State-UI gemappt.',
                    value: _errorContract,
                    onChanged: (value) =>
                        setState(() => _errorContract = value),
                  ),
                  _ContractToggle(
                    icon: Icons.cloud_off_outlined,
                    title: 'Offline Queue',
                    body:
                        'Antraege, Chat, Uploads und Form-Drafts können später in eine Retry-Queue gelegt werden.',
                    value: _offlineQueue,
                    onChanged: (value) => setState(() => _offlineQueue = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final endpoint in endpoints) ...[
              _EndpointCard(endpoint: endpoint),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: Theme.of(
                context,
              ).colorScheme.secondary.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('BINDING NEXT STEP'),
                  const SizedBox(height: 8),
                  Text(
                    'Wenn Laravel später API-Routen liefert, werden diese UI-Karten zu echten Client-Services, Repository-Methoden und Ladezustaenden.',
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.38,
                    ),
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(
                    label: 'API-Client später anbinden',
                    icon: Icons.api_outlined,
                    onPressed: () {},
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

class _Endpoint {
  const _Endpoint({
    required this.area,
    required this.method,
    required this.path,
    required this.screen,
    required this.status,
    required this.body,
    required this.color,
  });

  final String area;
  final String method;
  final String path;
  final String screen;
  final String status;
  final String body;
  final Color color;
}

const _endpoints = [
  _Endpoint(
    area: 'Auth',
    method: 'POST',
    path: '/api/v1/login',
    screen: 'Login + Session',
    status: 'Prepared',
    body: 'E-Mail, Passwort, 2FA, Token Restore und Device Session.',
    color: AirmiusColors.blue,
  ),
  _Endpoint(
    area: 'Auth',
    method: 'GET',
    path: '/api/v1/me',
    screen: 'Profile Shell',
    status: 'Prepared',
    body: 'User, Rollen, Workspaces, Sprache und Profile Completion.',
    color: AirmiusColors.green,
  ),
  _Endpoint(
    area: 'Club',
    method: 'GET',
    path: '/api/v1/clubs',
    screen: 'Vereine & Suche',
    status: 'Mapped',
    body: 'Öffentliche Vereine, Suchfilter, Status-Pills und Beitritts-CTA.',
    color: AirmiusColors.blue,
  ),
  _Endpoint(
    area: 'Club',
    method: 'GET',
    path: '/api/v1/clubs/{id}',
    screen: 'Club Public Preview',
    status: 'Mapped',
    body:
        'Hero, Teams, Admins, Dokumente, Kontakt, Sichtbarkeit und Mitgliederzahlen.',
    color: AirmiusColors.green,
  ),
  _Endpoint(
    area: 'Club',
    method: 'PATCH',
    path: '/api/v1/clubs/{id}/visibility',
    screen: 'Vereinssichtbarkeit',
    status: 'Mapped',
    body:
        'Schalter für Profilbereiche, Dokumente, Mitglieder, Teams und Antragsschalter.',
    color: AirmiusColors.amber,
  ),
  _Endpoint(
    area: 'Membership',
    method: 'POST',
    path: '/api/v1/clubs/{id}/applications',
    screen: 'Mitgliedsantrag',
    status: 'Mapped',
    body:
        'Dynamisches Formular, Dokumente, Zahlungsdaten, Datenschutz und Fehlertexte.',
    color: AirmiusColors.green,
  ),
  _Endpoint(
    area: 'Membership',
    method: 'DELETE',
    path: '/api/v1/applications/{id}',
    screen: 'Anfrage zurückziehen',
    status: 'Mapped',
    body:
        'Rückzug vor Admin-Entscheidung mit Status-Update und Benachrichtigung.',
    color: AirmiusColors.amber,
  ),
  _Endpoint(
    area: 'Files',
    method: 'POST',
    path: '/api/v1/clubs/{id}/files',
    screen: 'Dateimanager Upload',
    status: 'Mapped',
    body:
        'Datenschutz, Satzung, Beitragsordnung, Teamdateien und Formularanhang.',
    color: AirmiusColors.blue,
  ),
  _Endpoint(
    area: 'Files',
    method: 'GET',
    path: '/api/v1/files/{id}',
    screen: 'Dokument Preview',
    status: 'Prepared',
    body: 'Version, Zweck, Consent-Pflicht, Download und Sichtbarkeit.',
    color: AirmiusColors.green,
  ),
  _Endpoint(
    area: 'Social',
    method: 'GET',
    path: '/api/v1/feed',
    screen: 'Feed & Community',
    status: 'Prepared',
    body: 'Posts, Medien, Kommentare, Reaktionen, Reports und Moderation.',
    color: AirmiusColors.blue,
  ),
  _Endpoint(
    area: 'Social',
    method: 'POST',
    path: '/api/v1/messages',
    screen: 'Nachrichten',
    status: 'Prepared',
    body: 'Private Chats, Vereinsadmin-Kanal, Teamchat und Dateien.',
    color: AirmiusColors.green,
  ),
  _Endpoint(
    area: 'Commerce',
    method: 'GET',
    path: '/api/v1/marketplace/products',
    screen: 'Marketplace',
    status: 'Prepared',
    body: 'Produkte, Clubshop, Sponsorangebote, Warenkorb und Checkout.',
    color: AirmiusColors.blue,
  ),
  _Endpoint(
    area: 'Commerce',
    method: 'POST',
    path: '/api/v1/orders',
    screen: 'Order Flow',
    status: 'Prepared',
    body: 'Bestellung, Zahlung, Abholung, Versand, Rückgabe und Rechnungen.',
    color: AirmiusColors.amber,
  ),
  _Endpoint(
    area: 'Admin',
    method: 'GET',
    path: '/api/v1/admin/moderation',
    screen: 'Moderation Queue',
    status: 'Prepared',
    body:
        'Reports, Verifizierungen, Datenschutzanfragen, Eskalationen und Audit.',
    color: AirmiusColors.amber,
  ),
  _Endpoint(
    area: 'Admin',
    method: 'PATCH',
    path: '/api/v1/admin/users/{id}',
    screen: 'User Management',
    status: 'Prepared',
    body: 'Statuswechsel, Rollen, Sperren, Entsperren und Admin-Notizen.',
    color: AirmiusColors.blue,
  ),
];

class _EndpointCard extends StatelessWidget {
  const _EndpointCard({required this.endpoint});

  final _Endpoint endpoint;

  @override
  Widget build(BuildContext context) {
    final endpointColor = airmiusSemanticColor(context, endpoint.color);
    return AirmiusPanel(
      borderColor: endpointColor.withValues(alpha: .45),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              StatusPill(endpoint.method, color: endpointColor),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  endpoint.path,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              StatusPill(endpoint.status, color: endpointColor),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            endpoint.screen,
            style: TextStyle(
              color: airmiusAccentColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            endpoint.body,
            style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
          ),
        ],
      ),
    );
  }
}

class _ContractToggle extends StatelessWidget {
  const _ContractToggle({
    required this.icon,
    required this.title,
    required this.body,
    required this.value,
    required this.onChanged,
    this.last = false,
  });

  final IconData icon;
  final String title;
  final String body;
  final bool value;
  final ValueChanged<bool> onChanged;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final activeColor = value
        ? Theme.of(context).colorScheme.secondary
        : airmiusMutedColor(context);
    return Padding(
      padding: EdgeInsets.only(bottom: last ? 0 : 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: activeColor.withValues(alpha: .14),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: value
                    ? activeColor.withValues(alpha: .45)
                    : airmiusBorderColor(context),
              ),
            ),
            child: Icon(icon, color: activeColor),
          ),
          const SizedBox(width: 12),
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
                  body,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
              ],
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
