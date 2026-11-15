import { useI18n } from 'vue-i18n'
export const locationCopy = {
  "de": {
    "unavailable": "Adresssuche derzeit nicht erreichbar. Bitte erneut versuchen oder die Adresse manuell eingeben.",
    "choose": "Bitte einen Ort aus den Vorschlägen für die Umkreissuche auswählen.",
    "empty": "Kein Ort gefunden. Suche mit Stadt, PLZ und Land genauer.",
    "loading": "Orte werden gesucht …",
    "radius": "Umkreis",
    "exact": "Ohne Umkreis",
    "hint": "Umkreis als Luftlinie um die Ortsmitte. Fahrten ohne Ortsposition werden dabei nicht angezeigt.",
    "address": "Adresse oder Treffpunkt suchen",
    "area": "PLZ oder Stadt suchen",
    "unmapped": "Die Fahrt ist gespeichert. Für die Umkreissuche konnte noch keine Ortsposition ermittelt werden."
  },
  "en": {
    "unavailable": "Address search is unavailable. Try again or enter the address manually.",
    "choose": "Select a location from the suggestions to search nearby.",
    "empty": "No location found. Add city, postcode and country.",
    "loading": "Searching locations …",
    "radius": "Radius",
    "exact": "No radius",
    "hint": "Straight-line radius around the locality centre. Rides without a location are excluded.",
    "address": "Search address or meeting point",
    "area": "Search postcode or city",
    "unmapped": "The ride is saved. A locality position for nearby search is not available yet."
  },
  "fr": {
    "unavailable": "La recherche d’adresse est indisponible. Réessayez ou saisissez l’adresse manuellement.",
    "choose": "Sélectionnez un lieu proposé pour rechercher à proximité.",
    "empty": "Aucun lieu trouvé. Précisez la ville, le code postal et le pays.",
    "loading": "Recherche de lieux …",
    "radius": "Rayon",
    "exact": "Sans rayon",
    "hint": "Rayon à vol d’oiseau autour du centre de la localité. Les trajets sans position sont exclus.",
    "address": "Rechercher une adresse ou un rendez-vous",
    "area": "Rechercher un code postal ou une ville",
    "unmapped": "Le trajet est enregistré. Sa position pour la recherche à proximité reste indisponible."
  },
  "ar": {
    "unavailable": "البحث عن العنوان غير متاح. حاول مجدداً أو أدخل العنوان يدوياً.",
    "choose": "اختر مكاناً من الاقتراحات للبحث ضمن النطاق.",
    "empty": "لم يتم العثور على مكان. أضف المدينة والرمز البريدي والبلد.",
    "loading": "جار البحث عن الأماكن…",
    "radius": "النطاق",
    "exact": "دون نطاق",
    "hint": "المسافة بخط مستقيم من وسط المنطقة. لا تظهر الرحلات التي لا تملك موقعاً.",
    "address": "ابحث عن عنوان أو نقطة لقاء",
    "area": "ابحث عن رمز بريدي أو مدينة",
    "unmapped": "تم حفظ الرحلة. لم يتوفر موقع للبحث ضمن النطاق بعد."
  }
}
export function useRideLocationCopy() { const { locale } = useI18n(); return key => (locationCopy[locale.value.split('-')[0]] || locationCopy.de)[key] || key }
