import 'package:html/parser.dart' as html;

enum EditorialQualityCriterion {
  title,
  description,
  excerpt,
  content,
  category,
  cover,
}

class EditorialQualityCheck {
  const EditorialQualityCheck({
    required this.criterion,
    required this.passed,
    required this.current,
    required this.minimum,
    this.maximum,
  });

  final EditorialQualityCriterion criterion;
  final bool passed;
  final int current;
  final int minimum;
  final int? maximum;
}

class EditorialQualityResult {
  const EditorialQualityResult(this.checks);

  final List<EditorialQualityCheck> checks;

  int get passed => checks.where((check) => check.passed).length;

  int get score =>
      checks.isEmpty ? 0 : ((passed / checks.length) * 100).round();

  bool get publishable => score >= 85;
}

EditorialQualityResult evaluateEditorialQuality({
  required String title,
  required String metaTitle,
  required String excerpt,
  required String metaDescription,
  required String contentHtml,
  required bool hasCategory,
  required bool hasCover,
}) {
  final effectiveTitle = metaTitle.trim().isNotEmpty
      ? metaTitle.trim()
      : title.trim();
  final effectiveDescription = metaDescription.trim().isNotEmpty
      ? metaDescription.trim()
      : excerpt.trim();
  final excerptLength = excerpt.trim().length;
  final wordCount = RegExp(
    r'[\p{L}\p{N}]+',
    unicode: true,
  ).allMatches(html.parseFragment(contentHtml).text ?? '').length;

  return EditorialQualityResult([
    EditorialQualityCheck(
      criterion: EditorialQualityCriterion.title,
      passed: effectiveTitle.length >= 35 && effectiveTitle.length <= 65,
      current: effectiveTitle.length,
      minimum: 35,
      maximum: 65,
    ),
    EditorialQualityCheck(
      criterion: EditorialQualityCriterion.description,
      passed:
          effectiveDescription.length >= 110 &&
          effectiveDescription.length <= 160,
      current: effectiveDescription.length,
      minimum: 110,
      maximum: 160,
    ),
    EditorialQualityCheck(
      criterion: EditorialQualityCriterion.excerpt,
      passed: excerptLength >= 80,
      current: excerptLength,
      minimum: 80,
    ),
    EditorialQualityCheck(
      criterion: EditorialQualityCriterion.content,
      passed: wordCount >= 450,
      current: wordCount,
      minimum: 450,
    ),
    EditorialQualityCheck(
      criterion: EditorialQualityCriterion.category,
      passed: hasCategory,
      current: hasCategory ? 1 : 0,
      minimum: 1,
    ),
    EditorialQualityCheck(
      criterion: EditorialQualityCriterion.cover,
      passed: hasCover,
      current: hasCover ? 1 : 0,
      minimum: 1,
    ),
  ]);
}
