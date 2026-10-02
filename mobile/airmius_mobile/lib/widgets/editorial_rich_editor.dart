import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_quill/flutter_quill.dart' as quill;
import 'package:flutter_quill_delta_from_html/flutter_quill_delta_from_html.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:html/dom.dart' as dom;
import 'package:html/parser.dart' as html;
import 'package:vsc_quill_delta_to_html/vsc_quill_delta_to_html.dart';
import '../core/airmius_l10n.dart';
import 'editorial_labels.dart';

const editorialSemanticColors = <String, String>{
  'primary': '#202124',
  'secondary': '#697077',
  'accent': '#007faa',
  'success': '#238636',
  'warning': '#b77900',
  'danger': '#cf222e',
};

/// Keeps web-only markup as opaque blocks, never feeding it through a lossy codec.
class EditorialRichDocument extends ChangeNotifier {
  EditorialRichDocument(this.originalHtml) {
    final operations = <Map<String, dynamic>>[];
    final fragment = html.parseFragment(originalHtml);
    for (final node in fragment.nodes) {
      if (node is dom.Text && node.text.trim().isEmpty) continue;
      final source = node is dom.Element
          ? node.outerHtml
          : const HtmlEscape().convert(node.text ?? '');
      if (node is dom.Element && !_editable(node)) {
        final id = _preserve(source);
        operations.add({
          'insert': {embedType: id},
        });
        operations.add({'insert': '\n'});
      } else {
        operations.addAll(HtmlToDelta().convert(source).toJson());
      }
    }
    if (operations.isEmpty ||
        !(operations.last['insert'] is String &&
            (operations.last['insert'] as String).endsWith('\n'))) {
      operations.add({'insert': '\n'});
    }
    controller = quill.QuillController(
      document: quill.Document.fromJson(operations),
      selection: const TextSelection.collapsed(offset: 0),
    );
    _initialDelta = jsonEncode(controller.document.toDelta().toJson());
    _initialPreserved = jsonEncode(preserved);
    controller.addListener(notifyListeners);
  }

  static const embedType = 'editorial-preserved';
  final String originalHtml;
  final Map<String, String> preserved = {};
  late final quill.QuillController controller;
  late final String _initialDelta;
  late final String _initialPreserved;

  bool get hasPreservedBlocks => preserved.isNotEmpty;
  bool get hasUnsupportedBlocks => preserved.values.any(
    (source) => !EditorialFormattedBlock(source).editable,
  );

  void updatePreserved(String id, String source) {
    preserved[id] = source;
    notifyListeners();
  }

  bool _editable(dom.Element node) {
    const supported = {
      'p',
      'br',
      'strong',
      'b',
      'em',
      'i',
      'u',
      's',
      'strike',
      'h2',
      'h3',
      'h4',
      'blockquote',
      'ul',
      'ol',
      'li',
      'a',
      'pre',
      'code',
    };
    for (final element in [node, ...node.querySelectorAll('*')]) {
      if (!supported.contains(element.localName)) return false;
      if (element.attributes.keys.any(
        (key) => element.localName != 'a' || key != 'href',
      )) {
        return false;
      }
    }
    return true;
  }

  String _preserve(String source) {
    final id = 'block-${preserved.length}';
    preserved[id] = source;
    return id;
  }

  void insertHtmlBlock(String sanitizedHtml) {
    final index = controller.selection.baseOffset.clamp(
      0,
      controller.document.length - 1,
    );
    controller.replaceText(
      index,
      0,
      quill.BlockEmbed(embedType, _preserve(sanitizedHtml)),
      TextSelection.collapsed(offset: index + 1),
    );
  }

  String toHtml() {
    final delta = controller.document.toDelta().toJson();
    if (jsonEncode(delta) == _initialDelta &&
        jsonEncode(preserved) == _initialPreserved) {
      return originalHtml;
    }
    final result = StringBuffer();
    final chunk = <Map<String, dynamic>>[];
    void flush() {
      if (chunk.isEmpty) return;
      result.write(
        QuillDeltaToHtmlConverter(
          chunk,
          ConverterOptions(
            multiLineParagraph: false,
            converterOptions: OpConverterOptions(
              customTagAttributes: (op) =>
                  op.attributes.direction == DirectionType.rtl
                  ? {'dir': 'rtl'}
                  : null,
              customCssClasses: (op) => [
                for (final entry in editorialSemanticColors.entries)
                  if (entry.value == op.attributes.color)
                    'blog-text-${entry.key}',
                if (op.attributes.background == '#fff2a8') 'blog-mark',
              ],
            ),
          ),
        ).convert(),
      );
      chunk.clear();
    }

    var skipEmbedNewline = false;
    for (final operation in delta) {
      final insert = operation['insert'];
      if (insert is Map && insert.containsKey(embedType)) {
        flush();
        result.write(preserved[insert[embedType]] ?? '');
        skipEmbedNewline = true;
      } else {
        final next = Map<String, dynamic>.from(operation);
        if (skipEmbedNewline && insert is String && insert.startsWith('\n')) {
          next['insert'] = insert.substring(1);
        }
        skipEmbedNewline = false;
        if (next['insert'] != '') chunk.add(next);
      }
    }
    flush();
    return result.toString();
  }

