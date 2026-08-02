import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SessionSecurityTokenParitySuiteScreen extends StatefulWidget {
  const SessionSecurityTokenParitySuiteScreen({super.key});

  @override
  State<SessionSecurityTokenParitySuiteScreen> createState() =>
      _SessionSecurityTokenParitySuiteScreenState();
}

class _SessionSecurityTokenParitySuiteScreenState
    extends State<SessionSecurityTokenParitySuiteScreen> {
  String _scope = 'Session';
  String _risk = 'Normal';
  bool _twoFactor = true;
  bool _rememberDevice = true;
  bool _tokenRefresh = true;

  static const _scopes = ['Session', '2FA', 'Devices', 'API Tokens', 'Account'];
  static const _risks = ['Normal', 'Sensitive', 'Compromised'];

  static const _flows = <_SecurityFlow>[
    _SecurityFlow(
      scope: 'Session',
      title: 'Session wiederherstellen',
      body:
          'Beim App-Start wird User, Workspace, Rolle, Sprache, Tokenstatus und letzter Zielscreen als mobile Startlogik vorbereitet.',
      status: 'Restore',
      icon: Icons.restore_outlined,
      primary: 'Session laden',
      secondary: 'Cache',
      color: AirmiusColors.blue,
    ),
    _SecurityFlow(
      scope: 'Session',
      title: 'Token Refresh',
      body:
          'Access Token erneuern, API-Fehler behandeln, Retry zeigen und User bei abgelaufener Session freundlich zum Login führen.',
      status: 'Refresh',
      icon: Icons.sync_outlined,
      primary: 'Token erneuern',
      secondary: 'Retry',
      color: AirmiusColors.green,
    ),
    _SecurityFlow(
      scope: '2FA',
      title: 'Zwei-Faktor bestätigen',
      body:
          'Authenticator-Code, Recovery-Code, Trusted Device, Fehlertext, Rate Limit und Weiterleitung als mobile Security-Karte.',
      status: '2FA',
      icon: Icons.security_outlined,
      primary: 'Code prüfen',
      secondary: 'Recovery',
      color: AirmiusColors.blue,
    ),
    _SecurityFlow(
      scope: '2FA',
      title: 'Recovery-Codes verwalten',
      body:
          'Codes anzeigen, neu generieren, kopieren, warnen und mit Passwortbestätigung schützen.',
      status: 'Recovery',
      icon: Icons.key_outlined,
      primary: 'Codes',
      secondary: 'Neu generieren',
      color: AirmiusColors.amber,
    ),
    _SecurityFlow(
      scope: 'Devices',
      title: 'Geräte und Sessions',
      body:
          'Aktuelles Gerät, Browser/Web-Sessions, letzte Aktivität, IP-Hinweis, Logout anderer Sessions und Audit.',
      status: 'Devices',
      icon: Icons.devices_outlined,
      primary: 'Geräte',
      secondary: 'Andere abmelden',
      color: AirmiusColors.green,
    ),
    _SecurityFlow(
      scope: 'Devices',
      title: 'Verdacht auf fremden Zugriff',
      body:
          'Compromised State zeigt Passwortwechsel, alle Sessions beenden, 2FA aktivieren und Supportweg.',
      status: 'Risk',
      icon: Icons.report_gmailerrorred_outlined,
      primary: 'Sichern',
      secondary: 'Support',
      color: AirmiusColors.red,
    ),
    _SecurityFlow(
      scope: 'API Tokens',
      title: 'API Token verwalten',
      body:
          'Tokenname, Scopes, Ablauf, Kopieren, Widerruf, letzte Nutzung und Laravel Sanctum-Kompatibilität als mobile UI.',
      status: 'Token',
      icon: Icons.api_outlined,
      primary: 'Token',
      secondary: 'Scopes',
      color: AirmiusColors.blue,
    ),
    _SecurityFlow(
      scope: 'API Tokens',
      title: 'Token widerrufen',
      body:
          'Danger-Confirmation, betroffener Token, Scope, Audit, Erfolgsmeldung und Liste aktualisieren.',
      status: 'Revoke',
      icon: Icons.block_outlined,
      primary: 'Widerrufen',
      secondary: 'Audit',
      color: AirmiusColors.red,
    ),
    _SecurityFlow(
      scope: 'Account',
      title: 'Sensible Account-Aktion',
      body:
          'Passwort bestätigen, 2FA prüfen, Datenexport, Konto löschen, Löschcode und Rückweg als geschützte mobile Strecke.',
      status: 'Sensitive',
      icon: Icons.lock_outline,
      primary: 'Bestätigen',
      secondary: 'Abbrechen',
      color: AirmiusColors.amber,
    ),
    _SecurityFlow(
      scope: 'Account',
      title: 'Logout und Session-Ende',
      body:
          'Einzelnes Gerät abmelden, alle Sessions beenden, Cache löschen, Offline-Drafts warnen und zur Loginseite führen.',
      status: 'Logout',
      icon: Icons.logout_outlined,
      primary: 'Logout',
      secondary: 'Alle Sessions',
      color: AirmiusColors.green,
    ),
  ];

  List<_SecurityFlow> get _visibleFlows =>
      _flows.where((flow) => flow.scope == _scope).toList();

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
          title: 'Session Security Token Parity',
          subtitle: 'Mobile Auth-Sessions, 2FA, Geräte und API Tokens.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Hero(
                scope: _scope,
                risk: _risk,
                twoFactor: _twoFactor,
                tokenRefresh: _tokenRefresh,
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Security-Bereich',
                items: _scopes,
                active: _scope,
                color: airmiusAccentColor(context),
                onChanged: (value) => setState(() => _scope = value),
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Risiko',
                items: _risks,
                active: _risk,
                color: Theme.of(context).colorScheme.secondary,
                onChanged: (value) => setState(() => _risk = value),
              ),
              const SizedBox(height: 16),
              _RulesPanel(
                twoFactor: _twoFactor,
                rememberDevice: _rememberDevice,
                tokenRefresh: _tokenRefresh,
                onTwoFactor: (value) => setState(() => _twoFactor = value),
                onRememberDevice: (value) =>
                    setState(() => _rememberDevice = value),
                onTokenRefresh: (value) =>
                    setState(() => _tokenRefresh = value),
              ),
              const SizedBox(height: 16),
              _SessionPreview(
                scope: _scope,
                risk: _risk,
                twoFactor: _twoFactor,
                rememberDevice: _rememberDevice,
                tokenRefresh: _tokenRefresh,
              ),
              const SizedBox(height: 16),
              for (final flow in _visibleFlows) ...[
                _SecurityFlowCard(flow: flow, risk: _risk),
                const SizedBox(height: 12),
              ],
              if (_visibleFlows.isEmpty)
                const EmptyPanel(
                  'Keine Security-Flows für diesen Bereich sichtbar.',
                ),
              const SizedBox(height: 4),
              _Checklist(
                onOpen: () => openUiAction(
                  context,
                  title: 'Session Security Token Parity',
                  body:
                      'Session Restore, Token Refresh, 2FA, Recovery Codes, Device Sessions, API Tokens, Logout und Account-Löschung sind als mobile UI vorbereitet.',
                  status: 'Security',
                  icon: Icons.security_outlined,
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
    required this.scope,
    required this.risk,
    required this.twoFactor,
    required this.tokenRefresh,
  });

  final String scope;
  final String risk;
  final bool twoFactor;
  final bool tokenRefresh;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('SESSION SECURITY'),
          const SizedBox(height: 8),
          Text(
            'Auth ist ein mobiler Lebenszyklus, nicht nur Login.',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Flutter bereitet Session Restore, Token Refresh, 2FA, Device Sessions, API Tokens, Logout und sensible Account-Aktionen als sichere App-Flows vor.',
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
              _Metric(value: scope, label: 'Bereich'),
              _Metric(value: risk, label: 'Risiko'),
              _Metric(value: twoFactor ? '2FA' : 'Basic', label: 'Schutz'),
              _Metric(
                value: tokenRefresh ? 'Refresh' : 'Manual',
                label: 'Token',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({
    required this.title,
    required this.items,
    required this.active,
    required this.color,
    required this.onChanged,
  });

  final String title;
  final List<String> items;
  final String active;
  final Color color;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final textColor = airmiusTextColor(context);
    final mutedColor = airmiusMutedColor(context);
    final borderColor = airmiusBorderColor(context);
    final surfaceColor = airmiusSurfaceSoftColor(context);
    return AirmiusPanel(
      title: title,
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: items
              .map(
                (item) => ChoiceChip(
                  selected: active == item,
                  label: Text(item),
                  onSelected: (_) => onChanged(item),
                  selectedColor: color.withValues(alpha: .24),
                  backgroundColor: surfaceColor,
                  side: BorderSide(color: active == item ? color : borderColor),
                  labelStyle: TextStyle(
                    color: active == item ? textColor : mutedColor,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              )
              .toList(),
        ),
      ],
    );
  }
}

