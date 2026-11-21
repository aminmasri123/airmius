import 'dart:convert';
import 'package:flutter/material.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';

String formMessage(BuildContext context, String key) {
  final language = AirmiusScope.of(context).language.locale.languageCode;
  return (_messages[language] ?? _messages['en']!)[key] ?? key;
}

const _messages = {
  'de': {
    'check': 'Bitte prüfe die markierten Felder.',
    'required': 'Bitte fülle dieses Feld aus.',
    'failed': 'Speichern nicht möglich. Bitte versuche es erneut.',
    'network':
        'Keine Verbindung zum Server. Deine Eingaben bleiben erhalten. Bitte versuche es erneut.',
    'key':
        'Mit einem Kleinbuchstaben beginnen. Nur Kleinbuchstaben, Zahlen und Unterstriche verwenden (maximal 80 Zeichen).',
    'name': 'Bitte einen Namen mit höchstens 160 Zeichen eingeben.',
    'color': 'Bitte eine Farbe im Format #2563EB eingeben.',
    'options':
        'Bitte 1 bis 50 unterschiedliche Antworten eingeben, eine pro Zeile (je maximal 160 Zeichen).',
    'order': 'Bitte eine ganze Zahl zwischen 0 und 10000 eingeben.',
    'padding': 'Bitte eine ganze Zahl zwischen 1 und 12 eingeben.',
    'start': 'Bitte eine ganze Zahl zwischen 1 und 999999999999 eingeben.',
    'token': 'Maximal 40 Zeichen. Erlaubte Platzhalter: {YYYY} und {YY}.',
    'year':
        'Für den jährlichen Neustart muss Präfix oder Suffix {YYYY} oder {YY} enthalten.',
  },
  'en': {
    'check': 'Please check the highlighted fields.',
    'required': 'Please complete this field.',
    'failed': 'Could not save. Please try again.',
    'network':
        'Cannot connect to the server. Your entries are kept. Please try again.',
    'key':
        'Start with a lowercase letter. Use only lowercase letters, numbers and underscores (up to 80 characters).',
    'name': 'Please enter a name of up to 160 characters.',
    'color': 'Please enter a color such as #2563EB.',
    'options':
        'Enter 1 to 50 different answers, one per line (up to 160 characters each).',
    'order': 'Enter a whole number from 0 to 10000.',
    'padding': 'Enter a whole number from 1 to 12.',
    'start': 'Enter a whole number from 1 to 999999999999.',
    'token': 'Up to 40 characters. Supported placeholders: {YYYY} and {YY}.',
    'year': 'A yearly reset requires {YYYY} or {YY} in the prefix or suffix.',
  },
  'fr': {
    'check': 'Veuillez vérifier les champs signalés.',
    'required': 'Veuillez remplir ce champ.',
    'failed': 'Enregistrement impossible. Veuillez réessayer.',
    'network':
        'Connexion au serveur impossible. Vos saisies sont conservées. Veuillez réessayer.',
    'key':
        'Commencez par une minuscule. Utilisez uniquement des minuscules, chiffres et traits de soulignement (80 caractères maximum).',
    'name': 'Saisissez un nom de 160 caractères maximum.',
    'color': 'Saisissez une couleur au format #2563EB.',
    'options':
        'Saisissez 1 à 50 réponses distinctes, une par ligne (160 caractères maximum chacune).',
    'order': 'Saisissez un entier entre 0 et 10000.',
    'padding': 'Saisissez un entier entre 1 et 12.',
    'start': 'Saisissez un entier entre 1 et 999999999999.',
    'token': '40 caractères maximum. Variables autorisées : {YYYY} et {YY}.',
    'year':
        'Le redémarrage annuel nécessite {YYYY} ou {YY} dans le préfixe ou suffixe.',
  },
  'ar': {
    'check': 'يرجى مراجعة الحقول المحددة.',
    'required': 'يرجى تعبئة هذا الحقل.',
    'failed': 'تعذر الحفظ. يرجى المحاولة مجددًا.',
    'network':
        'تعذر الاتصال بالخادم. تم الاحتفاظ بمدخلاتك. يرجى المحاولة مجددًا.',
    'key':
        'ابدأ بحرف لاتيني صغير واستخدم الحروف الصغيرة والأرقام والشرطة السفلية فقط، بحد أقصى 80 حرفًا.',
    'name': 'أدخل اسمًا لا يتجاوز 160 حرفًا.',
    'color': 'أدخل لونًا بالتنسيق #2563EB.',
    'options':
        'أدخل من 1 إلى 50 إجابة مختلفة، واحدة في كل سطر، وبحد أقصى 160 حرفًا لكل إجابة.',
    'order': 'أدخل عددًا صحيحًا من 0 إلى 10000.',
    'padding': 'أدخل عددًا صحيحًا من 1 إلى 12.',
    'start': 'أدخل عددًا صحيحًا من 1 إلى 999999999999.',
    'token': '40 حرفًا كحد أقصى. العناصر المسموحة: {YYYY} و{YY}.',
    'year':
        'تتطلب إعادة التعيين السنوية وجود {YYYY} أو {YY} في البادئة أو اللاحقة.',
  },
};

void showFormFeedback(BuildContext context, [String? message]) {
  final messenger = ScaffoldMessenger.maybeOf(context);
  messenger?.hideCurrentSnackBar();
  messenger?.showSnackBar(
    SnackBar(
      content: Text(message ?? formMessage(context, 'check')),
      duration: const Duration(seconds: 5),
    ),
  );
}

/// A drop-in Form: preserves `GlobalKey<FormState>`, validators and navigation.
/// Invalid saves give feedback and reveal the first invalid field.
class AirmiusForm extends Form {
  const AirmiusForm({
    super.key,
    required super.child,
    super.onChanged,
    super.autovalidateMode,
    super.canPop,
    super.onPopInvokedWithResult,
  });

  @override
  FormState createState() => _AirmiusFormState();
}

class _AirmiusFormState extends FormState {
  @override
  bool validate() {
    final invalid = super.validateGranularly();
    if (invalid.isEmpty) return true;
    showFormFeedback(context);
    final first = invalid.first;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && first.mounted) {
        Scrollable.ensureVisible(
          first.context,
          alignment: .15,
          duration: MediaQuery.disableAnimationsOf(context)
              ? Duration.zero
              : const Duration(milliseconds: 250),
        );
      }
    });
    return false;
  }
}

String formErrorMessage(BuildContext context, Object error) =>
    error is AirmiusApiException && error.statusCode != 0
    ? error.userMessage
    : formMessage(context, 'network');

/// Extract field errors through the same sanitization as general API errors.
Map<String, String> formServerErrors(Object error) {
  if (error is! AirmiusApiException || error.statusCode != 422) return {};
  try {
    final json = jsonDecode(error.body);
    final errors = json is Map ? json['errors'] : null;
    if (errors is! Map) return {};
    return {
      for (final entry in errors.entries)
        '${entry.key}': AirmiusApiException(
          statusCode: 422,
          path: error.path,
          body: jsonEncode({
            'errors': {entry.key: entry.value},
          }),
        ).userMessage,
    };
  } catch (_) {
    return {};
  }
}
