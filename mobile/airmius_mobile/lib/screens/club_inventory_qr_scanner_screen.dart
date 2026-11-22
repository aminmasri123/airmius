import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';

bool isAirmiusInventoryQrPayload(String value) {
  final normalized = value.trim();
  return normalized.length <= 2048 &&
      RegExp(r'^airmius-inventory:[A-Za-z0-9_-]{16,}$').hasMatch(normalized);
}

enum _InventoryScanStatus { searching, wrongCode, checking, valid, error }

class ClubInventoryQrScannerScreen extends StatefulWidget {
  const ClubInventoryQrScannerScreen({required this.clubId, super.key});

  final int clubId;

  @override
  State<ClubInventoryQrScannerScreen> createState() =>
      _ClubInventoryQrScannerScreenState();
}

class _ClubInventoryQrScannerScreenState
    extends State<ClubInventoryQrScannerScreen> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
  );
  _InventoryScanStatus _status = _InventoryScanStatus.searching;
  String? _detail;
  bool _processing = false;
  int _statusGeneration = 0;

  String t(String key) => AirmiusScope.of(context).t(key);

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void dispose() {
    _statusGeneration++;
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_processing) return;

    final values = capture.barcodes
        .map((barcode) => barcode.rawValue?.trim())
        .whereType<String>()
        .where((value) => value.isNotEmpty)
        .toList();
    if (values.isEmpty) return;

    String? payload;
    for (final value in values) {
      if (isAirmiusInventoryQrPayload(value)) {
        payload = value;
        break;
      }
    }
    if (payload == null) {
      if (_status != _InventoryScanStatus.wrongCode) {
        HapticFeedback.selectionClick();
        _showTemporaryStatus(_InventoryScanStatus.wrongCode);
      }
      return;
    }

    _processing = true;
    final generation = ++_statusGeneration;
    HapticFeedback.mediumImpact();
    setState(() {
      _status = _InventoryScanStatus.checking;
      _detail = null;
    });
    await _controller.stop();

    try {
      final response = await _client.scanClubInventoryItem(
        widget.clubId,
        payload,
      );
      final data = response['data'];
      if (!mounted || generation != _statusGeneration) return;
      if (data is! Map) {
        throw const FormatException('Missing inventory item');
      }
      final item = Map<String, dynamic>.from(data);
      HapticFeedback.heavyImpact();
      setState(() => _status = _InventoryScanStatus.valid);
      await Future<void>.delayed(const Duration(milliseconds: 550));
      if (mounted && generation == _statusGeneration) {
        Navigator.pop(context, item);
      }
    } on AirmiusApiException catch (error) {
      if (!mounted || generation != _statusGeneration) return;
      setState(() {
        _status = _InventoryScanStatus.error;
        _detail = switch (error.statusCode) {
          403 => t('inventory.scannerNoAccess'),
          404 => t('inventory.scannerWrongClubOrInvalid'),
          410 => t('inventory.scannerRevoked'),
          _ => error.userMessage,
        };
        _processing = false;
      });
      await _controller.start();
    } catch (_) {
      if (!mounted || generation != _statusGeneration) return;
      setState(() {
        _status = _InventoryScanStatus.error;
        _detail = t('inventory.scannerCheckFailed');
        _processing = false;
      });
      await _controller.start();
    }
  }

  void _showTemporaryStatus(_InventoryScanStatus status) {
    final generation = ++_statusGeneration;
    setState(() {
      _status = status;
      _detail = null;
    });
    Future<void>.delayed(const Duration(milliseconds: 2400), () {
      if (!mounted || _processing || generation != _statusGeneration) return;
      setState(() => _status = _InventoryScanStatus.searching);
    });
  }

  void _continueScanning() {
    _statusGeneration++;
    setState(() {
      _status = _InventoryScanStatus.searching;
      _detail = null;
      _processing = false;
    });
  }

  String get _statusText => switch (_status) {
    _InventoryScanStatus.searching => t('inventory.scannerSearching'),
    _InventoryScanStatus.wrongCode => t('inventory.scannerWrongCode'),
    _InventoryScanStatus.checking => t('inventory.scannerChecking'),
    _InventoryScanStatus.valid => t('inventory.scannerValid'),
    _InventoryScanStatus.error =>
      _detail ?? t('inventory.scannerCheckFailed'),
  };

  IconData get _statusIcon => switch (_status) {
    _InventoryScanStatus.searching => Icons.qr_code_scanner,
    _InventoryScanStatus.wrongCode => Icons.qr_code_2_outlined,
    _InventoryScanStatus.checking => Icons.manage_search,
    _InventoryScanStatus.valid => Icons.check_circle,
    _InventoryScanStatus.error => Icons.error_outline,
  };

  Color _statusColor(BuildContext context) => switch (_status) {
    _InventoryScanStatus.searching => Theme.of(context).colorScheme.primary,
    _InventoryScanStatus.wrongCode => Colors.orange,
    _InventoryScanStatus.checking => Colors.lightBlue,
    _InventoryScanStatus.valid => Colors.green,
    _InventoryScanStatus.error => Theme.of(context).colorScheme.error,
  };

  @override
  Widget build(BuildContext context) {
    final statusColor = _statusColor(context);
    return Scaffold(
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
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Text(
                  t('inventory.scannerCameraFailed'),
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.white),
                ),
              ),
            ),
          ),
          IgnorePointer(
            child: Center(
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                width: 250,
                height: 250,
                decoration: BoxDecoration(
                  border: Border.all(color: statusColor, width: 4),
                  borderRadius: BorderRadius.circular(24),
                ),
              ),
            ),
          ),
          Positioned(
            left: 16,
            right: 16,
            bottom: 24,
            child: SafeArea(
              top: false,
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: 0.82),
                  border: Border.all(color: statusColor, width: 1.5),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Row(
                  children: [
                    if (_status == _InventoryScanStatus.checking)
                      SizedBox(
                        width: 24,
                        height: 24,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.5,
                          color: statusColor,
                        ),
                      )
                    else
                      Icon(_statusIcon, color: statusColor, size: 28),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _statusText,
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          if (_status == _InventoryScanStatus.searching) ...[
                            const SizedBox(height: 4),
                            Text(
                              t('inventory.scannerHint'),
                              style: const TextStyle(color: Colors.white70),
                            ),
                          ],
                        ],
                      ),
                    ),
                    if (_status == _InventoryScanStatus.wrongCode ||
                        _status == _InventoryScanStatus.error)
                      TextButton(
                        onPressed: _continueScanning,
                        child: Text(t('inventory.scannerContinue')),
                      ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