  @override
  void dispose() {
    controller.removeListener(notifyListeners);
    controller.dispose();
    super.dispose();
  }
}

class EditorialHtmlPreview extends StatelessWidget {
  const EditorialHtmlPreview({super.key, required this.htmlContent});
  final String htmlContent;

  @override
  Widget build(BuildContext context) => HtmlWidget(
    htmlContent,
    onTapUrl: (_) => true,
    customStylesBuilder: (element) {
      final colors = Theme.of(context).colorScheme;
      final styles = <String, String>{};
      if (element.classes.contains('blog-lead')) {
        styles.addAll({'font-size': '1.15em', 'font-weight': '600'});
      }
      if (element.classes.contains('blog-callout')) {
        styles.addAll({'border': '1px solid #888888', 'padding': '12px'});
      }
      if (element.classes.contains('blog-mark')) {
        styles['background-color'] = '#fff2a8';
      }
      final semanticColors = {
        'primary': colors.onSurface,
        'secondary': colors.onSurfaceVariant,
        'accent': colors.primary,
        'success': Colors.green,
        'warning': Colors.orange,
        'danger': colors.error,
      };
      for (final entry in semanticColors.entries) {
        if (element.classes.contains('blog-text-${entry.key}')) {
          styles['color'] =
              '#${entry.value.toARGB32().toRadixString(16).substring(2)}';
        }
      }
      return styles.isEmpty ? null : styles;
    },
  );
}

class EditorialRichEditor extends StatelessWidget {
  const EditorialRichEditor({
    super.key,
    required this.document,
    this.enabled = true,
    this.onInsertImage,
  });
  final EditorialRichDocument document;
  final bool enabled;
  final VoidCallback? onInsertImage;

