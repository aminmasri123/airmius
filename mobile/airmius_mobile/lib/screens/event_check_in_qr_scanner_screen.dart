import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_services_scope.dart';

class EventCheckInQrScannerScreen extends StatefulWidget {
  const EventCheckInQrScannerScreen({
    required this.eventId,
    required this.clubId,
    super.key,
  });

  final int eventId;
  final int clubId;

  @override
  State<EventCheckInQrScannerScreen> createState() =>
      _EventCheckInQrScannerScreenState();
}

class _EventCheckInQrScannerScreenState
    extends State<EventCheckInQrScannerScreen> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
  );
  bool _processing = false;
  int _successCount = 0;
  String? _lastToken;
  DateTime? _lastScanAt;
  String? _feedback;
  bool _feedbackIsError = false;
  String? _error;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_processing) return;
    for (final barcode in capture.barcodes) {
      final token = _extractToken(barcode.rawValue);
      if (token == null) continue;
      final now = DateTime.now();
      if (_lastToken == token &&
          _lastScanAt != null &&
          now.difference(_lastScanAt!) < const Duration(seconds: 2)) {
        return;
      }
      _lastToken = token;
      _lastScanAt = now;
      await _verify(token);
      return;
    }
    if (mounted && _error == null) {
      setState(() => _error = 'Kein gültiger Mitgliedskarten-Code erkannt.');
    }
  }

  Future<void> _verify(String token) async {
    setState(() {
      _processing = true;
      _error = null;
      _feedback = 'Mitgliedskarte wird geprüft...';
      _feedbackIsError = false;
    });

    try {
      final services = AirmiusServicesScope.of(context);
      final response = await services
          .clientForSession(services.authState.session)
          .verifyClubMemberCard(widget.clubId, {
            'token': token,
            'event_id': widget.eventId,
          });
      final data = response['data'] is JsonMap
          ? response['data'] as JsonMap
          : const <String, dynamic>{};
      final member = data['member'] is JsonMap
          ? data['member'] as JsonMap
          : const <String, dynamic>{};
      final event = data['event'] is JsonMap
          ? data['event'] as JsonMap
          : const <String, dynamic>{};
      final alreadyCheckedIn = event['already_checked_in'] == true;
      if (!mounted) return;
      setState(() {
        if (!alreadyCheckedIn) _successCount += 1;
        _feedback = alreadyCheckedIn
            ? '${member['name'] ?? 'Mitglied'} war bereits eingecheckt.'
            : '${member['name'] ?? 'Mitglied'} wurde eingecheckt.';
        _feedbackIsError = false;
        _processing = false;
      });
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _feedback = error.userMessage;
        _feedbackIsError = true;
        _processing = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _feedback = 'Die Mitgliedskarte konnte nicht geprüft werden.';
        _feedbackIsError = true;
        _processing = false;
      });
    }
  }

  String? _extractToken(String? rawValue) {
    final raw = rawValue?.trim();
    if (raw == null || raw.isEmpty) return null;

    final uri = Uri.tryParse(raw);
    if (uri != null && uri.queryParameters.containsKey('token')) {
      final event = int.tryParse(
        uri.queryParameters['event_id'] ?? uri.queryParameters['event'] ?? '',
      );
      if (event != null && event != widget.eventId) {
        if (mounted) {
          setState(
            () => _error = 'Dieser QR-Code gehört zu einem anderen Termin.',
          );
        }
        return null;
      }
      final token = uri.queryParameters['token']?.trim();
      return token != null && token.length >= 24 ? token : null;
    }

    if (raw.contains('://') || raw.length < 24 || raw.length > 160) {
      return null;
    }
    return raw;
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        title: const Text('Mitgliedskarte scannen'),
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        leading: IconButton(
          tooltip: 'Scanner schließen',
          onPressed: () => Navigator.pop(context, _successCount),
          icon: const Icon(Icons.close),
        ),
      ),
      body: Stack(
        fit: StackFit.expand,
        children: [
          MobileScanner(
            controller: _controller,
            onDetect: _onDetect,
            errorBuilder: (_, _) => const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Text(
                  'Kamera konnte nicht geöffnet werden.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.white, height: 1.35),
                ),
              ),
            ),
          ),
          IgnorePointer(
            child: Center(
              child: Container(
                width: 250,
                height: 250,
                decoration: BoxDecoration(
                  border: Border.all(color: scheme.primary, width: 4),
                  borderRadius: BorderRadius.circular(24),
                ),
              ),
            ),
          ),
          Positioned(
            left: 20,
            right: 20,
            top: 24,
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: Colors.black.withValues(alpha: .72),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 10,
                ),
                child: Text(
                  '$_successCount neue Check-ins',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ),
          ),
          Positioned(
            left: 20,
            right: 20,
            bottom: 28,
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: _feedbackIsError
                    ? Colors.red.shade800.withValues(alpha: .92)
                    : Colors.black.withValues(alpha: .78),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Text(
                  _feedback ??
                      _error ??
                      'Halte den QR-Code der Mitgliedskarte in den Rahmen.',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
