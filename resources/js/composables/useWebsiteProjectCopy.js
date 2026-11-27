import { useI18n } from 'vue-i18n'

const copy = {
    'Entwurf': ['Draft', 'Brouillon', 'مسودة'],
    'Als Entwurf speichern': ['Save as draft', 'Enregistrer comme brouillon', 'حفظ كمسودة'],
    'Anfrage bearbeiten': ['Edit request', 'Modifier la demande', 'تعديل الطلب'],
    'Anfrage absenden': ['Submit request', 'Envoyer la demande', 'إرسال الطلب'],
    'Anfrage zurückziehen': ['Withdraw request', 'Retirer la demande', 'سحب الطلب'],
    'Anfrage wirklich zurückziehen? Der Verlauf bleibt erhalten.': ['Withdraw this request? The history will be retained.', 'Retirer la demande ? L’historique sera conservé.', 'هل تريد سحب الطلب؟ سيبقى السجل محفوظاً.'],
    'Zurückgezogen': ['Withdrawn', 'Retirée', 'تم السحب'],
    'Änderungen speichern': ['Save changes', 'Enregistrer les modifications', 'حفظ التعديلات'],
    'Abbrechen': ['Cancel', 'Annuler', 'إلغاء'],
    'Dateien hinzufügen': ['Attach files', 'Ajouter des fichiers', 'إرفاق ملفات'],
    'Maximal 5 Dateien, jeweils bis 10 MB.': ['Up to 5 files, each up to 10 MB.', '5 fichiers maximum, jusqu’à 10 Mo chacun.', 'حتى 5 ملفات، بحد أقصى 10 ميغابايت لكل ملف.'],
    'Anfrage bearbeitet': ['Request edited', 'Demande modifiée', 'تم تعديل الطلب'],
    'Speichern fehlgeschlagen. Bitte erneut versuchen.': ['Saving failed. Please try again.', 'Échec de l’enregistrement. Réessayez.', 'فشل الحفظ. يرجى المحاولة مجدداً.'],
    'Die Anfrage ist für Änderungen gesperrt. Rückfragen sind über die Kommentare möglich, solange das Projekt offen ist.': ['Editing is locked. You can use comments while the project is open.', 'La modification est verrouillée. Les commentaires restent disponibles tant que le projet est ouvert.', 'التعديل مقفل. يمكنك التعليق ما دام المشروع مفتوحاً.'],
    'Projekttitel': ['Project title', 'Titre du projet', 'عنوان المشروع'],
    'Zielgruppe': ['Target audience', 'Public cible', 'الجمهور المستهدف'],
    'Inspirationswebsites': ['Inspiration websites', 'Sites d’inspiration', 'مواقع للإلهام'],
    'Bis zu fünf vollständige HTTP-/HTTPS-Links, ein Link pro Zeile.': ['Up to five complete HTTP/HTTPS links, one per line.', 'Jusqu’à cinq liens HTTP/HTTPS complets, un par ligne.', 'حتى خمسة روابط HTTP/HTTPS كاملة، رابط في كل سطر.'],
    'Unverbindliche Anfrage: Es wird kein kostenpflichtiges Paket automatisch aktiviert. Beschreibe Ziele, Zielgruppe und gewünschte Funktionen.': ['Non-binding request: no paid package is activated automatically. Describe your goals, audience and desired features.', 'Demande sans engagement : aucun forfait payant n’est activé automatiquement. Décrivez vos objectifs, votre public et les fonctions souhaitées.', 'طلب غير ملزم: لا يتم تفعيل أي باقة مدفوعة تلقائياً. صف الأهداف والجمهور والميزات المطلوبة.'],
    'Verein': ['Club', 'Club', 'النادي'],
    'Gewünschte Domain': ['Preferred domain', 'Domaine souhaité', 'النطاق المطلوب'],
    'Ziele und Funktionen': ['Goals and features', 'Objectifs et fonctionnalités', 'الأهداف والميزات'],
    'Weitere Hinweise': ['Additional notes', 'Remarques complémentaires', 'ملاحظات إضافية'],
    'Projekt und Nachrichten öffnen': ['Open project and messages', 'Ouvrir le projet et les messages', 'فتح المشروع والرسائل'],
    'Projekt und Nachrichten': ['Project and messages', 'Projet et messages', 'المشروع والرسائل'],
    'Projektstatus ändern': ['Change project status', 'Modifier le statut du projet', 'تغيير حالة المشروع'],
    'Website-Projekt': ['Website project', 'Projet de site web', 'مشروع الموقع'],
    'Aktualisieren': ['Refresh', 'Actualiser', 'تحديث'],
    'Anfrage eingegangen': ['Request received', 'Demande reçue', 'تم استلام الطلب'],
    'In Klärung': ['Discussing requirements', 'Clarification en cours', 'قيد التوضيح'],
    'Angebot erstellt': ['Quote prepared', 'Devis établi', 'تم إعداد العرض'],
    'In Umsetzung': ['In progress', 'En réalisation', 'قيد التنفيذ'],
    'Abgeschlossen': ['Completed', 'Terminé', 'مكتمل'],
    'Storniert': ['Cancelled', 'Annulé', 'ملغى'],
    'Anfrage → Rückfragen → Angebot → Umsetzung → Abschluss': ['Request → Questions → Quote → Implementation → Completion', 'Demande → Questions → Devis → Réalisation → Clôture', 'طلب ← أسئلة ← عرض ← تنفيذ ← إتمام'],
    'Erstellt': ['Created', 'Créé', 'تاريخ الإنشاء'],
    'Unverbindliche Anfrage, keine automatische kostenpflichtige Aktivierung.': ['Non-binding request, no automatic paid activation.', 'Demande sans engagement, sans activation payante automatique.', 'طلب غير ملزم دون تفعيل مدفوع تلقائي.'],
    'Noch nicht angegeben': ['Not yet specified', 'Non renseigné', 'لم يحدد بعد'],
    'Fragen und Kommentare': ['Questions and comments', 'Questions et commentaires', 'الأسئلة والتعليقات'],
    'Sichtbar für die berechtigten Projektbeteiligten und die Administration. Neue Antworten erscheinen nach dem Aktualisieren.': ['Visible to authorized project participants and administrators. Refresh to see new replies.', 'Visible par les participants autorisés et l’administration. Actualisez pour voir les nouvelles réponses.', 'مرئي للمشاركين المخولين والإدارة. حدّث الصفحة لرؤية الردود الجديدة.'],
    'Deine Nachricht': ['Your message', 'Votre message', 'رسالتك'],
    'Nachricht senden': ['Send message', 'Envoyer le message', 'إرسال الرسالة'],
    'Noch keine Nachrichten. Stelle hier deine erste Frage.': ['No messages yet. Ask your first question here.', 'Aucun message. Posez votre première question ici.', 'لا توجد رسائل بعد. اطرح سؤالك الأول هنا.'],
    'Projektteam': ['Project team', 'Équipe du projet', 'فريق المشروع'],
    'Status geändert': ['Status changed', 'Statut modifié', 'تم تغيير الحالة'],
    'Ältere Einträge laden': ['Load older entries', 'Charger les entrées précédentes', 'تحميل الإدخالات الأقدم'],
    'Identität bestätigen, dann Nachricht erneut senden': ['Verify your identity, then resend the message', 'Confirmez votre identité, puis renvoyez le message', 'أكد هويتك ثم أعد إرسال الرسالة'],
    'Das Projekt konnte nicht geladen werden. Bitte aktualisieren.': ['Could not load the project. Please refresh.', 'Impossible de charger le projet. Veuillez actualiser.', 'تعذر تحميل المشروع. يرجى التحديث.'],
    'Nachricht nicht bestätigt. Bitte erneut senden; Wiederholungen werden nicht doppelt gespeichert.': ['Message not confirmed. Please retry; retries are not saved twice.', 'Message non confirmé. Réessayez ; les nouvelles tentatives ne créent pas de doublons.', 'لم يتم تأكيد الرسالة. أعد المحاولة؛ لا يتم حفظ المحاولات المكررة مرتين.'],
}

export function useWebsiteProjectCopy() {
    const { locale } = useI18n()
    return text => {
        const language = String(locale.value || 'de').split('-')[0]
        return language === 'de' ? text : copy[text]?.[{ en: 0, fr: 1, ar: 2 }[language] ?? 0] || text
    }
}
