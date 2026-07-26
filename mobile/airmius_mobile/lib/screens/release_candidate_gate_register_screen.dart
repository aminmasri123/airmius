import 'package:flutter/material.dart';

import '../core/airmius_release_blockers.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ReleaseCandidateGateRegisterScreen extends StatelessWidget {
  const ReleaseCandidateGateRegisterScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text(
          'Release Candidate Gates',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: 'Release Candidate Gates',
        subtitle:
            'Harte Rest-Gates mit required evidence, Owner und aktuellem Status.',
        trailing: const StatusPill(
          'Evidence fehlt',
          color: AirmiusColors.amber,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('FINAL AUDIT'),
                  const SizedBox(height: 10),
                  const Text(
                    'Vor 100% brauchen wir Beweise, nicht nur vorbereitete Dateien.',
                    style: TextStyle(
                      color: AirmiusColors.text,
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                      height: 1.08,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Diese Ansicht trennt sauber zwischen vorbereitetem Produkt und echter Release-Freigabe: Analyze, Builds, Signing, Domain, Screenshots, API, Legal und Localization QA.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: const [
                      Expanded(
                        child: MetricCard(value: '8', label: 'Hard Gates'),
                      ),
                      SizedBox(width: 10),
                      Expanded(
                        child: MetricCard(value: '0', label: 'Bewiesen'),
                      ),
                      SizedBox(width: 10),
                      Expanded(
                        child: MetricCard(value: '8', label: 'Offen'),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final blocker in AirmiusReleaseBlockers.items) ...[
              _ReleaseBlockerCard(blocker: blocker),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: AirmiusColors.green.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('GO / NO-GO REGEL'),
                  const SizedBox(height: 8),
                  const Text(
                    'Release-ready ist die App erst, wenn alle Gates mit direkter Evidence belegt sind: Build-Logs, Artefakte, Domain-Dateien, Screenshots und QA-Freigaben.',
                    style: TextStyle(
                      color: AirmiusColors.text,
                      height: 1.38,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(
                    label: 'Finale Gates vormerken',
                    icon: Icons.fact_check_outlined,
                    onPressed: () => openUiAction(
                      context,
                      title: 'Release Candidate Gates',
                      body:
                          'Analyze, Builds, Signing, Domain Verification, Screenshots, API-QA, Legal und Localization QA müssen mit Evidence abgeschlossen werden.',
                      status: 'RC Audit',
                      icon: Icons.fact_check_outlined,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ReleaseBlockerCard extends StatelessWidget {
  const _ReleaseBlockerCard({required this.blocker});

  final AirmiusReleaseBlocker blocker;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: AirmiusColors.amber.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBadge(
            icon: Icons.lock_clock_outlined,
            color: AirmiusColors.amber,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        blocker.title,
                        style: const TextStyle(
                          color: AirmiusColors.text,
                          fontSize: 17,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    StatusPill(blocker.status, color: AirmiusColors.amber),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  'Owner: ${blocker.owner}',
                  style: const TextStyle(
                    color: AirmiusColors.blue,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  blocker.requiredEvidence,
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    height: 1.35,
                    fontWeight: FontWeight.w700,
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
