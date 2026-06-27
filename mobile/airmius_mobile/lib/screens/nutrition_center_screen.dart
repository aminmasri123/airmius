import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'meal_detail_screen.dart';
import 'wellbeing_operations_screen.dart';

class NutritionCenterScreen extends StatefulWidget {
  const NutritionCenterScreen({super.key});

  @override
  State<NutritionCenterScreen> createState() => _NutritionCenterScreenState();
}

class _NutritionCenterScreenState extends State<NutritionCenterScreen> {
  String _day = 'Heute';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Ernährung', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Ernährung',
        subtitle: 'Kalorien, Makros, Wasser, Barcode und KI-Mahlzeitenanalyse',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Tagesziel'),
                  const SizedBox(height: 8),
                  const Text('1840 von 2500 kcal erfasst. Protein und Wasser sind noch offen.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final day in const ['Heute', 'Gestern', 'Woche'])
                        ChoiceChip(
                          selected: _day == day,
                          label: Text(day),
                          onSelected: (_) => setState(() => _day = day),
                          selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(color: _day == day ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: _day == day ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: '1840', label: 'kcal')), SizedBox(width: 10), Expanded(child: MetricCard(value: '112g', label: 'Protein')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1.5L', label: 'Wasser'))]),
            const SizedBox(height: 14),
            const _MacroPanel(),
            const SizedBox(height: 14),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Mahlzeit erfassen', icon: Icons.add_circle_outline, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MealDetailScreen(title: 'Neue Mahlzeit', body: 'Lebensmittel suchen, Portion setzen und Makros speichern.', kcal: '0 kcal', icon: Icons.restaurant_menu_outlined)))),
              AirmiusButton(label: 'Barcode suchen', icon: Icons.qr_code_scanner_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MealDetailScreen(title: 'Barcode-Suche', body: 'Produktdaten, Portion und Makros aus Barcode vorbereiten.', kcal: 'Scan', icon: Icons.qr_code_scanner_outlined, mode: 'barcode')))),
              AirmiusButton(label: 'Foto analysieren', icon: Icons.camera_alt_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MealDetailScreen(title: 'KI-Fotoanalyse', body: 'Mahlzeit per Foto schaetzen, korrigieren und speichern.', kcal: 'KI', icon: Icons.camera_alt_outlined, mode: 'photo')))),
              AirmiusButton(label: 'Wellbeing Ops', icon: Icons.tune_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WellbeingOperationsScreen()))),
            ]),
            const SizedBox(height: 14),
            for (final meal in _meals) ...[
              _MealCard(meal: meal),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

class _MacroPanel extends StatelessWidget {
  const _MacroPanel();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: const [
          Eyebrow('Makros'),
          SizedBox(height: 10),
          _MacroLine(title: 'Protein', value: 0.70, label: '112 / 160g'),
          SizedBox(height: 12),
          _MacroLine(title: 'Kohlenhydrate', value: 0.58, label: '210 / 360g'),
          SizedBox(height: 12),
          _MacroLine(title: 'Fett', value: 0.45, label: '42 / 95g'),
        ],
      ),
    );
  }
}

class _MacroLine extends StatelessWidget {
  const _MacroLine({required this.title, required this.value, required this.label});

  final String title;
  final double value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(children: [Expanded(child: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))), Text(label, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12))]),
        const SizedBox(height: 7),
        ClipRRect(borderRadius: BorderRadius.circular(99), child: LinearProgressIndicator(value: value, minHeight: 8, backgroundColor: AirmiusColors.cardSoft, valueColor: const AlwaysStoppedAnimation<Color>(AirmiusColors.green))),
      ],
    );
  }
}

class _MealCard extends StatelessWidget {
  const _MealCard({required this.meal});

  final _Meal meal;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MealDetailScreen(title: meal.title, body: meal.body, kcal: meal.kcal, icon: meal.icon))),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(meal.icon, color: AirmiusColors.blue),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(meal.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(meal.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)), const SizedBox(height: 8), StatusPill(meal.kcal, color: AirmiusColors.green)])),
        const Icon(Icons.chevron_right, color: AirmiusColors.muted),
      ]),
    );
  }
}

class _Meal {
  const _Meal({required this.title, required this.body, required this.kcal, required this.icon});

  final String title;
  final String body;
  final String kcal;
  final IconData icon;
}

const _meals = [
  _Meal(title: 'Fruehstueck', body: 'Haferflocken, Banane, Protein', kcal: '520 kcal', icon: Icons.breakfast_dining_outlined),
  _Meal(title: 'Mittagessen', body: 'Reis, Gemuese, Haehnchen', kcal: '760 kcal', icon: Icons.lunch_dining_outlined),
  _Meal(title: 'Snack', body: 'Joghurt und Beeren', kcal: '220 kcal', icon: Icons.cookie_outlined),
  _Meal(title: 'Wasser', body: '3 Eintraege - Ziel 3 Liter', kcal: '1.5L', icon: Icons.water_drop_outlined),
];
