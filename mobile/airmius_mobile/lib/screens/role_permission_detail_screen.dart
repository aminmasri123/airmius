import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class RolePermissionDetailScreen extends StatefulWidget {
  const RolePermissionDetailScreen({
    super.key,
    required this.title,
    required this.status,
  });

  final String title;
  final String status;

  @override
  State<RolePermissionDetailScreen> createState() =>
      _RolePermissionDetailScreenState();
}

class _RolePermissionDetailScreenState
    extends State<RolePermissionDetailScreen> {
  bool _members = true;
  bool _finance = false;
  bool _media = true;
  bool _guardian = false;
  bool _auditRequired = true;
  bool _twoFactor = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: (Theme.of(context).appBarTheme.backgroundColor ?? airmiusSurfaceColor(context)),
        surfaceTintColor: Colors.transparent,
        title: Text(
          'Rolle',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: widget.title,
        subtitle: 'Permission-Matrix, Security Gates und Audit',
        trailing: StatusPill(widget.status),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('Rollenprofil'),
                  SizedBox(height: 8),
                  AirmiusTextField(
                    label: 'Rollenname',
                    hint: 'z. B. Coach, Vereinsadmin, Guardian',
                    icon: Icons.admin_panel_settings_outlined,
                  ),
                  SizedBox(height: 10),
                  AirmiusTextField(
                    label: 'Beschreibung',
                    hint: 'Was darf diese Rolle?',
                    icon: Icons.notes_outlined,
                    maxLines: 3,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(
                  child: MetricCard(value: '42', label: 'Rechte'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: '6', label: 'Gates'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: '3', label: 'Audits'),
                ),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Permission-Matrix'),
                  const SizedBox(height: 8),
                  _PermissionSwitch(
                    title: 'Mitglieder verwalten',
                    body: 'Anfragen, Kader, Rollen und Mitgliedsdaten.',
                    value: _members,
                    onChanged: (value) => setState(() => _members = value),
                  ),
                  _PermissionSwitch(
                    title: 'Finanzen sehen',
                    body: 'Beiträge, Rechnungen, SEPA und DATEV.',
                    value: _finance,
                    onChanged: (value) => setState(() => _finance = value),
                  ),
                  _PermissionSwitch(
                    title: 'Medien freigeben',
                    body: 'Uploads, Bildrechte und Medienrichtlinien.',
                    value: _media,
                    onChanged: (value) => setState(() => _media = value),
                  ),
                  _PermissionSwitch(
                    title: 'Jugendschutz entscheiden',
                    body: 'Guardian Consent und Altersfreigaben.',
                    value: _guardian,
                    onChanged: (value) => setState(() => _guardian = value),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: AirmiusColors.amber.withValues(alpha: 0.55),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Security Gates'),
                  const SizedBox(height: 8),
                  SwitchListTile(
                    value: _auditRequired,
                    onChanged: (value) =>
                        setState(() => _auditRequired = value),
                    activeThumbColor: AirmiusColors.blue,
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      'Auditpflicht bei sensiblen Aktionen',
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    subtitle: Text(
                      'Alle Änderungen werden protokolliert.',
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ),
                  SwitchListTile(
                    value: _twoFactor,
                    onChanged: (value) => setState(() => _twoFactor = value),
                    activeThumbColor: AirmiusColors.blue,
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      '2FA für Rolle verlangen',
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    subtitle: Text(
                      'Empfohlen für Admin, Finanzen und Jugendschutz.',
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('Audit-Historie'),
                  SizedBox(height: 12),
                  _AuditRow(
                    title: 'Finanzrecht deaktiviert',
                    body: 'Heute 09:21 - verein airmius',
                  ),
                  SizedBox(height: 10),
                  _AuditRow(
                    title: 'Medienfreigabe aktiviert',
                    body: 'Gestern 18:02 - Admin',
                  ),
                  SizedBox(height: 10),
                  _AuditRow(
                    title: '2FA Gate bestätigt',
                    body: 'Vor 3 Tagen - System',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusButton(
              label: 'Rolle speichern',
              icon: Icons.save_outlined,
              onPressed: () => openUiAction(
                context,
                title: 'Rolle speichern',
                body:
                    'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.',
                status: 'UI bereit',
                icon: Icons.save_outlined,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PermissionSwitch extends StatelessWidget {
  const _PermissionSwitch({
    required this.title,
    required this.body,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final String body;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile(
      value: value,
      onChanged: onChanged,
      activeThumbColor: AirmiusColors.blue,
      contentPadding: EdgeInsets.zero,
      title: Text(
        title,
        style: TextStyle(
          color: airmiusTextColor(context),
          fontWeight: FontWeight.w900,
        ),
      ),
      subtitle: Text(body, style: TextStyle(color: airmiusMutedColor(context))),
    );
  }
}

class _AuditRow extends StatelessWidget {
  const _AuditRow({required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        children: [
          Icon(Icons.history_outlined, color: AirmiusColors.blue),
          const SizedBox(width: 10),
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
                Text(body, style: TextStyle(color: airmiusMutedColor(context))),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
