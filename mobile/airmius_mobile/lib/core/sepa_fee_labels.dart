// Shared wording with resources/js/i18n/sepaFeeLocalization.json.
const Map<String, Map<String, String>> sepaFeeLabels = {
  "de": {
    "title": "Bankgebühr buchen",
    "help":
        "Die Gebühr wird als eigene Bankausgabe erfasst. Die Beitragsforderung bleibt unverändert. Bereits erfasste Ausgaben bitte verknüpfen.",
    "choice": "Buchungsweg auswählen",
    "existing": "Vorhandene Ausgabe verknüpfen",
    "new": "Neue Ausgabe anlegen",
    "reference": "Bankreferenz",
    "search": "Ausgaben suchen",
    "empty": "Keine passenden unverknüpften Bankausgaben gefunden.",
    "previous": "Zurück",
    "next": "Weiter",
    "selected": "Ausgewählte Ausgabe",
    "amount": "Gebühr in EUR",
    "date": "Buchungsdatum",
    "confirm":
        "Ich habe Betrag, Bankdatum und Referenz geprüft und bestätige die Buchung beziehungsweise Verknüpfung.",
    "link": "Ausgabe verknüpfen",
    "save": "Ausgabe buchen",
    "recorded": "Gebühr gebucht",
    "error":
        "Die Gebühr konnte nicht gespeichert werden. Bitte den Stand prüfen.",
    "exportAccount": "Aufwandskonto für Rücklastschriftgebühren",
    "exportAccountHelp":
        "Kein Standardkonto: Bitte das fachlich festgelegte Aufwandskonto eintragen. Gebuchte Gebühren werden sonst nicht exportiert.",
  },
  "en": {
    "title": "Record bank fee",
    "help":
        "The fee is a separate bank expense. Member debt stays unchanged. Link expenses that are already recorded.",
    "choice": "Choose booking method",
    "existing": "Link existing expense",
    "new": "Create new expense",
    "reference": "Bank reference",
    "search": "Search expenses",
    "empty": "No matching unlinked bank expenses found.",
    "previous": "Previous",
    "next": "Next",
    "selected": "Selected expense",
    "amount": "Fee in EUR",
    "date": "Booking date",
    "confirm":
        "I checked the amount, bank date and reference and confirm this booking or link.",
    "link": "Link expense",
    "save": "Record expense",
    "recorded": "Fee recorded",
    "error": "The fee could not be saved. Check the current status.",
    "exportAccount": "Expense account for returned-debit fees",
    "exportAccountHelp":
        "No default account: enter the agreed expense account. Recorded fees cannot be exported without it.",
  },
  "fr": {
    "title": "Enregistrer les frais bancaires",
    "help":
        "Les frais constituent une dépense bancaire distincte. La dette du membre reste inchangée. Associez les dépenses déjà enregistrées.",
    "choice": "Choisir le mode",
    "existing": "Associer une dépense existante",
    "new": "Créer une dépense",
    "reference": "Référence bancaire",
    "search": "Rechercher des dépenses",
    "empty": "Aucune dépense bancaire disponible correspondante.",
    "previous": "Précédent",
    "next": "Suivant",
    "selected": "Dépense sélectionnée",
    "amount": "Frais en EUR",
    "date": "Date comptable",
    "confirm":
        "J’ai vérifié le montant, la date bancaire et la référence et je confirme cette écriture ou association.",
    "link": "Associer la dépense",
    "save": "Enregistrer la dépense",
    "recorded": "Frais enregistrés",
    "error":
        "Les frais n’ont pas pu être enregistrés. Vérifiez la situation actuelle.",
    "exportAccount": "Compte de charges des frais de rejet",
    "exportAccountHelp":
        "Aucun compte par défaut : renseignez le compte de charges défini. Les frais enregistrés ne peuvent pas être exportés sans ce compte.",
  },
  "ar": {
    "title": "تسجيل الرسوم البنكية",
    "help":
        "تسجل الرسوم كمصروف بنكي منفصل. يبقى دين العضو دون تغيير. اربط المصروفات المسجلة مسبقاً.",
    "choice": "اختر طريقة التسجيل",
    "existing": "ربط مصروف موجود",
    "new": "إنشاء مصروف جديد",
    "reference": "المرجع البنكي",
    "search": "البحث عن المصروفات",
    "empty": "لم توجد مصروفات بنكية مطابقة غير مرتبطة.",
    "previous": "السابق",
    "next": "التالي",
    "selected": "المصروف المحدد",
    "amount": "الرسوم باليورو",
    "date": "تاريخ القيد",
    "confirm": "راجعت المبلغ والتاريخ البنكي والمرجع وأؤكد التسجيل أو الربط.",
    "link": "ربط المصروف",
    "save": "تسجيل المصروف",
    "recorded": "تم تسجيل الرسوم",
    "error": "تعذر حفظ الرسوم. تحقق من الحالة الحالية.",
    "exportAccount": "حساب مصروفات رسوم الخصم المرتجع",
    "exportAccountHelp":
        "لا يوجد حساب افتراضي: أدخل حساب المصروفات المحدد. لا يمكن تصدير الرسوم المسجلة بدونه.",
  },
};
