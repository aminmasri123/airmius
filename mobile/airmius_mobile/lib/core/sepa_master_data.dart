// Keep national lengths aligned with App\Support\SepaData.
class SepaMasterData {
  static const ibanLengths = <String, int>{
    'AD': 24,
    'AE': 23,
    'AL': 28,
    'AT': 20,
    'AZ': 28,
    'BA': 20,
    'BE': 16,
    'BG': 22,
    'BH': 22,
    'BI': 27,
    'BR': 29,
    'BY': 28,
    'CH': 21,
    'CR': 22,
    'CY': 28,
    'CZ': 24,
    'DE': 22,
    'DJ': 27,
    'DK': 18,
    'DO': 28,
    'EE': 20,
    'EG': 29,
    'ES': 24,
    'FI': 18,
    'FK': 18,
    'FO': 18,
    'FR': 27,
    'GB': 22,
    'GE': 22,
    'GI': 23,
    'GL': 18,
    'GR': 27,
    'GT': 28,
    'HR': 21,
    'HU': 28,
    'IE': 22,
    'IL': 23,
    'IQ': 23,
    'IS': 26,
    'IT': 27,
    'JO': 30,
    'KW': 30,
    'KZ': 20,
    'LB': 28,
    'LC': 32,
    'LI': 21,
    'LT': 20,
    'LU': 20,
    'LV': 21,
    'LY': 25,
    'MC': 27,
    'MD': 24,
    'ME': 22,
    'MK': 19,
    'MN': 20,
    'MR': 27,
    'MT': 31,
    'MU': 30,
    'NI': 28,
    'NL': 18,
    'NO': 15,
    'OM': 23,
    'PK': 24,
    'PL': 28,
    'PS': 29,
    'PT': 25,
    'QA': 29,
    'RO': 24,
    'RS': 22,
    'RU': 33,
    'SA': 24,
    'SC': 31,
    'SD': 18,
    'SE': 24,
    'SI': 19,
    'SK': 24,
    'SM': 27,
    'SO': 23,
    'ST': 25,
    'SV': 28,
    'TL': 23,
    'TN': 24,
    'TR': 26,
    'UA': 29,
    'VA': 22,
    'VG': 24,
    'XK': 20,
  };
  static String normalize(String value) =>
      value.replaceAll(RegExp(r'\s+'), '').toUpperCase();
  static bool checksum(String value) {
    var remainder = 0;
    for (final rune in value.runes) {
      final digits = rune >= 65
          ? (rune - 55).toString()
          : String.fromCharCode(rune);
      for (final digit in digits.runes) {
        remainder = (remainder * 10 + digit - 48) % 97;
      }
    }
    return remainder == 1;
  }

  static bool validIban(String raw) {
    final value = normalize(raw);
    return RegExp(r'^[A-Z]{2}[0-9]{2}[A-Z0-9]+$').hasMatch(value) &&
        ibanLengths[value.substring(0, 2)] == value.length &&
        checksum(value.substring(4) + value.substring(0, 4));
  }

  static bool validBic(String raw) =>
      RegExp(r'^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$').hasMatch(normalize(raw));
  static bool validCreditorId(String raw) {
    final value = normalize(raw);
    if (!RegExp(
      r'^[A-Z]{2}[0-9]{2}[A-Z0-9]{3}[A-Z0-9]{1,28}$',
    ).hasMatch(value)) {
      return false;
    }
    if (value.startsWith('DE') &&
        !RegExp(r'^DE[0-9]{2}[A-Z0-9]{3}0[0-9]{10}$').hasMatch(value)) {
      return false;
    }
    return checksum(value.substring(7) + value.substring(0, 4));
  }

  static String? bankError({
    required String iban,
    required String bic,
    String? creditorId,
  }) {
    if (iban.trim().isNotEmpty && !validIban(iban)) return 'iban';
    if (bic.trim().isNotEmpty && !validBic(bic)) return 'bic';
    if (creditorId != null &&
        creditorId.trim().isNotEmpty &&
        !validCreditorId(creditorId)) {
      return 'creditor';
    }
    return null;
  }

  static String? mandateError({
    required String iban,
    required String bic,
    required String reference,
    required String signedOn,
    required bool active,
    DateTime? today,
  }) {
    final bank = bankError(iban: iban, bic: bic);
    if (bank != null) return bank;
    if (active &&
        (iban.trim().isEmpty ||
            reference.trim().isEmpty ||
            signedOn.trim().isEmpty)) {
      return 'required';
    }
    if (reference.isNotEmpty &&
        (!RegExp(r"^[A-Za-z0-9+?/'():,. -]{1,35}$").hasMatch(reference) ||
            reference.trim().isEmpty)) {
      return 'reference';
    }
    if (signedOn.isNotEmpty) {
      final date = DateTime.tryParse(signedOn);
      final now = today ?? DateTime.now();
      if (!RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(signedOn) ||
          date == null ||
          date.toIso8601String().substring(0, 10) != signedOn ||
          date.isAfter(DateTime(now.year, now.month, now.day))) {
        return 'date';
      }
    }
    return null;
  }
}

