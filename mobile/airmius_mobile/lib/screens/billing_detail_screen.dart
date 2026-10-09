import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class BillingDetailScreen extends StatefulWidget {
  const BillingDetailScreen({
    super.key,
    required this.title,
    required this.body,
    required this.status,
    required this.amount,
    required this.icon,
    this.invoice,
  });
  final String title;
  final String body;
  final String status;
  final String amount;
  final IconData icon;
  final AirmiusInvoice? invoice;
  @override
  State<BillingDetailScreen> createState() => _BillingDetailScreenState();
}

class _BillingDetailScreenState extends State<BillingDetailScreen> {
  bool _busy = false;
  bool _sending = false;
  String? _feedback;
  bool _error = false;
  final _message = TextEditingController();
  String _text(String de, String en) =>
      AirmiusScope.of(context).language == AirmiusLanguage.de ? de : en;
  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  String _date(dynamic raw) {
    final date = DateTime.tryParse('$raw');
    if (date == null) return '-';
    return '${date.day.toString().padLeft(2, '0')}.${date.month.toString().padLeft(2, '0')}.${date.year}';
  }

  Future<void> _download() async {
    final invoice = widget.invoice;
    if (invoice == null || _busy) return;
    setState(() {
      _busy = true;
      _feedback = null;
    });
    try {
      final services = AirmiusServicesScope.of(context);
      final uri = Uri.parse(services.environment.apiBaseUrl).resolve(
        '/api/v1/billing/invoices/${invoice.kind}/${invoice.id}/download',
      );
      final response = await http
          .get(
            uri,
            headers: {
              'Accept': 'application/pdf',
              if (services.authState.session != null)
                'Authorization': 'Bearer ${services.authState.session!.token}',
            },
          )
          .timeout(services.environment.requestTimeout);
      if (response.statusCode != 200 ||
          !(response.headers['content-type'] ?? '').contains(
            'application/pdf',
          )) {
        throw Exception('PDF download failed');
      }
      final saved = await FilePicker.platform.saveFile(
        dialogTitle: _text('Rechnung speichern', 'Save invoice'),
        fileName: 'invoice-${invoice.kind}-${invoice.id}.pdf',
        type: FileType.custom,
        allowedExtensions: ['pdf'],
        bytes: response.bodyBytes,
      );
      if (mounted && saved != null) {
        setState(() {
          _error = false;
          _feedback = _text('Rechnung gespeichert.', 'Invoice saved.');
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = true;
          _feedback = _text(
            'Die Rechnung konnte nicht geladen werden. Bitte erneut versuchen.',
            'The invoice could not be downloaded. Please try again.',
          );
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _send() async {
    if (_sending || widget.invoice == null) return;
    if (_message.text.trim().length < 5) {
      setState(() {
        _error = true;
        _feedback = _text(
          'Bitte deine Rückfrage beschreiben.',
          'Please describe your question.',
        );
      });
      return;
    }
    setState(() => _sending = true);
    try {
      final services = AirmiusServicesScope.of(context);
      await services
          .clientForSession(services.authState.session)
          .invoiceQuestion(widget.invoice!.id, _message.text.trim());
      _message.clear();
      if (mounted) {
        setState(() {
          _error = false;
          _feedback = _text(
            'Nachricht an den Verein gesendet.',
            'Message sent to the club.',
          );
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = true;
          _feedback = _text(
            'Nachricht konnte nicht gesendet werden. Bitte erneut versuchen.',
            'The message could not be sent. Please try again.',
          );
        });
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final invoice = widget.invoice;
    final details = invoice?.details ?? const <String, dynamic>{};
    final club = details['club'];
    final isPaid = widget.status.toLowerCase() == 'paid';
    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              widget.amount,
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            const SizedBox(height: 8),
            Text(
              '${details['status_label'] ?? widget.status}',
              style: TextStyle(
                color: isPaid ? AirmiusColors.green : AirmiusColors.amber,
              ),
            ),
            const SizedBox(height: 20),
            if (club is JsonMap)
              _row(_text('Verein', 'Club'), '${club['name'] ?? '-'}'),
            _row(_text('Rechnungsnummer', 'Invoice number'), widget.title),
            if (details['title'] != null)
              _row(_text('Bezeichnung', 'Title'), '${details['title']}'),
            _row(
              _text('Ausgestellt am', 'Issued on'),
              _date(details['issued_at'] ?? details['created_at']),
            ),
            _row(_text('Fällig am', 'Due on'), _date(details['due_at'])),
            if (details['billing_period_start'] != null ||
                details['billing_period_end'] != null)
              _row(
                _text('Beitragszeitraum', 'Contribution period'),
                '${_date(details['billing_period_start'])} - ${_date(details['billing_period_end'])}',
              ),
            if (details['received_amount'] != null)
              _row(
                _text('Erhalten', 'Received'),
                '${details['received_amount']} ${invoice?.currency}',
              ),
            if (details['outstanding_amount'] != null)
              _row(
                _text('Restbetrag', 'Outstanding'),
                '${details['outstanding_amount']} ${invoice?.currency}',
              ),
            const SizedBox(height: 20),
            AirmiusButton(
              label: _busy
                  ? _text('Wird geladen…', 'Downloading…')
                  : _text('Rechnung herunterladen', 'Download invoice'),
              icon: Icons.download_outlined,
              onPressed: invoice == null || _busy ? null : _download,
            ),
            if (invoice?.kind == 'club_invoice') ...[
              const SizedBox(height: 24),
              Text(
                _text(
                  'Verein schreiben / Fehler melden',
                  'Contact club / report an error',
                ),
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _message,
                minLines: 3,
                maxLines: 6,
                maxLength: 2000,
                decoration: InputDecoration(
                  labelText: _text('Nachricht', 'Message'),
                ),
              ),
              const SizedBox(height: 10),
              AirmiusButton(
                label: _text('Nachricht senden', 'Send message'),
                icon: Icons.send_outlined,
                secondary: true,
                onPressed: _sending ? null : _send,
              ),
            ],
            if (_feedback != null)
              Padding(
                padding: const EdgeInsets.only(top: 14),
                child: Text(
                  _feedback!,
                  style: TextStyle(
                    color: _error ? AirmiusColors.red : AirmiusColors.green,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _row(String label, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 8),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: TextStyle(color: airmiusMutedColor(context))),
        const SizedBox(height: 3),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w700)),
      ],
    ),
  );
}
