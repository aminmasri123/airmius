import 'dart:async';
import 'dart:html' as html;
import 'dart:math' as math;
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';

const int _maxUploadBytes = 4 * 1024 * 1024;
const int _initialMaxDimension = 1600;

Future<PlatformFile> preparePostImageForUpload(PlatformFile file) async {
  final bytes = file.bytes;
  if (bytes == null || bytes.isEmpty) return file;

  final lowerName = file.name.toLowerCase();
  final isBrowserFriendly = lowerName.endsWith('.jpg') ||
      lowerName.endsWith('.jpeg') ||
      lowerName.endsWith('.png') ||
      lowerName.endsWith('.webp') ||
      lowerName.endsWith('.gif');

  if (bytes.length <= _maxUploadBytes && isBrowserFriendly && !lowerName.endsWith('.webp')) {
    return file;
  }

  try {
    final compressed = await _compressToJpeg(bytes);
    if (compressed.isEmpty) return file;

    final baseName = file.name.replaceFirst(RegExp(r'\.[^.]+$'), '');
    return PlatformFile(
      name: '${baseName.isEmpty ? 'beitragsbild' : baseName}.jpg',
      size: compressed.length,
      bytes: compressed,
    );
  } catch (_) {
    return file;
  }
}

Future<Uint8List> _compressToJpeg(Uint8List bytes) async {
  final blob = html.Blob([bytes]);
  final objectUrl = html.Url.createObjectUrlFromBlob(blob);

  try {
    final image = html.ImageElement()..src = objectUrl;
    await image.onLoad.first.timeout(const Duration(seconds: 10));

    final sourceWidth = image.naturalWidth;
    final sourceHeight = image.naturalHeight;
    if (sourceWidth <= 0 || sourceHeight <= 0) return Uint8List(0);

    for (final maxDimension in const [1600, 1400, 1200, 1000, 800]) {
      final scale = math.min(1.0, maxDimension / math.max(sourceWidth, sourceHeight));
      final targetWidth = math.max(1, (sourceWidth * scale).round());
      final targetHeight = math.max(1, (sourceHeight * scale).round());

      final canvas = html.CanvasElement(width: targetWidth, height: targetHeight);
      canvas.context2D.drawImageScaled(image, 0, 0, targetWidth, targetHeight);

      for (final quality in const [0.86, 0.78, 0.68, 0.58]) {
        final result = await _canvasToBytes(canvas, quality);
        if (result.isNotEmpty && result.length <= _maxUploadBytes) return result;
      }
    }

    return Uint8List(0);
  } finally {
    html.Url.revokeObjectUrl(objectUrl);
  }
}

Future<Uint8List> _canvasToBytes(html.CanvasElement canvas, double quality) {
  return canvas.toBlob('image/jpeg', quality).then((blob) {
    final completer = Completer<Uint8List>();
    final reader = html.FileReader();
    reader.onError.first.then((_) {
      if (!completer.isCompleted) completer.complete(Uint8List(0));
    });
    reader.onLoad.first.then((_) {
      final result = reader.result;
      if (result is ByteBuffer) {
        completer.complete(Uint8List.view(result));
        return;
      }
      completer.complete(Uint8List(0));
    });
    reader.readAsArrayBuffer(blob);
    return completer.future;
  }).timeout(const Duration(seconds: 10), onTimeout: () => Uint8List(0));
}
