import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class PublicBlogReaderScreen extends StatefulWidget {
  const PublicBlogReaderScreen({super.key, this.initialCategory = 'Alle'});

  final String initialCategory;

  @override
  State<PublicBlogReaderScreen> createState() => _PublicBlogReaderScreenState();
}

class _PublicBlogReaderScreenState extends State<PublicBlogReaderScreen> {
  late String _category = widget.initialCategory;
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final normalized = _query.trim().toLowerCase();
    final articles = _articles.where((article) {
      final categoryMatch = _category == 'Alle' || article.category == _category;
      final queryMatch = normalized.isEmpty || article.title.toLowerCase().contains(normalized) || article.body.toLowerCase().contains(normalized);
      return categoryMatch && queryMatch;
    }).toList();

    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Blog & Public Content', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Blog & Medien',
        subtitle: 'Oeffentliche Artikel, Kategorien, RSS, Suche und Meldungen',
        trailing: const StatusPill('Public'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Public Blog'),
            const SizedBox(height: 8),
            const Text('Die Web-App bietet Blogliste, Kategorien und RSS. Die Mobile-App bildet daraus einen nativen Reader mit Suche, Teilen und Melden.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            const SizedBox(height: 14),
            SearchBox(hint: 'Artikel, Kategorie oder Thema suchen', onChanged: (value) => setState(() => _query = value)),
          ])),
          const SizedBox(height: 14),
          Row(children: const [
            Expanded(child: MetricCard(value: '8', label: 'Artikel')),
            SizedBox(width: 10),
            Expanded(child: MetricCard(value: '4', label: 'Kategorien')),
            SizedBox(width: 10),
            Expanded(child: MetricCard(value: 'RSS', label: 'Feed')),
          ]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Wrap(spacing: 8, runSpacing: 8, children: [
            for (final category in const ['Alle', 'Vereine', 'Training', 'Datenschutz', 'Commerce'])
              ChoiceChip(
                selected: _category == category,
                label: Text(category),
                onSelected: (_) => setState(() => _category = category),
                selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                backgroundColor: AirmiusColors.cardSoft,
                side: BorderSide(color: _category == category ? AirmiusColors.blue : AirmiusColors.border),
                labelStyle: TextStyle(color: _category == category ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
              ),
          ])),
          const SizedBox(height: 14),
          for (final article in articles) ...[
            _PublicArticleCard(article: article),
            const SizedBox(height: 12),
          ],
          if (articles.isEmpty) const AirmiusPanel(child: Padding(padding: EdgeInsets.all(18), child: Center(child: Text('Keine Artikel gefunden.', style: TextStyle(color: AirmiusColors.muted))))),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.blue.withValues(alpha: 0.45), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('RSS & Kategorien'),
            const SizedBox(height: 8),
            const Text('RSS, Kategorie-Feeds und Public-Content-Links werden spaeter ueber Laravel geladen. Die App zeigt bereits den nativen Einstieg.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'RSS oeffnen', icon: Icons.rss_feed_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'RSS oeffnen', body: 'Public RSS Feed laden, abonnieren oder extern teilen.', status: 'RSS', icon: Icons.rss_feed_outlined)),
              AirmiusButton(label: 'Kategorie teilen', icon: Icons.ios_share_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Kategorie teilen', body: 'Kategorie-Link, Vorschau und Share Sheet vorbereiten.', status: _category, icon: Icons.ios_share_outlined)),
            ]),
          ])),
        ]),
      ),
    );
  }
}

class _PublicArticleCard extends StatelessWidget {
  const _PublicArticleCard({required this.article});

  final _PublicArticle article;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Container(width: 54, height: 54, decoration: BoxDecoration(color: AirmiusColors.blue.withValues(alpha: 0.13), borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)), child: Icon(article.icon, color: AirmiusColors.blue)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(article.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 17)),
            const SizedBox(height: 4),
            Text(article.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
            const SizedBox(height: 8),
            Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(article.category), StatusPill(article.meta)]),
          ])),
        ]),
        const SizedBox(height: 12),
        Wrap(spacing: 10, runSpacing: 10, children: [
          AirmiusButton(label: 'Lesen', icon: Icons.article_outlined, onPressed: () => openUiAction(context, title: article.title, body: 'Artikelinhalt, Autor, Kategorie, Lesezeit und Medien als nativen Public-Reader laden.', status: article.category, icon: Icons.article_outlined)),
          AirmiusButton(label: 'Teilen', icon: Icons.share_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Artikel teilen', body: '${article.title} mit Vorschau, Deep Link und Datenschutz-Hinweis teilen.', status: 'Share', icon: Icons.share_outlined)),
          AirmiusButton(label: 'Melden', icon: Icons.report_outlined, danger: true, onPressed: () => openUiAction(context, title: 'Artikel melden', body: 'Public-Content-Meldung, Grund, Kontakt und Moderationsreview vorbereiten.', status: 'Report', icon: Icons.report_outlined)),
        ]),
      ]),
    );
  }
}

class _PublicArticle {
  const _PublicArticle({required this.title, required this.body, required this.category, required this.meta, required this.icon});

  final String title;
  final String body;
  final String category;
  final String meta;
  final IconData icon;
}

const _articles = [
  _PublicArticle(title: 'Digitale Vereinsverwaltung starten', body: 'Mitglieder, Rollen, Dokumente und Beitraege in einem mobilen Prozess.', category: 'Vereine', meta: '4 min', icon: Icons.apartment_outlined),
  _PublicArticle(title: 'Datenschutz im Sportverein', body: 'Einwilligungen, Minderjaehrige, Medien und Dokumentversionen sauber fuehren.', category: 'Datenschutz', meta: '6 min', icon: Icons.privacy_tip_outlined),
  _PublicArticle(title: 'Training sichtbar planen', body: 'Events, Training, Feedback, Fahrgemeinschaften und Tagesflow verbinden.', category: 'Training', meta: '5 min', icon: Icons.event_available_outlined),
  _PublicArticle(title: 'Marketplace fuer Vereine', body: 'Produkte, Anbieter, Retouren, Kampagnen und Payouts mobil vorbereiten.', category: 'Commerce', meta: '7 min', icon: Icons.storefront_outlined),
];
