import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class BlogMediaDetailScreen extends StatefulWidget {
  const BlogMediaDetailScreen({super.key, required this.title, required this.status});

  final String title;
  final String status;

  @override
  State<BlogMediaDetailScreen> createState() => _BlogMediaDetailScreenState();
}

class _BlogMediaDetailScreenState extends State<BlogMediaDetailScreen> {
  String _visibility = 'Verein';
  String _publish = 'Entwurf';
  bool _guardianConsent = true;
  bool _imageRights = true;
  bool _comments = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Redaktion', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: 'Artikel, Medienfreigabe, Publikation und Moderation',
        trailing: StatusPill(widget.status),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
            Eyebrow('Editor'),
            SizedBox(height: 12),
            AirmiusTextField(label: 'Titel', hint: 'Vereinsnews, Eventbericht oder Medieninfo', icon: Icons.title_outlined),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Teaser', hint: 'Kurze Vorschau für Public-Seite und Feed', icon: Icons.short_text_outlined),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Inhalt', hint: 'Artikeltext, Markdown oder redaktioneller Entwurf', icon: Icons.article_outlined, maxLines: 5),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '8', label: 'Artikel')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Medien')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Freigaben'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Publikation'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              value: _publish,
              dropdownColor: AirmiusColors.card,
              decoration: _fieldDecoration('Status'),
              items: const ['Entwurf', 'Review', 'Geplant', 'Veröffentlicht'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
              onChanged: (value) => setState(() => _publish = value ?? _publish),
            ),
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [
              for (final item in const ['Privat', 'Verein', 'Öffentlich'])
                ChoiceChip(
                  selected: _visibility == item,
                  label: Text(item),
                  onSelected: (_) => setState(() => _visibility = item),
                  selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                  backgroundColor: AirmiusColors.cardSoft,
                  side: BorderSide(color: _visibility == item ? AirmiusColors.blue : AirmiusColors.border),
                  labelStyle: TextStyle(color: _visibility == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                ),
            ]),
            const SizedBox(height: 8),
            SwitchListTile(value: _comments, onChanged: (value) => setState(() => _comments = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Kommentare erlauben', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Kommentare werden moderierbar im Feed angezeigt.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Medien & Rechte'),
            const SizedBox(height: 8),
            Container(height: 150, decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(18), border: Border.all(color: AirmiusColors.border)), child: const Center(child: Icon(Icons.perm_media_outlined, color: AirmiusColors.blue, size: 42))),
            const SizedBox(height: 8),
            SwitchListTile(value: _imageRights, onChanged: (value) => setState(() => _imageRights = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Bildrechte bestätigt', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Datei wird mit Richtlinie und Autor verknuepft.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _guardianConsent, onChanged: (value) => setState(() => _guardianConsent = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Guardian Consent geprüft', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Pflicht bei Minderjaehrigen auf Foto oder Video.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.amber.withValues(alpha: 0.55), child: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Eyebrow('Review'),
            SizedBox(height: 8),
            Text('Dieser Inhalt kann vor Veröffentlichung durch Admin, Medienrichtlinie und Guardian-Regeln geprüft werden.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [StatusPill('Review offen'), StatusPill('Bildrechte OK'), StatusPill('Feed bereit')]),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Speichern', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.save_outlined)),
            AirmiusButton(label: 'Review anfragen', icon: Icons.fact_check_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Review anfragen', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.fact_check_outlined)),
          ]),
        ]),
      ),
    );
  }

  InputDecoration _fieldDecoration(String label) {
    return InputDecoration(
      labelText: label,
      labelStyle: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800),
      filled: true,
      fillColor: AirmiusColors.input,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.border)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.border)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.blue, width: 1.4)),
    );
  }
}
