import 'package:airmius/core/editorial_quality.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('matches the six backend editorial quality checks', () {
    final result = evaluateEditorialQuality(
      title: 'Ein ausreichend langer Artikeltitel fuer den Test',
      metaTitle: '',
      excerpt:
          'Diese Kurzbeschreibung ist bewusst lang genug und dient gleichzeitig als passende Beschreibung fuer den Artikel im Test.',
      metaDescription:
          'Diese Meta-Beschreibung hat eine passende Länge für die Qualitätsprüfung des Artikels und erklärt den Inhalt klar und verständlich.',
      contentHtml: '<p>${List.filled(450, 'Training').join(' ')}</p>',
      hasCategory: true,
      hasCover: true,
    );

    expect(result.passed, 6);
    expect(result.score, 100);
    expect(result.publishable, isTrue);
  });

  test('shows 83 percent when exactly one check fails', () {
    final result = evaluateEditorialQuality(
      title: 'Ein ausreichend langer Artikeltitel fuer den Test',
      metaTitle: '',
      excerpt:
          'Diese Kurzbeschreibung ist bewusst lang genug und dient gleichzeitig als passende Beschreibung fuer den Artikel im Test.',
      metaDescription:
          'Diese Meta-Beschreibung hat eine passende Länge für die Qualitätsprüfung des Artikels und erklärt den Inhalt klar und verständlich.',
      contentHtml: '<p>${List.filled(449, 'Training').join(' ')}</p>',
      hasCategory: true,
      hasCover: true,
    );

    expect(result.passed, 5);
    expect(result.score, 83);
    expect(
      result.checks
          .singleWhere(
            (check) => check.criterion == EditorialQualityCriterion.content,
          )
          .passed,
      isFalse,
    );
  });

  test('uses SEO fields before their article fallbacks', () {
    final result = evaluateEditorialQuality(
      title: 'Ein ausreichend langer Artikeltitel fuer den Test',
      metaTitle: 'Kurz',
      excerpt:
          'Diese Kurzbeschreibung ist bewusst lang genug und dient gleichzeitig als passende Beschreibung fuer den Artikel im Test.',
      metaDescription: 'Zu kurz',
      contentHtml: '<p>${List.filled(450, 'Training').join(' ')}</p>',
      hasCategory: true,
      hasCover: true,
    );

    expect(result.score, 67);
    expect(result.checks[0].passed, isFalse);
    expect(result.checks[1].passed, isFalse);
  });
}
