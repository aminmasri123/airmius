import 'package:flutter/material.dart';
import 'learning_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';
import 'learning_operations_screen.dart';

class CertificateVerificationScreen extends StatefulWidget {
  const CertificateVerificationScreen({super.key, this.code = 'AIR-2026-001'});

  final String code;

  @override
  State<CertificateVerificationScreen> createState() => _CertificateVerificationScreenState();
}

class _CertificateVerificationScreenState extends State<CertificateVerificationScreen> {
  bool _valid = true;
  bool _public = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF16855E), foregroundColor: Colors.white, icon: const Icon(Icons.school_outlined), label: const Text('Cert Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => LearningOperationsScreen(initialTab: 'Zertifikate')))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Zertifikat prüfen', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Zertifikat prüfen',
        subtitle: 'Public Certificate Verify, Code, Status und Download',
        trailing: StatusPill(_valid ? 'Gültig' : 'Ungültig', color: _valid ? AirmiusColors.green : AirmiusColors.red),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Verification'),
            const SizedBox(height: 8),
            const Text('Zertifikate können öffentlich per Code geprüft und später gegen Laravel validiert werden.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            const SizedBox(height: 12),
            AirmiusTextField(label: 'Zertifikatscode', hint: widget.code, icon: Icons.verified_outlined),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '100%', label: 'Quiz')), SizedBox(width: 10), Expanded(child: MetricCard(value: '6', label: 'Lektionen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2026', label: 'Jahr'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Status'),
            SwitchListTile(value: _valid, onChanged: (value) => setState(() => _valid = value), activeColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Zertifikat gültig', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Zeigt den späteren API-Erfolgs- oder Fehlerzustand.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _public, onChanged: (value) => setState(() => _public = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Öffentlich verifizierbar', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Kann von Vereinen, Arbeitgebern oder Kursanbietern geprüft werden.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Zertifikatsdaten'),
            SizedBox(height: 10),
            _CertificateLine(icon: Icons.person_outline, title: 'ZBB Konto', body: 'Teilnehmername und Profilbezug.', status: 'User'),
            _CertificateLine(icon: Icons.school_outlined, title: 'Datenschutz im Sportverein', body: 'Kurs, Abschlussdatum, Prüfstatus und Aussteller.', status: 'Kurs'),
            _CertificateLine(icon: Icons.workspace_premium_outlined, title: 'Airmius Learning', body: 'Aussteller, Signatur und Audit-Hinweis.', status: 'Issuer'),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Code prüfen', icon: Icons.fact_check_outlined, onPressed: () => openUiAction(context, title: 'Zertifikatscode prüfen', body: 'Code gegen Public-Learning-API validieren und Ergebnis anzeigen.', status: 'Verify', icon: Icons.fact_check_outlined)),
            AirmiusButton(label: 'PDF anzeigen', icon: Icons.picture_as_pdf_outlined, secondary: true, onPressed: _valid ? () => openUiAction(context, title: 'Zertifikat anzeigen', body: 'Zertifikat als PDF anzeigen, teilen oder herunterladen.', status: 'PDF', icon: Icons.picture_as_pdf_outlined) : null),
            AirmiusButton(label: 'Melden', icon: Icons.report_outlined, danger: true, onPressed: () => openUiAction(context, title: 'Zertifikat melden', body: 'Unstimmigkeit melden, Review starten und Supportfall vorbereiten.', status: 'Review', icon: Icons.report_outlined)),
          ]),
        ]),
      ),
    );
  }
}

class _CertificateLine extends StatelessWidget {
  const _CertificateLine({required this.icon, required this.title, required this.body, required this.status});

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(padding: const EdgeInsets.only(top: 12), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, color: AirmiusColors.blue), const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])), StatusPill(status)]));
  }
}

