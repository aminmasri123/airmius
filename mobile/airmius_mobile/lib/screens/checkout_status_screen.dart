import 'package:flutter/material.dart';
import 'marketplace_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class CheckoutStatusScreen extends StatefulWidget {
  const CheckoutStatusScreen({
    super.key,
    required this.flow,
    required this.status,
    required this.amount,
    this.reference = 'AIR-2026-004',
  });

  final String flow;
  final String status;
  final String amount;
  final String reference;

  @override
  State<CheckoutStatusScreen> createState() => _CheckoutStatusScreenState();
}

class _CheckoutStatusScreenState extends State<CheckoutStatusScreen> {
  bool _sendReceipt = true;
  bool _notifyClub = true;

  bool get _success => widget.status == 'Success';
  bool get _cancel => widget.status == 'Cancel';
  bool get _bank => widget.status == 'Banktransfer';

  @override
  Widget build(BuildContext context) {
    final color = _success
        ? AirmiusColors.green
        : _cancel
        ? AirmiusColors.red
        : AirmiusColors.amber;
    final icon = _success
        ? Icons.check_circle_outline
        : _cancel
        ? Icons.cancel_outlined
        : Icons.account_balance_outlined;
    return Scaffold(
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xFF1D5FA8),
        foregroundColor: Colors.white,
        icon: Icon(Icons.storefront_outlined),
        label: const Text(
          'Order Ops',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        onPressed: () => Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => MarketplaceOperationsScreen(initialTab: 'Orders'),
          ),
        ),
      ),

      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          '${widget.flow} Checkout',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: _title,
        subtitle:
            'Checkout-Ergebnis, Rechnung, Banktransfer, Benachrichtigung und Rückkehr in die App',
        trailing: StatusPill(widget.status, color: color),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              borderColor: color.withValues(alpha: 0.45),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(icon, color: color, size: 46),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Eyebrow(widget.flow),
                        const SizedBox(height: 6),
                        Text(
                          widget.amount,
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontSize: 28,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          _body,
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            height: 1.35,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: MetricCard(value: widget.reference, label: 'Referenz'),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: MetricCard(
                    value: _bank ? 'IBAN' : 'PDF',
                    label: _bank ? 'Bank' : 'Rechnung',
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: widget.status, label: 'Status'),
                ),
              ],
            ),
            const SizedBox(height: 14),
            if (_bank)
              const AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Eyebrow('Banktransfer'),
                    SizedBox(height: 10),
                    _StatusLine(
                      icon: Icons.account_balance_outlined,
                      title: 'Empfänger',
                      body: 'Airmius Payments / Vereinsanbieter',
                      status: 'Bank',
                    ),
                    _StatusLine(
                      icon: Icons.numbers_outlined,
                      title: 'Verwendungszweck',
                      body: 'AIR-2026-004 bitte exakt angeben.',
                      status: 'Pflicht',
                    ),
                    _StatusLine(
                      icon: Icons.sync_alt_outlined,
                      title: 'Automatische Zuordnung',
                      body:
                          'Zahlung wird später per API oder Bankimport abgeglichen.',
                      status: 'Abgleich',
                    ),
                  ],
                ),
              )
            else
              AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Eyebrow('Nächste Schritte'),
                    const SizedBox(height: 10),
                    _StatusLine(
                      icon: _success
                          ? Icons.receipt_long_outlined
                          : Icons.refresh_outlined,
                      title: _success
                          ? 'Rechnung bereit'
                          : 'Checkout erneut starten',
                      body: _success
                          ? 'PDF, E-Mail und Bestellstatus vorbereiten.'
                          : 'Abgebrochener Checkout kann aus Warenkorb oder Plan erneut gestartet werden.',
                      status: _success ? 'PDF' : 'Retry',
                    ),
                    const _StatusLine(
                      icon: Icons.history_outlined,
                      title: 'Statushistorie',
                      body:
                          'Provider-Referenz, Zeitstempel und Audit werden später serverseitig gespeichert.',
                      status: 'Audit',
                    ),
                  ],
                ),
              ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Benachrichtigung'),
                  SwitchListTile(
                    value: _sendReceipt,
                    onChanged: (value) => setState(() => _sendReceipt = value),
                    activeThumbColor: AirmiusColors.blue,
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      'Beleg per E-Mail senden',
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    subtitle: Text(
                      'Rechnung, Bankdaten oder Abbruchinfo zustellen.',
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ),
                  SwitchListTile(
                    value: _notifyClub,
                    onChanged: (value) => setState(() => _notifyClub = value),
                    activeThumbColor: AirmiusColors.green,
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      'Verein / Anbieter informieren',
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    subtitle: Text(
                      'Bei Club-Abos, Bestellungen oder Outfit-Lieferungen relevant.',
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                AirmiusButton(
                  label: _cancel
                      ? 'Checkout erneut starten'
                      : 'Status speichern',
                  icon: _cancel
                      ? Icons.refresh_outlined
                      : Icons.check_circle_outline,
                  onPressed: () => openUiAction(
                    context,
                    title: _cancel
                        ? 'Checkout erneut starten'
                        : 'Checkoutstatus speichern',
                    body:
                        'Checkout-Status, Provider-Referenz, Rechnung und UI-Refresh vorbereiten.',
                    status: widget.status,
                    icon: _cancel
                        ? Icons.refresh_outlined
                        : Icons.check_circle_outline,
                  ),
                ),
                AirmiusButton(
                  label: 'Rechnung / Beleg',
                  icon: Icons.receipt_long_outlined,
                  secondary: true,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Rechnung / Beleg',
                    body:
                        'PDF, E-Mail-Versand, Download und Buchungsverlauf vorbereiten.',
                    status: 'PDF',
                    icon: Icons.receipt_long_outlined,
                  ),
                ),
                if (_bank)
                  AirmiusButton(
                    label: 'Bankdaten kopieren',
                    icon: Icons.content_copy_outlined,
                    secondary: true,
                    onPressed: () => openUiAction(
                      context,
                      title: 'Bankdaten kopieren',
                      body:
                          'IBAN, Betrag und Verwendungszweck für Banktransfer kopieren.',
                      status: 'Bank',
                      icon: Icons.content_copy_outlined,
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  String get _title {
    if (_success) return 'Zahlung erfolgreich';
    if (_cancel) return 'Checkout abgebrochen';
    return 'Banktransfer vorbereitet';
  }

  String get _body {
    if (_success) {
      return 'Der Checkout wurde erfolgreich abgeschlossen. Rechnung, Status und Benachrichtigungen sind als native UI vorbereitet.';
    }
    if (_cancel) {
      return 'Der Checkout wurde abgebrochen. Die App zeigt Warenkorb, Retry und Supportpfad ohne WebView-Bruch.';
    }
    return 'Banktransfer zeigt Betrag, Referenz und Abgleichstatus, passend zu den Web-App-Banktransfer-Routen.';
  }
}

class _StatusLine extends StatelessWidget {
  const _StatusLine({
    required this.icon,
    required this.title,
    required this.body,
    required this.status,
  });

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AirmiusColors.blue),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  body,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
          StatusPill(status),
        ],
      ),
    );
  }
}
