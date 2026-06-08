import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ContentPublishingCmsSuiteScreen extends StatefulWidget {
  const ContentPublishingCmsSuiteScreen({super.key});

  @override
  State<ContentPublishingCmsSuiteScreen> createState() => _ContentPublishingCmsSuiteScreenState();
}

class _ContentPublishingCmsSuiteScreenState extends State<ContentPublishingCmsSuiteScreen> {
  String channel = 'Vereinsnews';
  bool requireApproval = true;
  bool schedulePublishing = true;
  bool sendNewsletter = true;
  bool sponsorPlacement = false;

  @override
  Widget build(BuildContext context) {
    final articles = [
      const _ContentRow(
        title: 'Neue Saison startet',
        status: 'Geplant',
        body: 'Vereinsnews mit Titelbild, Ausspielung im Clubprofil, Feed-Hinweis und Push-Benachrichtigung.',
        icon: Icons.article_outlined,
        color: AirmiusColors.blue,
      ),
      const _ContentRow(
        title: 'Sponsor des Monats',
        status: 'Freigabe',
        body: 'Gesponserter Inhalt mit Creative Review, Zielgruppe, Laufzeit und Moderationsstatus.',
        icon: Icons.campaign_outlined,
        color: AirmiusColors.amber,
      ),
      const _ContentRow(
        title: 'Newsletter Juni',
        status: 'Entwurf',
        body: 'Newsletter mit Vereinsupdates, Events, Marketplace-Angeboten und Kurs-Hinweisen.',
        icon: Icons.mail_outline,
        color: AirmiusColors.green,
      ),
      const _ContentRow(
        title: 'Oeffentlicher Blogartikel',
        status: 'Public',
        body: 'Gastseiten-Inhalt mit SEO, Autor, Kategorie, Sichtbarkeit und Vorschau.',
        icon: Icons.public_outlined,
        color: AirmiusColors.pink,
      ),
    ];

    return PageFrame(
      title: 'Content Publishing',
      subtitle: 'Blog, Vereinsnews und Newsletter',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('CMS FLOW'),
                const SizedBox(height: 8),
                const Text(
                  'Vereine und Plattformadmins brauchen eine mobile Redaktionsstrecke fuer Blog, Vereinsnews, Newsletter, Sponsorinhalte, Vorschau und Freigaben.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '4', label: 'Inhalte'),
                    Metric(value: 'Draft', label: 'Status'),
                    Metric(value: 'Push', label: 'Ausspielung'),
                    Metric(value: 'SEO', label: 'Public'),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('KANAL'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Blog', label: Text('Blog')),
                    ButtonSegment(value: 'Vereinsnews', label: Text('Verein')),
                    ButtonSegment(value: 'Newsletter', label: Text('Mail')),
                    ButtonSegment(value: 'Sponsor', label: Text('Sponsor')),
                  ],
                  selected: {channel},
                  onSelectionChanged: (value) => setState(() => channel = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('REGELN'),
                const SizedBox(height: 8),
                _ContentSwitch(title: 'Freigabe erforderlich', value: requireApproval, color: AirmiusColors.amber, onChanged: (value) => setState(() => requireApproval = value)),
                _ContentSwitch(title: 'Verarbeitung planen', value: schedulePublishing, color: AirmiusColors.blue, onChanged: (value) => setState(() => schedulePublishing = value)),
                _ContentSwitch(title: 'Newsletter senden', value: sendNewsletter, color: AirmiusColors.green, onChanged: (value) => setState(() => sendNewsletter = value)),
                _ContentSwitch(title: 'Sponsorplatzierung', value: sponsorPlacement, color: AirmiusColors.pink, onChanged: (value) => setState(() => sponsorPlacement = value)),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final article in articles) ...[
            _ContentCard(article: article),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('VORSCHAU'),
                const SizedBox(height: 8),
                Text(
                  'Aktueller Kanal: $channel. Spaeter verbindet die API Entwurf, Vorschau, SEO, Medien, Freigabe, Newsletter, Push und Ausspielungsstatus.',
                  style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'Inhalt vorbereiten',
                  icon: Icons.edit_note_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Inhalt vorbereiten',
                    body: 'Diese UI bereitet CMS-Entwuerfe, Vorschau, Medien, Freigaben, Newsletter und oeffentliche Ausspielung fuer die spaetere Laravel-API vor.',
                    status: 'UI vorbereitet',
                    icon: Icons.edit_note_outlined,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ContentRow {
  const _ContentRow({
    required this.title,
    required this.status,
    required this.body,
    required this.icon,
    required this.color,
  });

  final String title;
  final String status;
  final String body;
  final IconData icon;
  final Color color;
}

class _ContentSwitch extends StatelessWidget {
  const _ContentSwitch({
    required this.title,
    required this.value,
    required this.color,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final Color color;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      contentPadding: EdgeInsets.zero,
      title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
      value: value,
      activeColor: color,
      onChanged: onChanged,
    );
  }
}

class _ContentCard extends StatelessWidget {
  const _ContentCard({required this.article});

  final _ContentRow article;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(icon: article.icon, color: article.color),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(child: Text(article.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                        StatusPill(article.status, color: article.color),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(article.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              AirmiusButton(
                label: 'Vorschau',
                icon: Icons.preview_outlined,
                onPressed: () => openUiAction(
                  context,
                  title: 'Vorschau',
                  body: 'Vorschau, Medien, SEO, Zielgruppe und Ausspielungsstatus werden fuer die spaetere API vorbereitet.',
                  status: 'UI vorbereitet',
                  icon: Icons.preview_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Freigabe',
                icon: Icons.verified_user_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Freigabe',
                  body: 'Freigaben koennen spaeter Verein, Plattformadmin, Sponsorreview und Audit-Verlauf verbinden.',
                  status: 'UI vorbereitet',
                  icon: Icons.verified_user_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Senden',
                icon: Icons.send_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Inhalt senden',
                  body: 'Newsletter, Push, Feed-Hinweis und oeffentliche Veroeffentlichung werden als Publishing-Flow vorbereitet.',
                  status: 'UI vorbereitet',
                  icon: Icons.send_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
