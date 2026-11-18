import 'package:flutter/widgets.dart';
import 'airmius_l10n.dart';

const ridePricingCopy = <String, Map<String, String>>{
  "de": {
    "mode": "Kosten der Mitfahrt",
    "free": "Kostenlos",
    "contribution": "Kostenbeteiligung",
    "amount": "Betrag pro Person",
    "currency": "Währung",
    "country_hint":
        "Währung aus deinem Kontoland vorgeschlagen; für internationale Fahrten änderbar.",
    "per_person": "pro Person · einfache Fahrt",
    "unspecified": "Kosten noch nicht angegeben",
    "direct_payment":
        "Bezahlung direkt beim Fahrer. Keine Zahlung über Airmius.",
    "all_included":
        "Der Betrag gilt für die ganze angebotene Strecke einschließlich vereinbarter Maut- und Fährkosten.",
    "acknowledge":
        "Ich habe die Kosten der Mitfahrt gelesen und stimme ihnen zu.",
    "changed":
        "Die Kosten dieser Fahrt wurden geändert. Bitte prüfe die neuen Konditionen.",
    "agreed": "Bisher vereinbart",
    "proposed": "Neue Konditionen",
    "confirm": "Neue Konditionen bestätigen",
    "decline": "Änderung ablehnen und Platz freigeben",
    "decline_hint":
        "Bei Ablehnung wird deine Zusage oder offene Anfrage zurückgezogen und dein Platz freigegeben.",
    "awaiting_confirmation":
        "Der Mitfahrer muss die neuen Kosten zuerst bestätigen.",
    "invalid_amount":
        "Bitte einen positiven Betrag mit höchstens :digits Nachkommastellen eingeben.",
    "price_changed":
        "Die Kosten haben sich geändert. Bitte die Fahrt aktualisieren und die aktuellen Konditionen ausdrücklich bestätigen.",
    "confirmed": "Die neuen Konditionen wurden bestätigt.",
    "confirmed_title": "Neue Fahrtkosten bestätigt",
    "confirmed_body": ":name hat die neuen Konditionen deiner Fahrt bestätigt.",
    "choose_mode": "Bitte Kostenlos oder Kostenbeteiligung auswählen.",
    "update_warning":
        "Wenn du Kosten oder Währung änderst, müssen betroffene Mitfahrer erneut bestätigen. Ihre bisherige Vereinbarung bleibt bis dahin erhalten.",
    "unavailable_catalog":
        "Die Währungsauswahl konnte nicht geladen werden. Bitte aktualisieren.",
    "review": "Kosten vor der Anfrage prüfen",
    "search_currency": "Währung suchen",
    "own_pending": "Bestätigung der Mitfahrer ausstehend",
  },
  "en": {
    "mode": "Ride costs",
    "free": "Free",
    "contribution": "Cost contribution",
    "amount": "Amount per person",
    "currency": "Currency",
    "country_hint":
        "Suggested from your account country; you can change it for international trips.",
    "per_person": "per person · one-way trip",
    "unspecified": "Costs not specified yet",
    "direct_payment": "Pay the driver directly. No payment through Airmius.",
    "all_included":
        "The amount covers the entire offered route, including agreed toll and ferry costs.",
    "acknowledge": "I have read and agree to the ride costs.",
    "changed":
        "The costs of this ride have changed. Please review the new terms.",
    "agreed": "Previously agreed",
    "proposed": "New terms",
    "confirm": "Confirm new terms",
    "decline": "Decline change and release seat",
    "decline_hint":
        "Declining withdraws your booking or pending request and releases your seat.",
    "awaiting_confirmation": "The passenger must confirm the new costs first.",
    "invalid_amount":
        "Enter a positive amount with at most :digits decimal places.",
    "price_changed":
        "The costs have changed. Refresh the ride and explicitly confirm the current terms.",
    "confirmed": "The new terms have been confirmed.",
    "confirmed_title": "New ride costs confirmed",
    "confirmed_body": ":name confirmed the new terms of your ride.",
    "choose_mode": "Choose Free or Cost contribution.",
    "update_warning":
        "Changing the costs or currency requires passengers to confirm again. Their previous agreement remains until then.",
    "unavailable_catalog":
        "The currency list could not be loaded. Please refresh.",
    "review": "Review costs before requesting",
    "search_currency": "Search currency",
    "own_pending": "Awaiting passenger confirmation",
  },
  "fr": {
    "mode": "Coût du trajet",
    "free": "Gratuit",
    "contribution": "Participation aux frais",
    "amount": "Montant par personne",
    "currency": "Devise",
    "country_hint":
        "Devise proposée selon le pays de votre compte ; modifiable pour les trajets internationaux.",
    "per_person": "par personne · aller simple",
    "unspecified": "Coût non précisé",
    "direct_payment":
        "Paiement directement au conducteur. Aucun paiement via Airmius.",
    "all_included":
        "Le montant couvre tout le trajet proposé, y compris les péages et ferries convenus.",
    "acknowledge": "J’ai lu et j’accepte le coût du trajet.",
    "changed":
        "Le coût de ce trajet a changé. Vérifiez les nouvelles conditions.",
    "agreed": "Conditions précédemment acceptées",
    "proposed": "Nouvelles conditions",
    "confirm": "Confirmer les nouvelles conditions",
    "decline": "Refuser et libérer ma place",
    "decline_hint":
        "Le refus retire votre réservation ou demande en attente et libère votre place.",
    "awaiting_confirmation":
        "Le passager doit d’abord confirmer les nouveaux frais.",
    "invalid_amount":
        "Saisissez un montant positif avec au maximum :digits décimales.",
    "price_changed":
        "Le coût a changé. Actualisez le trajet et confirmez explicitement les conditions actuelles.",
    "confirmed": "Les nouvelles conditions ont été confirmées.",
    "confirmed_title": "Nouveaux frais confirmés",
    "confirmed_body":
        ":name a confirmé les nouvelles conditions de votre trajet.",
    "choose_mode": "Choisissez Gratuit ou Participation aux frais.",
    "update_warning":
        "Une modification du coût ou de la devise nécessite une nouvelle confirmation des passagers. Leur accord précédent reste valable jusque-là.",
    "unavailable_catalog":
        "Impossible de charger les devises. Actualisez la page.",
    "review": "Vérifier le coût avant la demande",
    "search_currency": "Rechercher une devise",
    "own_pending": "Confirmation des passagers en attente",
  },
  "ar": {
    "mode": "تكلفة الرحلة",
    "free": "مجانية",
    "contribution": "المساهمة في التكاليف",
    "amount": "المبلغ لكل شخص",
    "currency": "العملة",
    "country_hint":
        "العملة مقترحة حسب بلد حسابك ويمكن تغييرها للرحلات الدولية.",
    "per_person": "لكل شخص · رحلة باتجاه واحد",
    "unspecified": "لم تُحدد التكلفة بعد",
    "direct_payment": "الدفع مباشرة للسائق. لا يتم الدفع عبر Airmius.",
    "all_included":
        "يشمل المبلغ المسار المعروض كاملاً ورسوم الطرق والعبّارات المتفق عليها.",
    "acknowledge": "قرأت تكلفة الرحلة وأوافق عليها.",
    "changed": "تغيرت تكلفة هذه الرحلة. يرجى مراجعة الشروط الجديدة.",
    "agreed": "الشروط المتفق عليها سابقاً",
    "proposed": "الشروط الجديدة",
    "confirm": "تأكيد الشروط الجديدة",
    "decline": "رفض التغيير وإخلاء المقعد",
    "decline_hint": "يؤدي الرفض إلى سحب الحجز أو الطلب المعلق وإخلاء مقعدك.",
    "awaiting_confirmation": "يجب أن يؤكد الراكب التكاليف الجديدة أولاً.",
    "invalid_amount": "أدخل مبلغاً موجباً بما لا يزيد عن :digits منازل عشرية.",
    "price_changed": "تغيرت التكلفة. حدّث الرحلة وأكد الشروط الحالية صراحةً.",
    "confirmed": "تم تأكيد الشروط الجديدة.",
    "confirmed_title": "تم تأكيد تكاليف الرحلة الجديدة",
    "confirmed_body": "أكد :name الشروط الجديدة لرحلتك.",
    "choose_mode": "اختر مجانية أو المساهمة في التكاليف.",
    "update_warning":
        "يتطلب تغيير التكلفة أو العملة تأكيد الركاب مجدداً. يبقى اتفاقهم السابق سارياً حتى ذلك الحين.",
    "unavailable_catalog": "تعذر تحميل قائمة العملات. يرجى التحديث.",
    "review": "مراجعة التكلفة قبل إرسال الطلب",
    "search_currency": "البحث عن عملة",
    "own_pending": "بانتظار تأكيد الركاب",
  },
};
String ridePricingText(BuildContext context, String key) =>
    (ridePricingCopy[AirmiusScope.of(context).language.code.toLowerCase()] ??
        ridePricingCopy['en']!)[key] ??
    key;
