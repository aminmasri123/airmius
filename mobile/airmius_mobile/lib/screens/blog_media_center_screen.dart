import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'blog_media_detail_screen.dart';
import 'content_operations_screen.dart';
import 'media_guidelines_screen.dart';

class BlogMediaCenterScreen extends StatefulWidget {
  const BlogMediaCenterScreen({super.key});

  @override
  State<BlogMediaCenterScreen> createState() => _BlogMediaCenterScreenState();
}

class _BlogMediaCenterScreenState extends State<BlogMediaCenterScreen> {
  String _tab = 'Artikel';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Blog & Medien', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Blog & Medien',
        subtitle: 'Artikel, Redaktion, Medienbibliothek, Freigaben und Richtlinien',
        trailing: const StatusPill('Redaktion'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Content'),
            const SizedBox(height: 8),
            const Text('Redaktion und Medien mobil verwalten.', style: TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            const Text('Blogartikel, Entwuerfe, Tags, Autoren, Medienfreigaben, Bildrechte und Richtlinien werden wie in der Web-App als Workflows vorbereitet.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: ['Artikel', 'Entwuerfe', 'Medien', 'Freigaben'].map((item) => ChoiceChip(
              selected: _tab == item,
              label: Text(item),
              onSelected: (_) => setState(() => _tab = item),
              selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
              backgroundColor: AirmiusColors.cardSoft,
              side: BorderSide(color: _tab == item ? AirmiusColors.blue : AirmiusColors.border),
              labelStyle: TextStyle(color: _tab == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
            )).toList()),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '8', label: 'Artikel')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Entwuerfe')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Freigaben'))]),
          const SizedBox(height: 14),
          _ContentLine(
            icon: Icons.article_outlined,
            title: 'Vereinsnews planen',
            body: 'Titel, Teaser, Inhalt, Tags, Autor und Veroeffentlichung.',
            status: 'Entwurf',
            color: AirmiusColors.blue,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BlogMediaDetailScreen(title: 'Vereinsnews planen', status: 'Entwurf'))),
          ),
          const SizedBox(height: 12),
          _ContentLine(
            icon: Icons.perm_media_outlined,
            title: 'Medienfreigabe',
            body: 'Fotos und Videos mit Sichtbarkeit, Bildrechten und Guardian Consent verbinden.',
            status: 'Pruefen',
            color: AirmiusColors.amber,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BlogMediaDetailScreen(title: 'Medienfreigabe', status: 'Pruefen'))),
          ),
          const SizedBox(height: 12),
          AirmiusPanel(onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MediaGuidelinesScreen())), borderColor: AirmiusColors.green.withValues(alpha: 0.45), child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(Icons.policy_outlined, color: AirmiusColors.green, size: 28),
            SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Medienrichtlinien', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Upload-Regeln, Bildrechte, Altersfreigabe und Datenschutz oeffnen.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)), SizedBox(height: 10), StatusPill('Pflicht', color: AirmiusColors.green)])),
            Icon(Icons.chevron_right, color: AirmiusColors.muted),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Redaktions-Aktionen'),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Artikel schreiben', icon: Icons.edit_note_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BlogMediaDetailScreen(title: 'Neuer Artikel', status: 'Entwurf')))),
              AirmiusButton(label: 'Medium hochladen', icon: Icons.cloud_upload_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BlogMediaDetailScreen(title: 'Medium hochladen', status: 'Upload')))),
              AirmiusButton(label: 'Freigabe pruefen', icon: Icons.fact_check_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BlogMediaDetailScreen(title: 'Freigabe pruefen', status: 'Pruefen')))),
              AirmiusButton(label: 'Content Operations', icon: Icons.tune_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ContentOperationsScreen()))),
            ]),
          ])),
        ]),
      ),
    );
  }
}

class _ContentLine extends StatelessWidget {
  const _ContentLine({required this.icon, required this.title, required this.body, required this.status, required this.color, required this.onTap});

  final IconData icon;
  final String title;
  final String body;
  final String status;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(onTap: onTap, borderColor: color.withValues(alpha: 0.45), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Icon(icon, color: color, size: 28),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)), const SizedBox(height: 10), StatusPill(status, color: color)])),
      const Icon(Icons.chevron_right, color: AirmiusColors.muted),
    ]));
  }
}
