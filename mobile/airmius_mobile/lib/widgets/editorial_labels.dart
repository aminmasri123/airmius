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
  'imageWidth': [
    'Bildbreite (Pixel)',
    'Image width (pixels)',
    'Largeur (pixels)',
    'عرض الصورة (بكسل)',
  ],
  'imageWidthHint': [
    'Leer = automatisch. Die Höhe passt sich proportional an; auf kleinen Bildschirmen wird das Bild verkleinert.',
    'Empty = automatic. Height stays proportional; images shrink to fit small screens.',
    'Vide = automatique. La hauteur reste proportionnelle et l’image s’adapte aux petits écrans.',
    'فارغ = تلقائي. يبقى الارتفاع متناسبًا وتتكيف الصورة مع الشاشات الصغيرة.',
  ],
  'imageWidthError': [
    '40 bis 2000 Pixel eingeben.',
    'Enter 40 to 2000 pixels.',
    'Saisissez 40 à 2000 pixels.',
    'أدخل من 40 إلى 2000 بكسل.',
  ],
  'coverFormat': [
    'Empfohlen: Querformat 16:9, z. B. 1600 × 900 Pixel. JPEG, PNG oder WebP, maximal 8 MB. Andere Formate werden vollständig mit Rand angezeigt.',
    'Recommended: landscape 16:9, e.g. 1600 × 900 pixels. JPEG, PNG or WebP, up to 8 MB. Other formats are shown in full with margins.',
    'Recommandé : paysage 16:9, par exemple 1600 × 900 pixels. JPEG, PNG ou WebP, 8 Mo maximum. Les autres formats sont affichés intégralement avec des marges.',
    'الموصى به: أفقي 16:9، مثل 1600 × 900 بكسل. JPEG أو PNG أو WebP، حتى 8 ميغابايت. تظهر الأبعاد الأخرى كاملة مع هوامش.',
  ],
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
  'qualityTitle': [
    'Inhaltsqualität',
    'Content quality',
    'Qualité du contenu',
    'جودة المحتوى',
  ],
  'qualitySummary': [
    '{score}% · {passed} von 6 erfüllt',
    '{score}% · {passed} of 6 passed',
    '{score}% · {passed} sur 6 réussis',
    '{score}% · تم استيفاء {passed} من 6',
  ],
  'qualityPassed': ['Angemessen', 'Suitable', 'Conforme', 'مناسب'],
  'qualityFailed': [
    'Noch nicht erfüllt',
    'Not met yet',
    'Non satisfait',
    'غير مستوفى بعد',
  ],
  'qualityTitleCheck': [
    'SEO-Titel oder Titel: {current} Zeichen (35–65)',
    'SEO title or title: {current} characters (35–65)',
    'Titre SEO ou titre : {current} caractères (35–65)',
    'عنوان SEO أو العنوان: {current} حرفًا (35–65)',
  ],
  'qualityDescriptionCheck': [
    'SEO-Beschreibung oder Kurzbeschreibung: {current} Zeichen (110–160)',
    'SEO description or summary: {current} characters (110–160)',
    'Description SEO ou résumé : {current} caractères (110–160)',
    'وصف SEO أو الملخص: {current} حرفًا (110–160)',
  ],
  'qualityExcerptCheck': [
    'Kurzbeschreibung: {current} Zeichen (mindestens 80)',
    'Summary: {current} characters (at least 80)',
    'Résumé : {current} caractères (au moins 80)',
    'الملخص: {current} حرفًا (80 على الأقل)',
  ],
  'qualityContentCheck': [
    'Artikeltext: {current} Wörter (mindestens 450)',
    'Article text: {current} words (at least 450)',
    'Texte de l’article : {current} mots (au moins 450)',
    'نص المقال: {current} كلمة (450 على الأقل)',
  ],
  'qualityCategoryCheck': [
    'Kategorie ausgewählt',
    'Category selected',
    'Catégorie sélectionnée',
    'تم اختيار الفئة',
  ],
  'qualityCoverCheck': [
    'Titelbild hinterlegt',
    'Cover image provided',
    'Image de couverture ajoutée',
    'تمت إضافة صورة الغلاف',
  ],
  'qualityAllRequired': [
    'Zur Veröffentlichung müssen derzeit alle 6 Prüfungen erfüllt sein.',
    'All 6 checks currently have to pass before publishing.',
    'Les 6 contrôles doivent actuellement être réussis avant publication.',
    'يجب حاليًا استيفاء جميع الفحوصات الستة قبل النشر.',
  ],
};
