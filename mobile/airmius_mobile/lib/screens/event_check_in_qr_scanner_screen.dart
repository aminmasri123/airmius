import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

class EventCheckInQrScannerScreen extends StatefulWidget {
  const EventCheckInQrScannerScreen({required this.eventId, super.key});

  final int eventId;

  @override
  State<EventCheckInQrScannerScreen> createState() =>
      _EventCheckInQrScannerScreenState();
}

class _EventCheckInQrScannerScreenState
    extends State<EventCheckInQrScannerScreen> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
  );
  bool _handled = false;
  String? _error;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_handled) return;
    for (final barcode in capture.barcodes) {
      final token = _extractToken(barcode.rawValue);
      if (token == null) continue;
      _handled = true;
      await _controller.stop();
      if (mounted) Navigator.pop(context, token);
      return;
    }
    if (mounted && _error == null) {
      setState(() => _error = 'Kein gültiger Event-Check-in-Code erkannt.');
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
        title: const Text('Check-in-Code scannen'),
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
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
            bottom: 28,
            child: Text(
              _error ?? 'Halte den Event-Check-in-QR-Code in den Rahmen.',
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
