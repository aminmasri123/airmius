import 'package:flutter/material.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import 'billing_detail_screen.dart';

class MemberInvoicesScreen extends StatefulWidget {
  const MemberInvoicesScreen({super.key});
  @override
  State<MemberInvoicesScreen> createState() => _MemberInvoicesScreenState();
}

class _MemberInvoicesScreenState extends State<MemberInvoicesScreen> {
  final List<AirmiusInvoice> _items = [];
  bool _loading = false;
  bool _failed = false;
  bool _initialized = false;
  int _page = 0;
  int _lastPage = 1;
  String _text(String de, String en) =>
      AirmiusScope.of(context).language == AirmiusLanguage.de ? de : en;
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_initialized) {
      _initialized = true;
      _load();
    }
  }

  Future<void> _load({bool refresh = false}) async {
    if (_loading) return;
    setState(() {
      _loading = true;
      _failed = false;
    });
    try {
      final services = AirmiusServicesScope.of(context);
      final result = await services.repositories.billing.invoices(
        page: refresh ? 1 : _page + 1,
      );
      if (!mounted) return;
      setState(() {
        if (refresh) _items.clear();
        _items.addAll(result.items);
        _page = result.currentPage;
        _lastPage = result.lastPage;
      });
    } catch (_) {
      if (mounted) setState(() => _failed = true);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(_text('Meine Rechnungen', 'My invoices'))),
    body: RefreshIndicator(
      onRefresh: () => _load(refresh: true),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        children: [
          if (_items.isEmpty && !_loading && !_failed)
            Padding(
              padding: const EdgeInsets.all(24),
              child: Text(
                _text('Noch keine Rechnungen vorhanden.', 'No invoices yet.'),
              ),
            ),
          for (final invoice in _items)
            ListTile(
              leading: const Icon(Icons.receipt_long_outlined),
              title: Text(invoice.number),
              subtitle: Text(
                '${invoice.details['club']?['name'] ?? invoice.details['title'] ?? ''}\n${invoice.details['status_label'] ?? invoice.status}',
              ),
              isThreeLine: true,
              trailing: Text(
                '${(invoice.amountCents / 100).toStringAsFixed(2)} ${invoice.currency}',
              ),
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => BillingDetailScreen(
                    title: invoice.number,
                    body: '${invoice.details['title'] ?? ''}',
                    status: invoice.status,
                    amount:
                        '${(invoice.amountCents / 100).toStringAsFixed(2)} ${invoice.currency}',
                    icon: Icons.receipt_long_outlined,
                    invoice: invoice,
                  ),
                ),
              ),
            ),
          if (_failed) ...[
            Text(
              _text(
                'Rechnungen konnten nicht geladen werden.',
                'Invoices could not be loaded.',
              ),
            ),
            TextButton(
              onPressed: () => _load(refresh: _page == 0),
              child: Text(_text('Erneut versuchen', 'Retry')),
            ),
          ],
          if (_loading) const Center(child: CircularProgressIndicator()),
          if (!_loading && !_failed && _page < _lastPage)
            TextButton(
              onPressed: _load,
              child: Text(_text('Weitere laden', 'Load more')),
            ),
        ],
      ),
    ),
  );
}