const sepaMasterDataLabels = <String, Map<String, String>>{
  'de': {
    'iban': 'IBAN ungültig: Bitte Ländercode, Länge und Prüfziffer prüfen.',
    'bic':
        'BIC ungültig: 8 oder 11 Zeichen eingeben oder das optionale Feld leer lassen.',
    'creditor': 'Gläubiger-ID ungültig: Bitte Kennung und Prüfziffer prüfen.',
    'required':
        'Für ein aktives Mandat sind IBAN, Mandatsreferenz und Unterschriftsdatum erforderlich.',
    'reference':
        'Mandatsreferenz: 1 bis 35 SEPA-Zeichen; keine Umlaute oder Sonderzeichen wie < >.',
    'date':
        'Mandatsdatum: Ein gültiges Unterschriftsdatum bis einschließlich heute eingeben.',
    'settingsHint':
        'Für den Einzug sind gültige Gläubiger-ID und Vereins-IBAN erforderlich. Ohne Kontoinhaber wird der Vereinsname verwendet. BIC ist optional. Leere Stammdaten können als Entwurf gespeichert werden; der Einzug bleibt gesperrt.',
    'mandateHint':
        'Nur mit vorliegendem unterschriebenem Mandat aktivieren. Die Referenz muss je Zahlungspflichtigem eindeutig sein. Bei abweichendem Beitragszahler werden dessen Name und Mandat für seine Rechnung verwendet; die IBAN gehört zu diesem Zahlungspflichtigen.',
    'bicHint': 'Optional: 8 oder 11 Zeichen.',
  },
  'en': {
    'iban': 'Invalid IBAN: check country code, length and checksum.',
    'bic':
        'Invalid BIC: enter 8 or 11 characters, or leave this optional field empty.',
    'creditor': 'Invalid creditor ID: check identifier and checksum.',
    'required':
        'An active mandate requires an IBAN, reference and signature date.',
    'reference':
        'Mandate reference: 1 to 35 SEPA characters; no accented letters or characters such as < >.',
    'date': 'Enter a valid signature date no later than today.',
    'settingsHint':
        'Collection requires a valid creditor ID and club IBAN. The club name is used if the account holder is empty. BIC is optional. Empty details can be saved as a draft; collection remains blocked.',
    'mandateHint':
        'Activate only with a signed mandate. Use a unique reference per payer. For an alternate contribution payer, their name and mandate are used for their invoice; the IBAN belongs to that payer.',
    'bicHint': 'Optional: 8 or 11 characters.',
  },
  'fr': {
    'iban': 'IBAN invalide : vérifiez le pays, la longueur et la clé.',
    'bic': 'BIC invalide : saisissez 8 ou 11 caractères ou laissez vide.',
    'creditor':
        'Identifiant créancier invalide : vérifiez la référence et la clé.',
    'required':
        'Un mandat actif nécessite un IBAN, une référence et la date de signature.',
    'reference':
        'Référence : 1 à 35 caractères SEPA, sans accents ni caractères tels que < >.',
    'date': 'Saisissez une date de signature valide au plus tard aujourd’hui.',
    'settingsHint':
        'Le prélèvement nécessite un identifiant créancier et un IBAN valides. Sans titulaire, le nom du club est utilisé. BIC facultatif. Les données vides peuvent être enregistrées en brouillon ; le prélèvement reste bloqué.',
    'mandateHint':
        'Activez uniquement avec un mandat signé et une référence unique par payeur. Pour un autre payeur, son nom, son IBAN et son mandat sont utilisés pour sa facture.',
    'bicHint': 'Facultatif : 8 ou 11 caractères.',
  },
  'ar': {
    'iban': 'رقم IBAN غير صالح: تحقق من البلد والطول ورقم التحقق.',
    'bic':
        'رمز BIC غير صالح: أدخل 8 أو 11 حرفًا أو اترك الحقل الاختياري فارغًا.',
    'creditor': 'معرف الدائن غير صالح: تحقق من المعرف ورقم التحقق.',
    'required': 'يتطلب التفويض النشط رقم IBAN ومرجعًا وتاريخ التوقيع.',
    'reference': 'مرجع التفويض: من 1 إلى 35 رمز SEPA صالحًا دون رموز مثل < >.',
    'date': 'أدخل تاريخ توقيع صالحًا لا يتجاوز اليوم.',
    'settingsHint':
        'يتطلب الخصم معرف دائن ورقم IBAN صالحين. يستخدم اسم النادي عند ترك صاحب الحساب فارغًا. BIC اختياري. يمكن حفظ البيانات الفارغة كمسودة مع حظر الخصم.',
    'mandateHint':
        'فعّل فقط بوجود تفويض موقع ومرجع فريد لكل دافع. عند اختيار دافع آخر، يستخدم اسمه ورقم IBAN وتفويضه لفاتورته.',
    'bicHint': 'اختياري: 8 أو 11 حرفًا.',
  },
};
