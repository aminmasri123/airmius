import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'meal_detail_screen.dart';
import 'training_center_screen.dart';
import 'ui_action_result_screen.dart';

class DailyFlowScreen extends StatefulWidget {
  const DailyFlowScreen({super.key});

  @override
  State<DailyFlowScreen> createState() => _DailyFlowScreenState();
}

class _DailyFlowScreenState extends State<DailyFlowScreen> {
  String _focus = 'Heute';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Tagesflow', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Tagesflow',
        subtitle: 'Training, Ernährung, Wasser, Route und KI-Coach für deinen Tag',
        trailing: const StatusPill('KI-Coach'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Dein Tag'),
            const SizedBox(height: 8),
            const Text('Heute ist dein Flow zu 68% komplett.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            const Text('KI-Coach: Training ist geplant. Zieh Wasser nach und erfasse später deine Mahlzeit, damit dein Tagesprofil sauber bleibt.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 14),
            ClipRRect(borderRadius: BorderRadius.circular(99), child: const LinearProgressIndicator(value: 0.68, minHeight: 10, backgroundColor: AirmiusColors.cardSoft, valueColor: AlwaysStoppedAnimation<Color>(AirmiusColors.green))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Wrap(spacing: 8, runSpacing: 8, children: ['Heute', 'Training', 'Ernährung', 'Regeneration'].map((item) {
            return ChoiceChip(
              selected: _focus == item,
              label: Text(item),
              onSelected: (_) => setState(() => _focus = item),
              selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
              backgroundColor: AirmiusColors.cardSoft,
              side: BorderSide(color: _focus == item ? AirmiusColors.blue : AirmiusColors.border),
              labelStyle: TextStyle(color: _focus == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
            );
          }).toList())),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '45', label: 'Minuten Ziel')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1840', label: 'Kalorien')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1.5L', label: 'Wasser'))]),
          const SizedBox(height: 14),
          _FlowTask(icon: Icons.event_available_outlined, title: 'Training vorbereiten', body: 'Öffne deine echten Events und Trainings aus der API.', status: 'Naechster Schritt', color: AirmiusColors.blue, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TrainingCenterScreen()))),
          const SizedBox(height: 12),
          _FlowTask(icon: Icons.water_drop_outlined, title: 'Wasser nachziehen', body: 'Noch 1.5 Liter bis zum Tagesziel. Kleine Erinnerung am Nachmittag.', status: 'Offen', color: AirmiusColors.amber, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Wasser loggen', body: 'Wassermenge für den Tagesflow erfassen und Ziel automatisch aktualisieren.', status: 'Hydration', icon: Icons.water_drop_outlined)))),
          const SizedBox(height: 12),
          _FlowTask(icon: Icons.restaurant_menu_outlined, title: 'Mahlzeit erfassen', body: 'Nach dem Training Protein und Kohlenhydrate dokumentieren.', status: 'Später', color: AirmiusColors.green, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MealDetailScreen(title: 'Mahlzeit erfassen', body: 'Nach dem Training Protein und Kohlenhydrate dokumentieren.', kcal: 'Neu', icon: Icons.restaurant_menu_outlined)))),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Schnellaktionen'),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Training starten', icon: Icons.play_arrow_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TrainingCenterScreen()))),
              AirmiusButton(label: 'Wasser loggen', icon: Icons.water_drop_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Wasser loggen', body: 'Schneller Hydration-Eintrag für den Tagesflow.', status: 'Hydration', icon: Icons.water_drop_outlined)))),
              AirmiusButton(label: 'Mahlzeit', icon: Icons.add_circle_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MealDetailScreen(title: 'Neue Mahlzeit', body: 'Mahlzeit, Makros, Barcode oder KI-Fotoanalyse erfassen.', kcal: 'Neu', icon: Icons.add_circle_outline)))),
            ]),
          ])),
        ]),
      ),
    );
  }
}

class _FlowTask extends StatelessWidget {
  const _FlowTask({required this.icon, required this.title, required this.body, required this.status, required this.color, required this.onTap});

  final IconData icon;
  final String title;
  final String body;
  final String status;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: onTap,
      borderColor: color.withValues(alpha: 0.45),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, color: color, size: 28),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)), const SizedBox(height: 10), StatusPill(status, color: color)])),
        const Icon(Icons.chevron_right, color: AirmiusColors.muted),
      ]),
    );
  }
}
