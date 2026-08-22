import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../core/airmius_l10n.dart';

class ClubInventoryQrScannerScreen extends StatefulWidget {
  const ClubInventoryQrScannerScreen({super.key});
  @override
  State<ClubInventoryQrScannerScreen> createState() =>
      _ClubInventoryQrScannerScreenState();
}

class _ClubInventoryQrScannerScreenState
    extends State<ClubInventoryQrScannerScreen> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
  );
  bool _handled = false;
  String? _error;

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_handled) return;
    for (final barcode in capture.barcodes) {
      final value = barcode.rawValue?.trim();
      if (value == null ||
          !RegExp(
            r'^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$',
          ).hasMatch(value)) {
        continue;
      }
      _handled = true;
      await _controller.stop();
      if (mounted) Navigator.pop(context, value);
      return;
    }
    if (mounted) {
      setState(() => _error = t('inventory.scannerInvalid'));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Colors.black,
    appBar: AppBar(
      title: Text(t('inventory.scannerTitle')),
      backgroundColor: Colors.black,
      foregroundColor: Colors.white,
    ),
    body: Stack(
      fit: StackFit.expand,
      children: [
        MobileScanner(
          controller: _controller,
          onDetect: _onDetect,
          errorBuilder: (_, _) => Center(
            child: Text(
              t('inventory.scannerCameraFailed'),
              style: const TextStyle(color: Colors.white),
            ),
          ),
        ),
        IgnorePointer(
          child: Center(
            child: Container(
              width: 250,
              height: 250,
              decoration: BoxDecoration(
                border: Border.all(
                  color: Theme.of(context).colorScheme.primary,
                  width: 4,
                ),
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
            _error ?? t('inventory.scannerHint'),
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    ),
  );
}
