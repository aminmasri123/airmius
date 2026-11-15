import 'package:flutter/widgets.dart';
import 'airmius_l10n.dart';

const exerciseCategories = [
  "warmup",
  "strength",
  "endurance",
  "technique",
  "mobility",
  "balance",
  "recovery",
  "other",
];
const Map<String, Map<String, String>> _copy = {
  "de": {
    "suitable": "Geeignet für",
    "general": "Sportübergreifend",
    "choose": "Sportarten auswählen",
    "search": "Sportart suchen",
    "empty": "Keine Sportart gefunden.",
    "hint":
        "Ohne Einschränkung für alle Sportarten geeignet. Wähle Sportarten nur für spezielle Übungen.",
    "category": "Kategorie",
    "uncategorized": "Noch nicht zugeordnet",
    "allSports": "Alle Sportarten",
    "allCategories": "Alle Kategorien",
    "filterHint":
        "Sportübergreifende Übungen bleiben im Sportartenfilter sichtbar.",
    "done": "Auswahl übernehmen",
    "warmup": "Aufwärmen",
    "strength": "Kraft",
    "endurance": "Ausdauer",
    "technique": "Technik",
    "mobility": "Beweglichkeit",
    "balance": "Balance & Koordination",
    "recovery": "Regeneration",
    "other": "Sonstiges",
  },
  "en": {
    "suitable": "Suitable for",
    "general": "All sports",
    "choose": "Select sports",
    "search": "Search sports",
    "empty": "No sports found.",
    "hint":
        "Suitable for all sports by default. Select sports only for specific exercises.",
    "category": "Category",
    "uncategorized": "Not assigned",
    "allSports": "All sports",
    "allCategories": "All categories",
    "filterHint": "General exercises remain visible when filtering by sport.",
    "done": "Apply selection",
    "warmup": "Warm-up",
    "strength": "Strength",
    "endurance": "Endurance",
    "technique": "Technique",
    "mobility": "Mobility",
    "balance": "Balance & coordination",
    "recovery": "Recovery",
    "other": "Other",
  },
  "fr": {
    "suitable": "Adapté à",
    "general": "Tous sports",
    "choose": "Choisir des sports",
    "search": "Rechercher un sport",
    "empty": "Aucun sport trouvé.",
    "hint":
        "Convient à tous les sports par défaut. Choisissez des sports pour les exercices spécifiques.",
    "category": "Catégorie",
    "uncategorized": "Non attribuée",
    "allSports": "Tous les sports",
    "allCategories": "Toutes les catégories",
    "filterHint":
        "Les exercices généraux restent visibles avec un filtre de sport.",
    "done": "Appliquer la sélection",
    "warmup": "Échauffement",
    "strength": "Force",
    "endurance": "Endurance",
    "technique": "Technique",
    "mobility": "Mobilité",
    "balance": "Équilibre et coordination",
    "recovery": "Récupération",
    "other": "Autre",
  },
  "ar": {
    "suitable": "مناسب لـ",
    "general": "جميع الرياضات",
    "choose": "اختيار الرياضات",
    "search": "البحث عن رياضة",
    "empty": "لم يتم العثور على رياضة.",
    "hint":
        "مناسب لجميع الرياضات افتراضياً. اختر رياضات للتمارين المتخصصة فقط.",
    "category": "الفئة",
    "uncategorized": "غير مصنف",
    "allSports": "جميع الرياضات",
    "allCategories": "جميع الفئات",
    "filterHint": "تظل التمارين العامة ظاهرة عند التصفية حسب الرياضة.",
    "done": "تطبيق الاختيار",
    "warmup": "إحماء",
    "strength": "قوة",
    "endurance": "تحمل",
    "technique": "تقنية",
    "mobility": "مرونة",
    "balance": "توازن وتنسيق",
    "recovery": "تعافٍ",
    "other": "أخرى",
  },
};
String suitabilityCopy(BuildContext context, String key) {
  final code = AirmiusScope.of(context).language.locale.languageCode;
  return _copy[code]?[key] ?? _copy['en']?[key] ?? key;
}

List<String> exerciseSports(Map<String, dynamic> exercise) {
  final sports = exercise['sport_types'];
  if (sports is List) return sports.whereType<String>().toList();
  final legacy = exercise['sport_type']?.toString().trim() ?? '';
  return legacy.isEmpty ? [] : [legacy];
}

String sportSearch(String text) {
  var result = text.toLowerCase();
  const replacements = {
    'ä': 'a',
    'ö': 'o',
    'ü': 'u',
    'ß': 'ss',
    'é': 'e',
    'è': 'e',
    'ê': 'e',
    'à': 'a',
    'ç': 'c',
  };
  replacements.forEach((from, to) => result = result.replaceAll(from, to));
  return result;
}
