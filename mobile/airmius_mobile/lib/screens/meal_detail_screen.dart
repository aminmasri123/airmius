import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class MealDetailScreen extends StatefulWidget {
  const MealDetailScreen({super.key, required this.title, required this.body, required this.kcal, required this.icon, this.mode = 'meal'});

  final String title;
  final String body;
  final String kcal;
  final IconData icon;
  final String mode;

  @override
  State<MealDetailScreen> createState() => _MealDetailScreenState();
}

class _MealDetailScreenState extends State<MealDetailScreen> {
  String _portion = 'Normal';
  bool _saveTemplate = false;

  @override
  Widget build(BuildContext context) {
    final isPhoto = widget.mode == 'photo';
    final isBarcode = widget.mode == 'barcode';
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: isPhoto ? 'KI-Fotoanalyse, Schaetzung, Korrektur und Speichern' : isBarcode ? 'Barcode-Suche, Produktdaten, Portion und Makros' : 'Mahlzeit, Makros, Portion, Wasser und Tagesziel',
        trailing: StatusPill(widget.kcal, color: AirmiusColors.green),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Container(height: 190, decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(18), border: Border.all(color: AirmiusColors.border)), child: Icon(widget.icon, color: AirmiusColors.blue, size: 72)),
            const SizedBox(height: 12),
            Text(widget.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 12),
            Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(widget.kcal, color: AirmiusColors.green), StatusPill(isPhoto ? 'KI Analyse' : isBarcode ? 'Barcode' : 'Mahlzeit'), const StatusPill('Heute')]),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '38g', label: 'Protein')), SizedBox(width: 10), Expanded(child: MetricCard(value: '72g', label: 'Carbs')), SizedBox(width: 10), Expanded(child: MetricCard(value: '18g', label: 'Fett'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Portion & Korrektur'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(value: _portion, dropdownColor: AirmiusColors.cardSoft, decoration: const InputDecoration(labelText: 'Portion'), items: const ['Klein', 'Normal', 'Gross', 'Eigene Menge'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(), onChanged: (value) => setState(() => _portion = value ?? _portion)),
            const SizedBox(height: 12),
            const AirmiusTextField(label: 'Notiz oder Korrektur', hint: 'z.B. ohne Sauce, mehr Reis, weniger Oel...'),
            SwitchListTile(value: _saveTemplate, onChanged: (value) => setState(() => _saveTemplate = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Als Vorlage speichern', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Spaeter schneller erfassen.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Tagesziel Wirkung'),
            SizedBox(height: 10),
            _MealImpactLine(title: 'Kalorien', value: 0.74, label: '1840 / 2500 kcal'),
            SizedBox(height: 12),
            _MealImpactLine(title: 'Protein', value: 0.70, label: '112 / 160g'),
            SizedBox(height: 12),
            _MealImpactLine(title: 'Wasser', value: 0.50, label: '1.5 / 3.0L'),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Speichern', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.save_outlined)),
            AirmiusButton(label: isPhoto ? 'Neu analysieren' : isBarcode ? 'Barcode erneut' : 'Loeschen', icon: isPhoto ? Icons.camera_alt_outlined : isBarcode ? Icons.qr_code_scanner_outlined : Icons.delete_outline, secondary: true, onPressed: () => openUiAction(context, title: isPhoto ? 'Neu analysieren' : isBarcode ? 'Barcode erneut' : 'Loeschen', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: isPhoto ? Icons.camera_alt_outlined : isBarcode ? Icons.qr_code_scanner_outlined : Icons.delete_outline)),
          ]),
        ]),
      ),
    );
  }
}

class _MealImpactLine extends StatelessWidget {
  const _MealImpactLine({required this.title, required this.value, required this.label});

  final String title;
  final double value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Row(children: [Expanded(child: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))), Text(label, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12))]),
      const SizedBox(height: 7),
      ClipRRect(borderRadius: BorderRadius.circular(99), child: LinearProgressIndicator(value: value, minHeight: 8, backgroundColor: AirmiusColors.cardSoft, valueColor: const AlwaysStoppedAnimation<Color>(AirmiusColors.green))),
    ]);
  }
}