class _RulesPanel extends StatelessWidget {
  const _RulesPanel({
    required this.twoFactor,
    required this.rememberDevice,
    required this.tokenRefresh,
    required this.onTwoFactor,
    required this.onRememberDevice,
    required this.onTokenRefresh,
  });

  final bool twoFactor;
  final bool rememberDevice;
  final bool tokenRefresh;
  final ValueChanged<bool> onTwoFactor;
  final ValueChanged<bool> onRememberDevice;
  final ValueChanged<bool> onTokenRefresh;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Security-Regeln',
      subtitle:
          'Diese Optionen machen Auth-Zustände später mit Laravel/Sanctum nachvollziehbar.',
      children: [
        _SwitchLine(
          title: '2FA-Gate aktivieren',
          value: twoFactor,
          onChanged: onTwoFactor,
        ),
        _SwitchLine(
          title: 'Gerät merken erlauben',
          value: rememberDevice,
          onChanged: onRememberDevice,
        ),
        _SwitchLine(
          title: 'Token automatisch erneuern',
          value: tokenRefresh,
          onChanged: onTokenRefresh,
        ),
      ],
    );
  }
}

class _SessionPreview extends StatelessWidget {
  const _SessionPreview({
    required this.scope,
    required this.risk,
    required this.twoFactor,
    required this.rememberDevice,
    required this.tokenRefresh,
  });

