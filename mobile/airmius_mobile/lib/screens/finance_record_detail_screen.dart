import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class FinanceRecordDetailScreen extends StatefulWidget {
  const FinanceRecordDetailScreen({
    super.key,
    required this.title,
    required this.body,
    required this.trailing,
    required this.icon,
  });

  final String title;
  final String body;
  final String trailing;
  final IconData icon;

  @override
  State<FinanceRecordDetailScreen> createState() =>
      _FinanceRecordDetailScreenState();
}

class _FinanceRecordDetailScreenState extends State<FinanceRecordDetailScreen> {
  String _rhythm = 'Monatlich';
  String _method = 'Überweisung';
  bool _matched = false;
  bool _sepa = false;
  bool _datev = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: (Theme.of(context).appBarTheme.backgroundColor ?? airmiusSurfaceColor(context)),
        surfaceTintColor: Colors.transparent,
        title: Text(
          'Finanzdetail',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: widget.title,
        subtitle: widget.body,
        trailing: StatusPill(widget.trailing),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(widget.icon, color: AirmiusColors.blue, size: 34),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      widget.body,
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.35,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(
                  child: MetricCard(value: '12 EUR', label: 'Betrag'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: '06/26', label: 'Periode'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: '0', label: 'Mahn.'),
                ),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Beitrag & Zahlung'),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: _rhythm,
                    dropdownColor: airmiusSurfaceColor(context),
                    decoration: _fieldDecoration('Zahlrhythmus'),
                    items:
                        const ['Monatlich', '4 Monate', '6 Monate', 'Jährlich']
                            .map(
                              (item) => DropdownMenuItem(
                                value: item,
                                child: Text(item),
                              ),
                            )
                            .toList(),
                    onChanged: (value) =>
                        setState(() => _rhythm = value ?? _rhythm),
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    initialValue: _method,
                    dropdownColor: airmiusSurfaceColor(context),
                    decoration: _fieldDecoration('Zahlmethode'),
                    items: const ['Überweisung', 'Bar', 'SEPA', 'Extern']
                        .map(
                          (item) =>
                              DropdownMenuItem(value: item, child: Text(item)),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setState(() => _method = value ?? _method),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Abgleich & Export'),
                  const SizedBox(height: 8),
                  SwitchListTile(
                    value: _matched,
                    onChanged: (value) => setState(() => _matched = value),
                    activeThumbColor: AirmiusColors.green,
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      'Zahlung zugeordnet',
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    subtitle: Text(
                      'Banktransfer oder Barzahlung mit Rechnung verbinden.',
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ),
                  SwitchListTile(
                    value: _sepa,
                    onChanged: (value) => setState(() => _sepa = value),
                    activeThumbColor: AirmiusColors.blue,
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      'In SEPA Export aufnehmen',
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    subtitle: Text(
                      'Nur bei Mandat und fälligem Beitrag.',
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ),
                  SwitchListTile(
                    value: _datev,
                    onChanged: (value) => setState(() => _datev = value),
                    activeThumbColor: AirmiusColors.blue,
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      'DATEV relevant',
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    subtitle: Text(
                      'Buchhaltungsdaten vorbereiten.',
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: AirmiusColors.amber.withValues(alpha: 0.55),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Eyebrow('Audit & Dokumente'),
                  SizedBox(height: 8),
                  Text(
                    'Rechnung, Zahlung, SEPA-Mandat, DATEV Export und manuelle Änderungen werden später per Laravel protokolliert.',
                    style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
                  ),
                  SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      StatusPill('Audit'),
                      StatusPill('PDF'),
                      StatusPill('Export'),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusButton(
              label: 'Finanzdetail speichern',
              icon: Icons.save_outlined,
              onPressed: () => openUiAction(
                context,
                title: 'Finanzdetail speichern',
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

  InputDecoration _fieldDecoration(String label) {
    return InputDecoration(
      labelText: label,
      labelStyle: TextStyle(
        color: airmiusMutedColor(context),
        fontWeight: FontWeight.w800,
      ),
      filled: true,
      fillColor: airmiusInputColor(context),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: airmiusBorderColor(context)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: airmiusBorderColor(context)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: AirmiusColors.blue, width: 1.4),
      ),
    );
  }
}
