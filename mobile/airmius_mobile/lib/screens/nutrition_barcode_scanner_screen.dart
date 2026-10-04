import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../core/airmius_l10n.dart';

class NutritionBarcodeScannerScreen extends StatefulWidget {
  const NutritionBarcodeScannerScreen({super.key});

  @override
  State<NutritionBarcodeScannerScreen> createState() =>
      _NutritionBarcodeScannerScreenState();
}

class _NutritionBarcodeScannerScreenState
    extends State<NutritionBarcodeScannerScreen> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
    formats: const [
      BarcodeFormat.ean8,
      BarcodeFormat.ean13,
      BarcodeFormat.upcA,
      BarcodeFormat.upcE,
      BarcodeFormat.code128,
    ],
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
      final value = barcode.rawValue?.replaceAll(RegExp(r'[\s-]'), '').trim();
      if (value == null || !RegExp(r'^\d{6,18}$').hasMatch(value)) continue;
      _handled = true;
      await _controller.stop();
      if (mounted) Navigator.pop(context, value);
      return;
    }
    if (mounted) setState(() => _error = t('nutrition.barcodeScanInvalid'));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Colors.black,
    appBar: AppBar(
      title: Text(t('nutrition.barcodeScanTitle')),
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
              t('nutrition.barcodeScanCameraFailed'),
              style: const TextStyle(color: Colors.white),
            ),
          ),
        ),
        IgnorePointer(
          child: Center(
            child: Container(
              width: 280,
              height: 180,
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
            _error ?? t('nutrition.barcodeScanHint'),
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