  final String scope;
  final String risk;
  final bool twoFactor;
  final bool rememberDevice;
  final bool tokenRefresh;

  @override
  Widget build(BuildContext context) {
    final semanticColor = risk == 'Compromised'
        ? AirmiusColors.red
        : risk == 'Sensitive'
        ? AirmiusColors.amber
        : AirmiusColors.green;
    final color = airmiusSemanticColor(context, semanticColor);
    return AirmiusPanel(
      borderColor: color,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.security_outlined, color: color, size: 32),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  '$scope · $risk',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              StatusPill(risk, color: color),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            '2FA: ${twoFactor ? 'aktiv' : 'aus'} · Trusted Device: ${rememberDevice ? 'erlaubt' : 'aus'} · Token Refresh: ${tokenRefresh ? 'automatisch' : 'manuell'}',
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.4,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: 'Security prüfen',
                icon: Icons.security_outlined,
                danger: risk == 'Compromised',
                onPressed: () => openUiAction(
                  context,
                  title: 'Security Status',
                  body:
                      'Scope $scope, Risiko $risk, 2FA $twoFactor, Trusted Device $rememberDevice und Token Refresh $tokenRefresh.',
                  status: 'Security',
                  icon: Icons.security_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Audit',
                icon: Icons.history_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Security Audit',
                  body:
                      'Letzte Aktivität, Gerät, IP-Hinweis, Tokenstatus, Rollenwechsel und sensible Aktion.',
                  status: 'Audit',
                  icon: Icons.history_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _SecurityFlowCard extends StatelessWidget {
  const _SecurityFlowCard({required this.flow, required this.risk});

  final _SecurityFlow flow;
  final String risk;

  @override
  Widget build(BuildContext context) {
    final flowColor = airmiusSemanticColor(context, flow.color);
    final danger = risk == 'Compromised' || flow.color == AirmiusColors.red;
    final color = danger ? Theme.of(context).colorScheme.error : flowColor;
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
                      flow.scope,
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
                danger: danger,
                onPressed: () => openUiAction(
                  context,
                  title: flow.primary,
                  body: '${flow.title}: ${flow.body}',
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
                      'Details, Audit, API-Fehler, Retry und Permission für ${flow.title}.',
                  status: 'Security Detail',
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

class _Checklist extends StatelessWidget {
  const _Checklist({required this.onOpen});

  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Session-/Security-Parität',
      subtitle: 'Was Auth in der Mobile-App leisten muss.',
      children: [
        const _CheckLine(
          'Session Restore, Token Refresh und Rolle/Workspace werden beim App-Start sichtbar.',
        ),
        const _CheckLine(
          '2FA, Recovery Codes, Trusted Device und Rate Limit haben eigene mobile Zustände.',
        ),
        const _CheckLine(
          'Geräte, Web-Sessions, API Tokens und Logout anderer Sessions sind als UI vorbereitet.',
        ),
        const _CheckLine(
          'Konto löschen, Datenexport und sensible Aktionen brauchen Passwort/2FA, Audit und Rückweg.',
        ),
        const SizedBox(height: 12),
        AirmiusButton(
          label: 'Security-Parität markieren',
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

class _SecurityFlow {
  const _SecurityFlow({
    required this.scope,
    required this.title,
    required this.body,
    required this.status,
    required this.icon,
    required this.primary,
    required this.secondary,
    required this.color,
  });

  final String scope;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final String primary;
  final String secondary;
  final Color color;
}
