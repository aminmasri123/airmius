import 'package:flutter/widgets.dart';
import '../core/airmius_l10n.dart';

String editorialLabel(BuildContext context, String key) {
  final locale =
      context
          .dependOnInheritedWidgetOfExactType<AirmiusScope>()
          ?.language
          .code ??
      Localizations.localeOf(context).languageCode;
  final index = ['de', 'en', 'fr', 'ar'].indexOf(locale.toLowerCase());
  return _labels[key]![index < 0 ? 1 : index];
}

const _labels = <String, List<String>>{
  'language': [
    'Inhaltssprache',
    'Content language',
    'Langue du contenu',
    'لغة المحتوى',
  ],
  'translation': [
    'Übersetzung erstellen',
    'Create translation',
    'Créer une traduction',
    'إنشاء ترجمة',
  ],
  'preview': [
    'Entwurfsvorschau',
    'Draft preview',
    'Aperçu du brouillon',
    'معاينة المسودة',
  ],
  'cover': [
    'Titelbild hochladen',
    'Upload cover image',
    'Téléverser la couverture',
    'رفع صورة الغلاف',
  ],
  'image': ['Bild einfügen', 'Insert image', 'Insérer une image', 'إدراج صورة'],
  'editBlock': [
    'Formatierten Block bearbeiten',
    'Edit formatted block',
    'Modifier le bloc formaté',
    'تحرير الكتلة المنسقة',
  ],
  'addBlock': [
    'Textblock einfügen',
    'Insert text block',
    'Insérer un bloc de texte',
    'إدراج كتلة نصية',
  ],
  'lead': ['Einleitung', 'Lead', 'Introduction', 'مقدمة'],
  'callout': ['Hinweisbox', 'Callout', 'Encadré', 'ملاحظة بارزة'],
  'paragraph': ['Absatz', 'Paragraph', 'Paragraphe', 'فقرة'],
  'primary': [
    'Standardtext',
    'Primary text',
    'Texte principal',
    'النص الأساسي',
  ],
  'secondary': [
    'Nebeninfo',
    'Secondary text',
    'Texte secondaire',
    'النص الثانوي',
  ],
  'accent': ['Akzent', 'Accent', 'Accent', 'تمييز'],
  'success': ['Positiv', 'Success', 'Positif', 'نجاح'],
  'warning': ['Wichtig', 'Warning', 'Important', 'تنبيه'],
  'danger': ['Warnung', 'Danger', 'Avertissement', 'تحذير'],
  'mark': ['Markierung', 'Highlight', 'Surlignage', 'إبراز'],
  'alt': [
    'Alternativtext',
    'Alternative text',
    'Texte alternatif',
    'النص البديل',
  ],
  'caption': ['Bildunterschrift', 'Caption', 'Légende', 'وصف الصورة'],
  'url': ['Bildadresse', 'Image URL', 'Adresse de l’image', 'رابط الصورة'],
  'invalidUrl': [
    'HTTPS- oder HTTP-Adresse erforderlich.',
    'An HTTPS or HTTP URL is required.',
    'Une adresse HTTPS ou HTTP est requise.',
    'يلزم رابط HTTPS أو HTTP.',
  ],
  'save': ['Speichern', 'Save', 'Enregistrer', 'حفظ'],
  'cancel': ['Abbrechen', 'Cancel', 'Annuler', 'إلغاء'],
  'style': ['Textart', 'Text style', 'Style de texte', 'نمط النص'],
  'direction': [
    'Textrichtung',
    'Text direction',
    'Sens du texte',
    'اتجاه النص',
  ],
  'unsupported': [
    'Zusätzliche Web-Formatierung bleibt unverändert erhalten. Diese Blöcke können nur im Webeditor bearbeitet werden.',
    'Additional web formatting is preserved unchanged. These blocks can only be edited in the web editor.',
    'La mise en forme web supplémentaire est conservée. Ces blocs ne sont modifiables que dans l’éditeur web.',
    'يتم الاحتفاظ بتنسيقات الويب الإضافية دون تغيير. لا يمكن تحرير هذه الكتل إلا في محرر الويب.',
  ],
  'uploadError': [
    'JPEG, PNG oder WebP bis 8 MB auswählen.',
    'Choose a JPEG, PNG or WebP image up to 8 MB.',
    'Choisissez une image JPEG, PNG ou WebP de 8 Mo maximum.',
    'اختر صورة JPEG أو PNG أو WebP بحجم أقصى 8 ميغابايت.',
  ],
};
