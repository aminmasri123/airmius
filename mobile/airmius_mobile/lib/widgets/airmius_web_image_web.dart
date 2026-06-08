import 'dart:html' as html;
import 'dart:ui_web' as ui_web;

import 'package:flutter/widgets.dart';

int _airmiusHtmlImageCounter = 0;

Widget? airmiusHtmlImage(List<String> urls, {BoxFit fit = BoxFit.cover}) {
  if (urls.isEmpty) return null;

  final viewType = 'airmius-html-image-${_airmiusHtmlImageCounter++}';

  ui_web.platformViewRegistry.registerViewFactory(viewType, (int viewId) {
    var index = 0;
    final image = html.ImageElement()
      ..src = urls[index]
      ..alt = 'Airmius Bild';

    image
      ..setAttribute('decoding', 'async')
      ..setAttribute('loading', 'lazy');

    image.onError.listen((_) {
      index += 1;
      if (index < urls.length) {
        image.src = urls[index];
        return;
      }
      image.style.display = 'none';
    });

    image.style
      ..width = '100%'
      ..height = '100%'
      ..objectFit = _objectFit(fit)
      ..display = 'block';

    return image;
  });

  return HtmlElementView(viewType: viewType);
}

String _objectFit(BoxFit fit) {
  return switch (fit) {
    BoxFit.contain => 'contain',
    BoxFit.fill => 'fill',
    BoxFit.fitWidth => 'contain',
    BoxFit.fitHeight => 'contain',
    BoxFit.none => 'none',
    BoxFit.scaleDown => 'scale-down',
    BoxFit.cover => 'cover',
  };
}
