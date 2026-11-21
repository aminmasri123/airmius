import 'package:flutter/material.dart';
import '../screens/club_finance_workspace_screen.dart';
import 'airmius_widgets.dart';

/// Loads only the permission-scoped account overview, not budgets or invoices.
class ClubMoneyAccountsOverview extends StatefulWidget {
  const ClubMoneyAccountsOverview({
    super.key,
    required this.load,
    required this.onOpen,
  });

  final Future<Map<String, dynamic>> Function() load;
  final Future<void> Function(bool create) onOpen;

  @override
  State<ClubMoneyAccountsOverview> createState() =>
      _ClubMoneyAccountsOverviewState();
}

class _ClubMoneyAccountsOverviewState extends State<ClubMoneyAccountsOverview> {
  late Future<Map<String, dynamic>> _future;
  bool _opening = false;

  @override
  void initState() {
    super.initState();
    _future = widget.load();
  }

  Future<void> _open(bool create) async {
    if (_opening) return;
    setState(() => _opening = true);
    try {
      await widget.onOpen(create);
    } finally {
      if (mounted) {
        setState(() {
          _opening = false;
          _future = widget.load();
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    String c(String key) => financeWorkspaceLabel(context, key);
    return AirmiusPanel(
      child: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          final data = snapshot.data;
          final accounts = (data?['accounts'] as List? ?? const [])
              .whereType<Map<String, dynamic>>()
              .where((account) => account['is_active'] != false)
              .toList();
          final canCreate =
              data?['available'] == true && data?['can_manage'] == true;
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Icon(
                    Icons.account_balance_wallet_outlined,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      c('Kassen & Bankkonten'),
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                c(
                  'Barkassen und Bankkonten des Vereins oder Teams getrennt verwalten.',
                ),
              ),
              const SizedBox(height: 14),
              if (snapshot.connectionState != ConnectionState.done)
                const LinearProgressIndicator()
              else if (snapshot.hasError) ...[
                Text(c('Kassen und Bankkonten konnten nicht geladen werden.')),
                TextButton.icon(
                  onPressed: () => setState(() {
                    _future = widget.load();
                  }),
                  icon: const Icon(Icons.refresh),
                  label: Text(c('Erneut versuchen')),
                ),
              ] else if (data?['available'] != true)
                Text(c('Noch nicht verfügbar'))
              else ...[
                if (accounts.isEmpty) ...[
                  Text(
                    c('Noch keine Kasse oder kein Bankkonto angelegt.'),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 8),
                ] else ...[
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      Chip(
                        avatar: const Icon(Icons.payments_outlined, size: 18),
                        label: Text(
                          '${c('Barkassen')}: ${accounts.where((a) => a['type'] == 'cash').length}',
                        ),
                      ),
                      Chip(
                        avatar: const Icon(
                          Icons.account_balance_outlined,
                          size: 18,
                        ),
                        label: Text(
                          '${c('Bankkonten')}: ${accounts.where((a) => a['type'] == 'bank').length}',
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  for (final account in accounts)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 9),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(
                            account['type'] == 'cash'
                                ? Icons.account_balance_wallet_outlined
                                : Icons.account_balance_outlined,
                            size: 21,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${account['name']}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                Text(
                                  c(
                                    account['type'] == 'cash'
                                        ? 'Barkasse'
                                        : 'Bankkonto',
                                  ),
                                ),
                                Text(
                                  '${((account['balance_cents'] as num? ?? 0) / 100).toStringAsFixed(2)} EUR',
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
                if (canCreate) ...[
                  const SizedBox(height: 8),
                  AirmiusButton(
                    label: c('Kasse oder Bankkonto anlegen'),
                    icon: Icons.add,
                    onPressed: _opening ? null : () => _open(true),
                  ),
                ],
                const SizedBox(height: 8),
                AirmiusButton(
                  label: c('Kassen und Bankkonten verwalten'),
                  icon: Icons.arrow_forward,
                  secondary: true,
                  onPressed: _opening ? null : () => _open(false),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}
