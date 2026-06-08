import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class FacilityBookingResourceSchedulerSuiteScreen extends StatefulWidget {
  const FacilityBookingResourceSchedulerSuiteScreen({super.key});

  @override
  State<FacilityBookingResourceSchedulerSuiteScreen> createState() => _FacilityBookingResourceSchedulerSuiteScreenState();
}

class _FacilityBookingResourceSchedulerSuiteScreenState extends State<FacilityBookingResourceSchedulerSuiteScreen> {
  String _resourceType = 'Plaetze';
  bool _conflictCheck = true;
  bool _roleRules = true;
  bool _maintenanceBlocks = true;
  bool _paymentRequired = false;

  @override
  Widget build(BuildContext context) {
    final resources = _resources.where((resource) => _resourceType == 'Alle' || resource.type == _resourceType).toList();

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Ressourcen buchen', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Facility Booking Resource Scheduler',
        subtitle: 'Mobile UI fuer Plaetze, Hallen, Raeume, Geraete, Buchungen, Konflikte, Wartung und Rollenrechte.',
        trailing: const StatusPill('Scheduler', color: AirmiusColors.green),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('RESOURCE BOOKING'),
                  const SizedBox(height: 8),
                  const Text(
                    'Vereinsressourcen werden mobil planbar.',
                    style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Die App bereitet Buchungen fuer Plaetze, Hallen, Raeume, Geraete und Trainingsfenster mit Konfliktpruefung und Rollenrechten vor.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: ['Alle', 'Plaetze', 'Hallen', 'Raeume', 'Geraete', 'Training', 'Wartung'].map((item) {
                      return ChoiceChip(
                        selected: _resourceType == item,
                        label: Text(item),
                        onSelected: (_) => setState(() => _resourceType = item),
                        selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.panelSoft,
                        side: BorderSide(color: _resourceType == item ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _resourceType == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      );
                    }).toList(),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '6', label: 'Ressourcen')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '18', label: 'Slots')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '2', label: 'Konflikte')),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(children: [const Expanded(child: Eyebrow('BUCHUNGSREGELN')), StatusPill(_resourceType, color: AirmiusColors.blue)]),
                  const SizedBox(height: 12),
                  _BookingToggle(
                    icon: Icons.event_busy_outlined,
                    title: 'Konfliktpruefung',
                    body: 'Doppelte Buchungen, Teamtermine, Sperrzeiten und Trainerverfuegbarkeit werden vor dem Speichern geprueft.',
                    enabled: _conflictCheck,
                    onChanged: (value) => setState(() => _conflictCheck = value),
                  ),
                  _BookingToggle(
                    icon: Icons.admin_panel_settings_outlined,
                    title: 'Rollenrechte',
                    body: 'Mitglieder, Trainer, Vereinsadmins und Kassenwarte bekommen unterschiedliche Buchungs- und Freigaberechte.',
                    enabled: _roleRules,
                    onChanged: (value) => setState(() => _roleRules = value),
                  ),
                  _BookingToggle(
                    icon: Icons.build_outlined,
                    title: 'Wartungszeiten',
                    body: 'Plaetze, Raeume oder Geraete koennen fuer Pflege, Reparatur oder externe Nutzung blockiert werden.',
                    enabled: _maintenanceBlocks,
                    onChanged: (value) => setState(() => _maintenanceBlocks = value),
                  ),
                  _BookingToggle(
                    icon: Icons.payments_outlined,
                    title: 'Zahlungspflichtige Slots',
                    body: 'Optionale Gebuehren fuer externe Gaeste, Court-Buchungen oder Sondernutzung koennen spaeter angebunden werden.',
                    enabled: _paymentRequired,
                    onChanged: (value) => setState(() => _paymentRequired = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final resource in resources) ...[
              _ResourceCard(resource: resource),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: AirmiusColors.amber.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('KONFLIKT-VORSCHAU'),
                  const SizedBox(height: 8),
                  const Text('Court 1 ist um 18:00 bereits durch U16 Training belegt. Alternative: Court 2 um 18:30 oder Halle B um 19:00.', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, height: 1.38)),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: const [
                      StatusPill('Alternative gefunden', color: AirmiusColors.green),
                      StatusPill('Trainer-Konflikt', color: AirmiusColors.amber),
                      StatusPill('Teamtermin', color: AirmiusColors.blue),
                    ],
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(label: 'Alternative buchen', icon: Icons.event_available_outlined, onPressed: () {}),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('API BOOKING PAYLOAD'),
                  const SizedBox(height: 10),
                  const _PayloadLine(label: 'resource_type', value: 'court, hall, room, equipment, training_slot'),
                  const _PayloadLine(label: 'rules', value: 'role_scope, conflict_check, maintenance_block, payment_required'),
                  const _PayloadLine(label: 'actions', value: 'reserve, approve, cancel, reschedule, block, export'),
                  const _PayloadLine(label: 'audit', value: 'created_by, team_id, trainer_id, changed_at, conflict_reason'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Resource {
  const _Resource({required this.type, required this.title, required this.body, required this.status, required this.icon, required this.color});

  final String type;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}

const _resources = [
  _Resource(type: 'Plaetze', title: 'Court 1', body: 'Tennisplatz mit Flutlicht, heute 18:00 durch U16 Training belegt.', status: 'Belegt', icon: Icons.sports_tennis, color: AirmiusColors.amber),
  _Resource(type: 'Plaetze', title: 'Court 2', body: 'Freier Slot um 18:30, buchbar fuer Mitglieder und Trainer.', status: 'Frei', icon: Icons.sports_tennis, color: AirmiusColors.green),
  _Resource(type: 'Hallen', title: 'Halle B', body: 'Mehrzweckhalle fuer Training, Events und Vereinsversammlungen.', status: '19:00 frei', icon: Icons.location_on_outlined, color: AirmiusColors.blue),
  _Resource(type: 'Raeume', title: 'Besprechungsraum', body: 'Vorstand, Trainermeeting, Elternabend oder Sponsorentermin.', status: 'Review', icon: Icons.meeting_room_outlined, color: AirmiusColors.blue),
  _Resource(type: 'Geraete', title: 'Timing-System', body: 'Geraet fuer Wettkampf und Training, Rueckgabe mit Checkliste.', status: 'Ausgabe', icon: Icons.inventory_2_outlined, color: AirmiusColors.green),
  _Resource(type: 'Training', title: 'U16 Trainingsslot', body: 'Serientermin mit Coach, Team, Check-in und Anwesenheitsliste.', status: 'Serie', icon: Icons.event_available_outlined, color: AirmiusColors.green),
  _Resource(type: 'Wartung', title: 'Court Pflege', body: 'Blockierter Zeitraum fuer Reinigung, Reparatur oder Saisonvorbereitung.', status: 'Block', icon: Icons.build_outlined, color: AirmiusColors.amber),
];

class _ResourceCard extends StatelessWidget {
  const _ResourceCard({required this.resource});

  final _Resource resource;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: resource.color.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(color: resource.color.withValues(alpha: .14), borderRadius: BorderRadius.circular(16), border: Border.all(color: resource.color.withValues(alpha: .42))),
            child: Icon(resource.icon, color: resource.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(resource.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
                    StatusPill(resource.status, color: resource.color),
                  ],
                ),
                const SizedBox(height: 6),
                Text(resource.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 9),
                Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(resource.type, color: resource.color), const StatusPill('Bookable')]),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _BookingToggle extends StatelessWidget {
  const _BookingToggle({required this.icon, required this.title, required this.body, required this.enabled, required this.onChanged, this.last = false});

  final IconData icon;
  final String title;
  final String body;
  final bool enabled;
  final ValueChanged<bool> onChanged;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: last ? 0 : 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: enabled ? AirmiusColors.green : AirmiusColors.muted),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              ],
            ),
          ),
          Switch(value: enabled, activeThumbColor: AirmiusColors.green, onChanged: onChanged),
        ],
      ),
    );
  }
}

class _PayloadLine extends StatelessWidget {
  const _PayloadLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(width: 112, child: Text(label, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900))),
            Expanded(child: Text(value, style: const TextStyle(color: AirmiusColors.text, height: 1.35))),
          ],
        ),
      ),
    );
  }
}