  @override
  Widget build(BuildContext context) {
    document.controller.readOnly = !enabled;
    return Localizations.override(
      context: context,
      locale: context
          .dependOnInheritedWidgetOfExactType<AirmiusScope>()
          ?.language
          .locale,
      delegates: const [quill.FlutterQuillLocalizations.delegate],
      child: ListenableBuilder(
        listenable: document,
        builder: (context, _) => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (document.hasUnsupportedBlocks)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Text(editorialLabel(context, 'unsupported')),
              ),
            IgnorePointer(
              ignoring: !enabled,
              child: quill.QuillSimpleToolbar(
                controller: document.controller,
                config: const quill.QuillSimpleToolbarConfig(
                  showFontFamily: false,
                  showFontSize: false,
                  showColorButton: false,
                  showBackgroundColorButton: false,
                  showSubscript: false,
                  showSuperscript: false,
                  showListCheck: false,
                  showDirection: true,
                  showSearchButton: false,
                  buttonOptions: quill.QuillSimpleToolbarButtonOptions(
                    selectHeaderStyleButtons:
                        quill.QuillToolbarSelectHeaderStyleButtonsOptions(
                          attributes: [
                            quill.Attribute.header,
                            quill.Attribute.h2,
                            quill.Attribute.h3,
                            quill.Attribute.h4,
                          ],
                        ),
                  ),
                ),
              ),
            ),
            Wrap(
              children: [
                IconButton(
                  tooltip: editorialLabel(context, 'image'),
                  icon: const Icon(Icons.add_photo_alternate_outlined),
                  onPressed: enabled ? onInsertImage : null,
                ),
                PopupMenuButton<String>(
                  tooltip: editorialLabel(context, 'addBlock'),
                  icon: const Icon(Icons.add_box_outlined),
                  enabled: enabled,
                  itemBuilder: (_) => ['lead', 'callout', 'image']
                      .map(
                        (key) => PopupMenuItem(
                          value: key,
                          child: Text(editorialLabel(context, key)),
                        ),
                      )
                      .toList(),
                  onSelected: (style) async {
                    final source = style == 'image'
                        ? '<figure class="blog-image"><img src=""><figcaption></figcaption></figure>'
                        : style == 'callout'
                        ? '<div class="blog-callout"><p></p></div>'
                        : '<p class="blog-lead"></p>';
                    final result = await editEditorialFormattedBlock(
                      context,
                      source,
                    );
                    if (result != null && context.mounted) {
                      document.insertHtmlBlock(result);
                    }
                  },
                ),
                for (final entry in editorialSemanticColors.entries)
                  IconButton(
                    tooltip: editorialLabel(context, entry.key),
                    icon: Icon(
                      Icons.circle,
                      color: Color(
                        int.parse('ff${entry.value.substring(1)}', radix: 16),
                      ),
                    ),
                    onPressed: enabled
                        ? () => document.controller.formatSelection(
                            quill.Attribute.clone(
                              quill.Attribute.color,
                              entry.value,
                            ),
                          )
                        : null,
                  ),
                IconButton(
                  tooltip: editorialLabel(context, 'mark'),
                  icon: const Icon(Icons.highlight),
                  onPressed: enabled
                      ? () => document.controller.formatSelection(
                          quill.Attribute.clone(
                            quill.Attribute.background,
                            '#fff2a8',
                          ),
                        )
                      : null,
                ),
              ],
            ),
            Container(
              height: 360,
              decoration: BoxDecoration(
                border: Border.all(color: Theme.of(context).dividerColor),
              ),
              child: quill.QuillEditor.basic(
                controller: document.controller,
                config: quill.QuillEditorConfig(
                  padding: const EdgeInsets.all(12),
                  embedBuilders: [_PreservedEmbedBuilder(document)],
                  onLaunchUrl: (_) {},
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PreservedEmbedBuilder extends quill.EmbedBuilder {
  _PreservedEmbedBuilder(this.document);
  final EditorialRichDocument document;
  @override
  String get key => EditorialRichDocument.embedType;
  @override
  Widget build(BuildContext context, quill.EmbedContext embedContext) {
    final id = embedContext.node.value.data as String;
    final source = document.preserved[id] ?? '';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        EditorialHtmlPreview(htmlContent: source),
        if (!embedContext.readOnly && EditorialFormattedBlock(source).editable)
          Align(
            alignment: Alignment.centerRight,
            child: IconButton(
              tooltip: editorialLabel(context, 'editBlock'),
              icon: const Icon(Icons.edit_outlined),
              onPressed: () async {
                final result = await editEditorialFormattedBlock(
                  context,
                  source,
                );
                if (result != null && context.mounted) {
                  document.updatePreserved(id, result);
                }
              },
            ),
          ),
      ],
    );
  }
}

/// Structured updates retain the rest of a web block's DOM, including captions
/// and nested semantic spans that the regular Delta converter cannot represent.
class EditorialFormattedBlock {
  EditorialFormattedBlock(String source)
    : root = html.parseFragment(source).children.first;
  dom.Element root;
  bool get isFigure => root.localName == 'figure' || root.localName == 'img';
  dom.Element? get image =>
      root.localName == 'img' ? root : root.querySelector('img');
  bool get editable =>
      {
        'p',
        'div',
        'span',
        'figure',
        'img',
        'h2',
        'h3',
        'h4',
        'blockquote',
        'ul',
        'ol',
        'pre',
      }.contains(root.localName) &&
      root.querySelector('table,iframe,video,svg,math') == null;
  String get style => root.classes.contains('blog-lead')
      ? 'lead'
      : root.classes.contains('blog-callout')
      ? 'callout'
      : root.classes.contains('blog-mark')
      ? 'mark'
      : editorialSemanticColors.keys.firstWhere(
          (key) => root.classes.contains('blog-text-$key'),
          orElse: () => 'paragraph',
        );

  String updateText({
    required String content,
    required String style,
    required String direction,
  }) {
    root.innerHtml = content;
    root.classes.removeWhere(
      (name) =>
          name == 'blog-lead' ||
          name == 'blog-callout' ||
          name == 'blog-mark' ||
          name.startsWith('blog-text-'),
    );
    if (style == 'lead' || style == 'callout' || style == 'mark') {
      root.classes.add('blog-$style');
    }
    if (editorialSemanticColors.containsKey(style)) {
      root.classes.add('blog-text-$style');
    }
    if (direction.isEmpty) {
      root.attributes.remove('dir');
    } else {
      root.attributes['dir'] = direction;
    }
    return root.outerHtml;
  }

  String updateFigure({
    required String url,
    required String alt,
    required String caption,
  }) {
    final imageNode = image ?? dom.Element.tag('img');
    if (imageNode.parentNode == null && imageNode != root) {
      root.nodes.insert(0, imageNode);
    }
    imageNode.attributes['src'] = url;
    imageNode.attributes['alt'] = alt;
    if (root.localName == 'img') {
      root = dom.Element.tag('figure')
        ..classes.add('blog-image')
        ..nodes.add(imageNode);
    }
    var captionNode = root.querySelector('figcaption');
    if (captionNode == null && caption.isNotEmpty) {
      captionNode = dom.Element.tag('figcaption');
      root.nodes.add(captionNode);
    }
    if (captionNode != null && captionNode.text != caption) {
      captionNode.text = caption;
    }
    return root.outerHtml;
  }
}

Future<String?> editEditorialFormattedBlock(
  BuildContext context,
  String source,
) => showDialog<String>(
  context: context,
  builder: (_) => _FormattedBlockDialog(source: source),
);

class _FormattedBlockDialog extends StatefulWidget {
  const _FormattedBlockDialog({required this.source});
  final String source;
  @override
  State<_FormattedBlockDialog> createState() => _FormattedBlockDialogState();
}

class _FormattedBlockDialogState extends State<_FormattedBlockDialog> {
  late final EditorialFormattedBlock block;
  late final EditorialRichDocument content;
  late final TextEditingController url;
  late final TextEditingController alt;
  late final TextEditingController caption;
  late String style;
  late String direction;
  final formKey = GlobalKey<FormState>();
  @override
  void initState() {
    super.initState();
    block = EditorialFormattedBlock(widget.source);
    content = EditorialRichDocument(block.isFigure ? '' : block.root.innerHtml);
    url = TextEditingController(text: block.image?.attributes['src'] ?? '');
    alt = TextEditingController(text: block.image?.attributes['alt'] ?? '');
    caption = TextEditingController(
      text: block.root.querySelector('figcaption')?.text ?? '',
    );
    style = block.style;
    direction = block.root.attributes['dir'] ?? '';
  }

  @override
  void dispose() {
    content.dispose();
    url.dispose();
    alt.dispose();
    caption.dispose();
    super.dispose();
  }

  void save() {
    if (!formKey.currentState!.validate()) return;
    if (block.isFigure) {
      Navigator.pop(
        context,
        block.updateFigure(
          url: url.text.trim(),
          alt: alt.text,
          caption: caption.text,
        ),
      );
      return;
    }
    var inner = content.toHtml();
    // A paragraph/span already supplies its own block boundary.
    if (['p', 'span', 'h2', 'h3', 'h4'].contains(block.root.localName)) {
      final fragment = html.parseFragment(inner);
      if (fragment.children.length == 1 &&
          fragment.children.first.localName == 'p') {
        inner = fragment.children.first.innerHtml;
      }
    }
    Navigator.pop(
      context,
      block.updateText(content: inner, style: style, direction: direction),
    );
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(editorialLabel(context, 'editBlock')),
    content: SizedBox(
      width: 640,
      child: SingleChildScrollView(
        child: Form(
          key: formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: block.isFigure
                ? [
                    TextFormField(
                      controller: url,
                      decoration: InputDecoration(
                        labelText: editorialLabel(context, 'url'),
                      ),
                      validator: (value) {
                        final uri = Uri.tryParse(value?.trim() ?? '');
                        return uri != null &&
                                ['https', 'http'].contains(uri.scheme) &&
                                uri.host.isNotEmpty
                            ? null
                            : editorialLabel(context, 'invalidUrl');
                      },
                    ),
                    TextFormField(
                      controller: alt,
                      maxLength: 160,
                      decoration: InputDecoration(
                        labelText: editorialLabel(context, 'alt'),
                      ),
                    ),
                    TextFormField(
                      controller: caption,
                      maxLines: 3,
                      decoration: InputDecoration(
                        labelText: editorialLabel(context, 'caption'),
                      ),
                    ),
                  ]
                : [
                    DropdownButtonFormField<String>(
                      initialValue: style,
                      decoration: InputDecoration(
                        labelText: editorialLabel(context, 'style'),
                      ),
                      items:
                          [
                                'paragraph',
                                'lead',
                                'callout',
                                ...editorialSemanticColors.keys,
                                'mark',
                              ]
                              .map(
                                (key) => DropdownMenuItem(
                                  value: key,
                                  child: Text(editorialLabel(context, key)),
                                ),
                              )
                              .toList(),
                      onChanged: (value) => setState(() => style = value!),
                    ),
                    DropdownButtonFormField<String>(
                      initialValue: direction,
                      decoration: InputDecoration(
                        labelText: editorialLabel(context, 'direction'),
                      ),
                      items: const [
                        DropdownMenuItem(value: '', child: Text('Auto')),
                        DropdownMenuItem(value: 'ltr', child: Text('LTR')),
                        DropdownMenuItem(value: 'rtl', child: Text('RTL')),
                      ],
                      onChanged: (value) => setState(() => direction = value!),
                    ),
                    EditorialRichEditor(document: content),
                  ],
          ),
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: Text(editorialLabel(context, 'cancel')),
      ),
      FilledButton(
        onPressed: save,
        child: Text(editorialLabel(context, 'save')),
      ),
    ],
  );
}
