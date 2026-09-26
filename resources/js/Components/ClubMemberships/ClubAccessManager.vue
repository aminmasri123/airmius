<script setup>
import Modal from '@/Components/Modal.vue'
import { confirmDialog } from '@/services/dialogService'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    show: { type: Boolean, default: false },
    club: { type: Object, default: null },
    member: { type: Object, default: null },
    members: { type: Array, default: () => [] },
})

const emit = defineEmits(['close'])
const { locale } = useI18n()
const copy = {
    de: {
        title: 'Rollen und Zugriff', intro: 'Einzelrechte, anpassbare Rollen und zeitliche Vertretungen für :name verwalten.',
        individual: 'Einzelrechte', roles: 'Rollen', delegations: 'Vertretungen', handover: 'Übergabe', loading: 'Zugriffsdaten werden geladen …',
        retry: 'Erneut laden', close: 'Schließen', save: 'Speichern', saving: 'Wird gespeichert …', saved: 'Änderungen wurden gespeichert.',
        inherited: 'Aus Rollen übernehmen', allow: 'Erlauben', deny: 'Ausschließen', effective: 'Wirksam', yes: 'Ja', no: 'Nein',
        individual_hint: 'Ein Ausschluss hat Vorrang vor festen Rollen, konfigurierten Rollen und Vertretungen.',
        assigned_roles: 'Zugewiesene Rollen', add_assignment: 'Rollenzuweisung hinzufügen', role: 'Rolle', scope: 'Bereich',
        club: 'Ganzer Verein', department: 'Abteilung', team: 'Mannschaft', select_target: 'Bereich auswählen', remove: 'Entfernen',
        role_definitions: 'Rollendefinitionen', new_role: 'Neue Rolle', edit_role: 'Rolle bearbeiten', name: 'Name', key: 'Schlüssel',
        permissions: 'Berechtigungen', active: 'Aktiv', create: 'Anlegen', update: 'Aktualisieren', cancel: 'Abbrechen', delete: 'Löschen',
        assigned_count: ':count Zuweisungen', templates: 'Vorlage übernehmen', no_roles: 'Noch keine anpassbaren Rollen vorhanden.',
        delegation_new: 'Zeitliche Vertretung vergeben', starts_at: 'Beginn', ends_at: 'Ende', maximum: 'Maximal :days Tage',
        grant: 'Vertretung vergeben', revoke: 'Widerrufen', status: 'Status', scheduled: 'Geplant', expired: 'Abgelaufen',
        revoked: 'Widerrufen', current: 'Aktiv', no_delegations: 'Für dieses Mitglied gibt es keine Vertretungen.',
        confirm_delete: 'Diese unbenutzte Rollendefinition wirklich löschen?', confirm_revoke: 'Diese Vertretung sofort widerrufen?',
        load_failed: 'Die Zugriffsdaten konnten nicht geladen werden.', action_failed: 'Die Änderung konnte nicht gespeichert werden.',
        choose_permission: 'Mindestens eine Berechtigung auswählen.', owner_locked: 'Die Rechte des Vereinsinhabers sind geschützt.',
        handover_intro: 'Vor dem Tätigkeitsende wird festgelegt, ob Zugriffe entfernt oder an eine Nachfolge übertragen werden.',
        handover_none: 'Für dieses Mitglied liegt keine Zugriffsprüfung vor.', handover_due: 'Fällig am :date', decision: 'Entscheidung',
        decision_remove: 'Zugriffe ohne Nachfolge entfernen', decision_successor: 'Rollen an Nachfolge übertragen', successor: 'Nachfolge',
        note: 'Begründung', propose: 'Zur zweiten Prüfung vorlegen', approve: 'Als zweite Person freigeben',
        handover_pending: 'Offen', handover_proposed: 'Zur Freigabe vorgelegt', handover_approved: 'Freigegeben',
        handover_applied: 'Umgesetzt', handover_stale: 'Erneute Prüfung erforderlich', second_person_hint: 'Die vorschlagende Person darf nicht selbst freigeben.',
    },
    en: {
        title: 'Roles and access', intro: 'Manage individual permissions, custom roles and temporary delegations for :name.',
        individual: 'Individual permissions', roles: 'Roles', delegations: 'Delegations', handover: 'Handover', loading: 'Loading access data …', retry: 'Reload',
        close: 'Close', save: 'Save', saving: 'Saving …', saved: 'Changes saved.', inherited: 'Use role defaults', allow: 'Allow',
        deny: 'Deny', effective: 'Effective', yes: 'Yes', no: 'No', individual_hint: 'A denial overrides fixed roles, custom roles and delegations.',
        assigned_roles: 'Assigned roles', add_assignment: 'Add role assignment', role: 'Role', scope: 'Scope', club: 'Whole club',
        department: 'Department', team: 'Team', select_target: 'Select scope', remove: 'Remove', role_definitions: 'Role definitions',
        new_role: 'New role', edit_role: 'Edit role', name: 'Name', key: 'Key', permissions: 'Permissions', active: 'Active',
        create: 'Create', update: 'Update', cancel: 'Cancel', delete: 'Delete', assigned_count: ':count assignments',
        templates: 'Use template', no_roles: 'No custom roles yet.', delegation_new: 'Grant temporary delegation', starts_at: 'Starts',
        ends_at: 'Ends', maximum: 'Maximum :days days', grant: 'Grant delegation', revoke: 'Revoke', status: 'Status', scheduled: 'Scheduled',
        expired: 'Expired', revoked: 'Revoked', current: 'Active', no_delegations: 'No delegations for this member.',
        confirm_delete: 'Delete this unused role definition?', confirm_revoke: 'Revoke this delegation immediately?',
        load_failed: 'Access data could not be loaded.', action_failed: 'The change could not be saved.',
        choose_permission: 'Select at least one permission.', owner_locked: 'The club owner permissions are protected.',
        handover_intro: 'Before responsibilities end, decide whether access is removed or transferred to a successor.',
        handover_none: 'There is no access review for this member.', handover_due: 'Due on :date', decision: 'Decision',
        decision_remove: 'Remove access without a successor', decision_successor: 'Transfer roles to a successor', successor: 'Successor',
        note: 'Reason', propose: 'Submit for second review', approve: 'Approve as second person',
        handover_pending: 'Pending', handover_proposed: 'Awaiting approval', handover_approved: 'Approved',
        handover_applied: 'Applied', handover_stale: 'Review again', second_person_hint: 'The person proposing the handover cannot approve it.',
    },
    fr: {
        title: 'Rôles et accès', intro: 'Gérer les droits individuels, les rôles personnalisés et les délégations temporaires de :name.',
        individual: 'Droits individuels', roles: 'Rôles', delegations: 'Délégations', handover: 'Relève', loading: 'Chargement des accès …', retry: 'Recharger',
        close: 'Fermer', save: 'Enregistrer', saving: 'Enregistrement …', saved: 'Modifications enregistrées.', inherited: 'Hériter des rôles',
        allow: 'Autoriser', deny: 'Refuser', effective: 'Effectif', yes: 'Oui', no: 'Non',
        individual_hint: 'Un refus prévaut sur les rôles fixes, les rôles personnalisés et les délégations.', assigned_roles: 'Rôles attribués',
        add_assignment: 'Ajouter une attribution', role: 'Rôle', scope: 'Périmètre', club: 'Tout le club', department: 'Section', team: 'Équipe',
        select_target: 'Choisir le périmètre', remove: 'Retirer', role_definitions: 'Définitions des rôles', new_role: 'Nouveau rôle',
        edit_role: 'Modifier le rôle', name: 'Nom', key: 'Clé', permissions: 'Droits', active: 'Actif', create: 'Créer', update: 'Mettre à jour',
        cancel: 'Annuler', delete: 'Supprimer', assigned_count: ':count attributions', templates: 'Utiliser un modèle', no_roles: 'Aucun rôle personnalisé.',
        delegation_new: 'Accorder une délégation temporaire', starts_at: 'Début', ends_at: 'Fin', maximum: 'Maximum :days jours',
        grant: 'Accorder', revoke: 'Révoquer', status: 'État', scheduled: 'Planifiée', expired: 'Expirée', revoked: 'Révoquée', current: 'Active',
        no_delegations: 'Aucune délégation pour ce membre.', confirm_delete: 'Supprimer cette définition de rôle inutilisée ?',
        confirm_revoke: 'Révoquer immédiatement cette délégation ?', load_failed: 'Impossible de charger les accès.',
        action_failed: 'Impossible d’enregistrer la modification.', choose_permission: 'Sélectionnez au moins un droit.',
        owner_locked: 'Les droits du propriétaire du club sont protégés.',
        handover_intro: 'Avant la fin de fonction, décidez si les accès sont retirés ou transmis à une relève.',
        handover_none: 'Aucune révision des accès ne concerne ce membre.', handover_due: 'Échéance le :date', decision: 'Décision',
        decision_remove: 'Retirer les accès sans relève', decision_successor: 'Transmettre les rôles à une relève', successor: 'Relève',
        note: 'Motif', propose: 'Soumettre à une seconde vérification', approve: 'Approuver comme seconde personne',
        handover_pending: 'Ouverte', handover_proposed: 'En attente d’approbation', handover_approved: 'Approuvée',
        handover_applied: 'Appliquée', handover_stale: 'Nouvelle vérification requise', second_person_hint: 'La personne qui propose ne peut pas approuver elle-même.',
    },
    ar: {
        title: 'الأدوار والصلاحيات', intro: 'إدارة الصلاحيات الفردية والأدوار المخصصة والتفويضات المؤقتة لـ :name.',
        individual: 'الصلاحيات الفردية', roles: 'الأدوار', delegations: 'التفويضات', handover: 'التسليم', loading: 'جارٍ تحميل بيانات الوصول …', retry: 'إعادة التحميل',
        close: 'إغلاق', save: 'حفظ', saving: 'جارٍ الحفظ …', saved: 'تم حفظ التغييرات.', inherited: 'الاعتماد على الأدوار', allow: 'سماح',
        deny: 'منع', effective: 'الفعلي', yes: 'نعم', no: 'لا', individual_hint: 'يتقدم المنع على الأدوار الثابتة والمخصصة والتفويضات.',
        assigned_roles: 'الأدوار المسندة', add_assignment: 'إضافة إسناد دور', role: 'الدور', scope: 'النطاق', club: 'النادي بالكامل',
        department: 'القسم', team: 'الفريق', select_target: 'اختر النطاق', remove: 'إزالة', role_definitions: 'تعريفات الأدوار',
        new_role: 'دور جديد', edit_role: 'تعديل الدور', name: 'الاسم', key: 'المفتاح', permissions: 'الصلاحيات', active: 'نشط',
        create: 'إنشاء', update: 'تحديث', cancel: 'إلغاء', delete: 'حذف', assigned_count: ':count إسناد', templates: 'استخدام قالب',
        no_roles: 'لا توجد أدوار مخصصة بعد.', delegation_new: 'منح تفويض مؤقت', starts_at: 'البداية', ends_at: 'النهاية',
        maximum: 'الحد الأقصى :days يومًا', grant: 'منح التفويض', revoke: 'إلغاء التفويض', status: 'الحالة', scheduled: 'مجدول',
        expired: 'منتهي', revoked: 'ملغى', current: 'نشط', no_delegations: 'لا توجد تفويضات لهذا العضو.',
        confirm_delete: 'هل تريد حذف تعريف الدور غير المستخدم؟', confirm_revoke: 'هل تريد إلغاء هذا التفويض فورًا؟',
        load_failed: 'تعذر تحميل بيانات الوصول.', action_failed: 'تعذر حفظ التغيير.', choose_permission: 'اختر صلاحية واحدة على الأقل.',
        owner_locked: 'صلاحيات مالك النادي محمية.',
        handover_intro: 'قبل انتهاء المهمة، حدد ما إذا كانت الصلاحيات ستزال أو ستنقل إلى خلف.',
        handover_none: 'لا توجد مراجعة وصول لهذا العضو.', handover_due: 'الاستحقاق في :date', decision: 'القرار',
        decision_remove: 'إزالة الوصول دون خلف', decision_successor: 'نقل الأدوار إلى خلف', successor: 'الخلف',
        note: 'السبب', propose: 'إرسال للمراجعة الثانية', approve: 'الموافقة بصفة الشخص الثاني',
        handover_pending: 'مفتوحة', handover_proposed: 'بانتظار الموافقة', handover_approved: 'موافق عليها',
        handover_applied: 'مطبقة', handover_stale: 'تحتاج إلى مراجعة جديدة', second_person_hint: 'لا يجوز للشخص الذي اقترح التسليم أن يوافق عليه بنفسه.',
    },
}

const text = (key, values = {}) => Object.entries(values).reduce(
    (value, [name, replacement]) => String(value).replaceAll(`:${name}`, String(replacement)),
    (copy[String(locale.value || 'de').split('-')[0]] || copy.de)[key] || key,
)

const tab = ref('individual')
const loading = ref(false)
const busy = ref(false)
const error = ref('')
const notice = ref('')
const permissions = ref({ catalog: [], overrides: {}, effective: {}, defaults: [] })
const overrideForm = ref({})
const roles = ref([])
const templates = ref([])
const permissionCatalog = ref([])
const assignments = ref([])
const delegations = ref([])
const maximumDays = ref(90)
const departments = ref([])
const teams = ref([])
const handoverReviews = ref([])
const eligibleSuccessors = ref([])
const handoverForm = ref({ decision: 'remove', successor_user_id: '', note: '' })
const roleForm = ref({ id: null, name: '', key: '', permissions: [], is_active: true })

const localDateTime = (date) => {
    const offset = date.getTimezoneOffset() * 60000
    return new Date(date.getTime() - offset).toISOString().slice(0, 16)
}
const delegationForm = ref({ permissions: [], scope_type: 'club', scope_id: '', starts_at: '', ends_at: '' })
const ownDelegations = computed(() => delegations.value.filter((item) => Number(item.grantee_user_id) === Number(props.member?.id)))
const memberHandoverReviews = computed(() => handoverReviews.value.filter((item) => Number(item.departing_user?.id) === Number(props.member?.id)))
const currentHandover = computed(() => memberHandoverReviews.value.find((item) => item.status !== 'applied') || memberHandoverReviews.value[0] || null)
const activeRoles = computed(() => roles.value.filter((role) => role.is_active || assignments.value.some((item) => item.role_definition_id === role.id)))
const permissionLabel = (key) => permissionCatalog.value.find((item) => item.key === key)?.label
    || permissions.value.catalog.find((item) => item.key === key)?.label || key
const targetOptions = (scopeType) => scopeType === 'department' ? departments.value : (scopeType === 'team' ? teams.value : [])
const targetName = (item) => item.scope_type === 'club'
    ? text('club')
    : (targetOptions(item.scope_type).find((target) => Number(target.id) === Number(item.scope_id))?.name || text(item.scope_type))
const statusLabel = (status) => text(status === 'active' ? 'current' : status)

const resetDelegation = () => {
    const starts = new Date()
    const ends = new Date(starts.getTime() + 7 * 86400000)
    delegationForm.value = {
        permissions: [], scope_type: 'club', scope_id: '',
        starts_at: localDateTime(starts), ends_at: localDateTime(ends),
    }
}
const resetRole = () => { roleForm.value = { id: null, name: '', key: '', permissions: [], is_active: true } }
const requestConfig = { headers: { Accept: 'application/json' } }

const load = async () => {
    if (!props.club?.id || !props.member?.id) return
    loading.value = true
    error.value = ''
    notice.value = ''
    try {
        const [permissionResponse, roleResponse, assignmentResponse, delegationResponse, organizationResponse, handoverResponse] = await Promise.all([
            window.axios.get(route('api.v1.clubs.members.permissions.show', [props.club.id, props.member.id]), requestConfig),
            window.axios.get(route('api.v1.clubs.role-definitions.index', props.club.id), requestConfig),
            window.axios.get(route('api.v1.clubs.members.role-definitions.show', [props.club.id, props.member.id]), requestConfig),
            window.axios.get(route('api.v1.clubs.permission-delegations.index', props.club.id), requestConfig),
            window.axios.get(route('api.v1.clubs.organization.index', props.club.id), requestConfig),
            window.axios.get(route('api.v1.clubs.access-handover-reviews.index', props.club.id), requestConfig),
        ])
        permissions.value = permissionResponse.data.data
        overrideForm.value = Object.fromEntries(permissions.value.catalog.map(({ key }) => [
            key, Object.hasOwn(permissions.value.overrides, key) ? (permissions.value.overrides[key] ? 'allow' : 'deny') : 'inherit',
        ]))
        roles.value = roleResponse.data.data.roles || []
        templates.value = roleResponse.data.data.templates || []
        permissionCatalog.value = roleResponse.data.data.permission_catalog || []
        assignments.value = (assignmentResponse.data.data.assignments || []).map((item) => ({ ...item, scope_id: item.scope_id || '' }))
        delegations.value = delegationResponse.data.data.delegations || []
        maximumDays.value = delegationResponse.data.data.maximum_days || 90
        departments.value = organizationResponse.data.data.departments || []
        teams.value = organizationResponse.data.data.team_assignments?.length
            ? organizationResponse.data.data.team_assignments
            : (props.club.teams || [])
        handoverReviews.value = handoverResponse.data.data.reviews || []
        eligibleSuccessors.value = (handoverResponse.data.data.eligible_successors || []).filter((item) => Number(item.id) !== Number(props.member.id))
        const review = handoverReviews.value.find((item) => Number(item.departing_user?.id) === Number(props.member.id))
        handoverForm.value = {
            decision: review?.decision || 'remove',
            successor_user_id: review?.successor?.id || '',
            note: review?.proposal_note || '',
        }
        resetRole()
        resetDelegation()
    } catch (requestError) {
        error.value = requestError.response?.data?.message || text('load_failed')
    } finally {
        loading.value = false
    }
}

watch(() => [props.show, props.club?.id, props.member?.id], ([show]) => {
    if (show) void load()
})

const action = async (callback) => {
    if (busy.value) return
    busy.value = true
    error.value = ''
    notice.value = ''
    try {
        await callback()
        notice.value = text('saved')
    } catch (requestError) {
        const errors = requestError.response?.data?.errors
        error.value = errors
            ? Object.values(errors).flat()[0]
            : (requestError.response?.data?.message || requestError.message || text('action_failed'))
    } finally {
        busy.value = false
    }
}

const saveOverrides = () => action(async () => {
    const response = await window.axios.put(route('api.v1.clubs.members.permissions.update', [props.club.id, props.member.id]), {
        permissions: Object.fromEntries(Object.entries(overrideForm.value).map(([key, value]) => [key, value === 'inherit' ? null : value === 'allow'])),
    }, requestConfig)
    permissions.value = response.data.data
})

const addAssignment = () => assignments.value.push({
    role_definition_id: activeRoles.value[0]?.id || '', scope_type: 'club', scope_id: '',
})
const changeAssignmentScope = (assignment) => { assignment.scope_id = '' }
const saveAssignments = () => action(async () => {
    const response = await window.axios.put(route('api.v1.clubs.members.role-definitions.update', [props.club.id, props.member.id]), {
        assignments: assignments.value.map((item) => ({
            role_definition_id: Number(item.role_definition_id),
            scope_type: item.scope_type,
            scope_id: item.scope_type === 'club' ? null : Number(item.scope_id),
        })),
    }, requestConfig)
    assignments.value = (response.data.data.assignments || []).map((item) => ({ ...item, scope_id: item.scope_id || '' }))
    await refreshRoles()
})

const refreshRoles = async () => {
    const response = await window.axios.get(route('api.v1.clubs.role-definitions.index', props.club.id), requestConfig)
    roles.value = response.data.data.roles || []
}
const editRole = (role) => { roleForm.value = { ...role, permissions: [...role.permissions] } }
const applyTemplate = (template) => {
    roleForm.value = { id: null, name: template.name, key: '', permissions: [...template.permissions], is_active: true }
}
const saveRole = () => action(async () => {
    const payload = { ...roleForm.value }
    const response = roleForm.value.id
        ? await window.axios.put(route('api.v1.clubs.role-definitions.update', [props.club.id, roleForm.value.id]), payload, requestConfig)
        : await window.axios.post(route('api.v1.clubs.role-definitions.store', props.club.id), payload, requestConfig)
    await refreshRoles()
    editRole(response.data.data)
})
const deleteRole = async (role) => {
    if (!await confirmDialog({ message: text('confirm_delete'), danger: true })) return
    await action(async () => {
        await window.axios.delete(route('api.v1.clubs.role-definitions.destroy', [props.club.id, role.id]), requestConfig)
        assignments.value = assignments.value.filter((item) => item.role_definition_id !== role.id)
        await refreshRoles()
        if (roleForm.value.id === role.id) resetRole()
    })
}

const grantDelegation = () => action(async () => {
    if (!delegationForm.value.permissions.length) throw new Error(text('choose_permission'))
    await window.axios.post(route('api.v1.clubs.permission-delegations.store', props.club.id), {
        grantee_user_id: props.member.id,
        ...delegationForm.value,
        scope_id: delegationForm.value.scope_type === 'club' ? null : Number(delegationForm.value.scope_id),
    }, requestConfig)
    const response = await window.axios.get(route('api.v1.clubs.permission-delegations.index', props.club.id), requestConfig)
    delegations.value = response.data.data.delegations || []
    resetDelegation()
})
const revokeDelegation = async (delegation) => {
    if (!await confirmDialog({ message: text('confirm_revoke'), danger: true })) return
    await action(async () => {
        const response = await window.axios.post(
            route('api.v1.clubs.permission-delegations.revoke', [props.club.id, delegation.id]), {}, requestConfig,
        )
        delegations.value = delegations.value.map((item) => item.id === delegation.id ? response.data.data : item)
    })
}
const refreshHandovers = async () => {
    const response = await window.axios.get(route('api.v1.clubs.access-handover-reviews.index', props.club.id), requestConfig)
    handoverReviews.value = response.data.data.reviews || []
    eligibleSuccessors.value = (response.data.data.eligible_successors || []).filter((item) => Number(item.id) !== Number(props.member.id))
}
const proposeHandover = () => action(async () => {
    if (!currentHandover.value) return
    await window.axios.post(route('api.v1.clubs.access-handover-reviews.propose', [props.club.id, currentHandover.value.id]), {
        decision: handoverForm.value.decision,
        successor_user_id: handoverForm.value.decision === 'assign_successor' ? Number(handoverForm.value.successor_user_id) : null,
        note: handoverForm.value.note || null,
    }, requestConfig)
    await refreshHandovers()
})
const approveHandover = () => action(async () => {
    if (!currentHandover.value) return
    await window.axios.post(route('api.v1.clubs.access-handover-reviews.approve', [props.club.id, currentHandover.value.id]), {}, requestConfig)
    await refreshHandovers()
})
</script>

<template>
    <Modal :show="show" max-width="2xl" :aria-label="text('title')" @close="emit('close')">
        <div class="max-h-[calc(100dvh-5rem)] overflow-y-auto p-2 sm:p-3">
            <header class="pe-10">
                <h2 class="text-xl font-bold text-primary">{{ text('title') }}</h2>
                <p class="mt-1 text-sm leading-6 text-secondary">{{ text('intro', { name: member?.name || '' }) }}</p>
            </header>

            <div class="mt-4 flex gap-2 overflow-x-auto border-b border-border" role="tablist">
                <button v-for="item in ['individual', 'roles', 'delegations', 'handover']" :key="item" type="button" role="tab" :aria-selected="tab === item" class="min-h-11 shrink-0 border-b-2 px-3 text-sm font-semibold" :class="tab === item ? 'border-buttonPrimary text-buttonPrimary' : 'border-transparent text-secondary'" @click="tab = item">{{ text(item) }}</button>
            </div>

            <p v-if="notice" class="mt-4 rounded-lg border border-success/30 bg-success/10 p-3 text-sm font-semibold text-success" role="status">{{ notice }}</p>
            <div v-if="error" class="mt-4 flex items-center justify-between gap-3 rounded-lg border border-error/30 bg-error/10 p-3 text-sm font-semibold text-error" role="alert"><span>{{ error }}</span><button v-if="!loading" type="button" class="underline" @click="load">{{ text('retry') }}</button></div>
            <div v-if="loading" class="flex min-h-48 items-center justify-center text-sm text-secondary" aria-busy="true"><i class="las la-circle-notch me-2 animate-spin" aria-hidden="true"></i>{{ text('loading') }}</div>

            <form v-else-if="tab === 'individual'" class="mt-4" @submit.prevent="saveOverrides">
                <p class="rounded-lg bg-inputBg p-3 text-xs leading-5 text-secondary">{{ text('individual_hint') }}</p>
                <div class="mt-3 max-h-[50dvh] divide-y divide-border overflow-y-auto rounded-lg border border-border">
                    <label v-for="permission in permissions.catalog" :key="permission.key" class="grid gap-2 p-3 sm:grid-cols-[minmax(0,1fr)_11rem] sm:items-center">
                        <span><span class="block text-sm font-semibold text-primary">{{ permission.label }}</span><span class="text-xs text-secondary">{{ text('effective') }}: {{ permissions.effective[permission.key] ? text('yes') : text('no') }}</span></span>
                        <select v-model="overrideForm[permission.key]" class="rounded-lg border border-border bg-inputBg px-2 py-2 text-sm text-primary"><option value="inherit">{{ text('inherited') }}</option><option value="allow">{{ text('allow') }}</option><option value="deny">{{ text('deny') }}</option></select>
                    </label>
                </div>
                <button type="submit" :disabled="busy || member?.id === club?.owner_id" class="mt-4 w-full rounded-lg bg-buttonPrimary px-4 py-2.5 text-sm font-bold text-buttonTextPrimary disabled:opacity-50">{{ busy ? text('saving') : text('save') }}</button>
            </form>

            <div v-else-if="tab === 'roles'" class="mt-4 space-y-5">
                <section>
                    <div class="flex items-center justify-between gap-3"><h3 class="font-bold text-primary">{{ text('assigned_roles') }}</h3><button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary" @click="addAssignment">{{ text('add_assignment') }}</button></div>
                    <div class="mt-3 space-y-2">
                        <div v-for="(assignment, index) in assignments" :key="index" class="grid gap-2 rounded-lg border border-border bg-inputBg p-3 sm:grid-cols-[1fr_9rem_1fr_auto]">
                            <select v-model="assignment.role_definition_id" :aria-label="text('role')" class="rounded-lg border border-border bg-card px-2 py-2 text-sm text-primary"><option v-for="role in activeRoles" :key="role.id" :value="role.id">{{ role.name }}</option></select>
                            <select v-model="assignment.scope_type" :aria-label="text('scope')" class="rounded-lg border border-border bg-card px-2 py-2 text-sm text-primary" @change="changeAssignmentScope(assignment)"><option value="club">{{ text('club') }}</option><option value="department">{{ text('department') }}</option><option value="team">{{ text('team') }}</option></select>
                            <select v-if="assignment.scope_type !== 'club'" v-model="assignment.scope_id" :aria-label="text('select_target')" required class="rounded-lg border border-border bg-card px-2 py-2 text-sm text-primary"><option value="" disabled>{{ text('select_target') }}</option><option v-for="target in targetOptions(assignment.scope_type)" :key="target.id" :value="target.id">{{ target.name }}</option></select><span v-else></span>
                            <button type="button" :aria-label="text('remove')" class="rounded-lg border border-error/30 px-3 text-error" @click="assignments.splice(index, 1)"><i class="las la-trash" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <button type="button" :disabled="busy" class="mt-3 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary disabled:opacity-50" @click="saveAssignments">{{ busy ? text('saving') : text('save') }}</button>
                </section>

                <section class="border-t border-border pt-5">
                    <h3 class="font-bold text-primary">{{ text('role_definitions') }}</h3>
                    <div class="mt-3 flex flex-wrap gap-2"><button v-for="template in templates" :key="template.key" type="button" class="rounded-full border border-border px-3 py-1.5 text-xs font-semibold text-primary" @click="applyTemplate(template)">{{ template.name }}</button></div>
                    <form class="mt-3 space-y-3 rounded-lg border border-border bg-bg p-4" @submit.prevent="saveRole">
                        <h4 class="text-sm font-bold text-primary">{{ roleForm.id ? text('edit_role') : text('new_role') }}</h4>
                        <div class="grid gap-3 sm:grid-cols-2"><label class="text-xs font-semibold text-secondary">{{ text('name') }}<input v-model.trim="roleForm.name" required maxlength="120" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label><label class="text-xs font-semibold text-secondary">{{ text('key') }}<input v-model.trim="roleForm.key" maxlength="80" pattern="[a-z][a-z0-9_]*" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label></div>
                        <fieldset><legend class="text-xs font-semibold text-secondary">{{ text('permissions') }}</legend><div class="mt-2 grid max-h-52 gap-2 overflow-y-auto rounded-lg border border-border bg-inputBg p-3 sm:grid-cols-2"><label v-for="permission in permissionCatalog" :key="permission.key" class="flex items-start gap-2 text-xs text-primary"><input v-model="roleForm.permissions" type="checkbox" :value="permission.key" class="mt-0.5 rounded border-border"><span>{{ permission.label }}</span></label></div></fieldset>
                        <label class="flex items-center gap-2 text-sm font-semibold text-primary"><input v-model="roleForm.is_active" type="checkbox" class="rounded border-border">{{ text('active') }}</label>
                        <div class="flex gap-2"><button type="submit" :disabled="busy" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary disabled:opacity-50">{{ roleForm.id ? text('update') : text('create') }}</button><button type="button" class="rounded-lg border border-border px-4 py-2 text-sm text-primary" @click="resetRole">{{ text('cancel') }}</button></div>
                    </form>
                    <div class="mt-3 space-y-2"><p v-if="!roles.length" class="text-sm text-secondary">{{ text('no_roles') }}</p><article v-for="role in roles" :key="role.id" class="flex items-center justify-between gap-3 rounded-lg border border-border p-3"><button type="button" class="min-w-0 text-start" @click="editRole(role)"><span class="block truncate text-sm font-semibold text-primary">{{ role.name }}</span><span class="text-xs text-secondary">{{ text('assigned_count', { count: role.assigned_count }) }} · {{ role.permissions.length }} {{ text('permissions') }}</span></button><button type="button" :disabled="role.assigned_count > 0" class="rounded-lg border border-error/30 px-3 py-2 text-xs font-semibold text-error disabled:opacity-40" @click="deleteRole(role)">{{ text('delete') }}</button></article></div>
                </section>
            </div>

            <div v-else-if="tab === 'delegations'" class="mt-4 space-y-5">
                <form class="space-y-3 rounded-lg border border-border bg-bg p-4" @submit.prevent="grantDelegation">
                    <div><h3 class="font-bold text-primary">{{ text('delegation_new') }}</h3><p class="text-xs text-secondary">{{ text('maximum', { days: maximumDays }) }}</p></div>
                    <div class="grid gap-3 sm:grid-cols-2"><label class="text-xs font-semibold text-secondary">{{ text('starts_at') }}<input v-model="delegationForm.starts_at" type="datetime-local" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label><label class="text-xs font-semibold text-secondary">{{ text('ends_at') }}<input v-model="delegationForm.ends_at" required type="datetime-local" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></label></div>
                    <div class="grid gap-3 sm:grid-cols-2"><label class="text-xs font-semibold text-secondary">{{ text('scope') }}<select v-model="delegationForm.scope_type" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" @change="delegationForm.scope_id = ''"><option value="club">{{ text('club') }}</option><option value="department">{{ text('department') }}</option><option value="team">{{ text('team') }}</option></select></label><label v-if="delegationForm.scope_type !== 'club'" class="text-xs font-semibold text-secondary">{{ text('select_target') }}<select v-model="delegationForm.scope_id" required class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><option value="" disabled>{{ text('select_target') }}</option><option v-for="target in targetOptions(delegationForm.scope_type)" :key="target.id" :value="target.id">{{ target.name }}</option></select></label></div>
                    <fieldset><legend class="text-xs font-semibold text-secondary">{{ text('permissions') }}</legend><div class="mt-2 grid max-h-52 gap-2 overflow-y-auto rounded-lg border border-border bg-inputBg p-3 sm:grid-cols-2"><label v-for="permission in permissionCatalog.filter((item) => item.key !== 'members.roles')" :key="permission.key" class="flex items-start gap-2 text-xs text-primary"><input v-model="delegationForm.permissions" type="checkbox" :value="permission.key" class="mt-0.5 rounded border-border"><span>{{ permission.label }}</span></label></div></fieldset>
                    <button type="submit" :disabled="busy" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary disabled:opacity-50">{{ text('grant') }}</button>
                </form>
                <section><h3 class="font-bold text-primary">{{ text('delegations') }}</h3><p v-if="!ownDelegations.length" class="mt-2 text-sm text-secondary">{{ text('no_delegations') }}</p><div v-else class="mt-2 space-y-2"><article v-for="delegation in ownDelegations" :key="delegation.id" class="rounded-lg border border-border p-3"><div class="flex items-start justify-between gap-3"><div><p class="text-sm font-semibold text-primary">{{ targetName(delegation) }}</p><p class="mt-1 text-xs text-secondary">{{ delegation.permissions.map(permissionLabel).join(', ') }}</p><p class="mt-1 text-xs text-secondary">{{ text('status') }}: {{ statusLabel(delegation.status) }}</p></div><button v-if="['active', 'scheduled'].includes(delegation.status)" type="button" class="rounded-lg border border-error/30 px-3 py-2 text-xs font-semibold text-error" @click="revokeDelegation(delegation)">{{ text('revoke') }}</button></div></article></div></section>
            </div>

            <section v-else class="mt-4 space-y-4" aria-labelledby="handover-title">
                <div><h3 id="handover-title" class="font-bold text-primary">{{ text('handover') }}</h3><p class="mt-1 text-sm leading-6 text-secondary">{{ text('handover_intro') }}</p></div>
                <p v-if="!currentHandover" class="rounded-lg bg-inputBg p-3 text-sm text-secondary">{{ text('handover_none') }}</p>
                <div v-else class="space-y-4 rounded-lg border border-border p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2"><p class="text-sm font-bold text-primary">{{ text(`handover_${currentHandover.status}`) }}</p><p class="text-xs text-secondary">{{ text('handover_due', { date: currentHandover.due_on }) }} · {{ currentHandover.assignment_count }} {{ text('assigned_roles') }} · {{ currentHandover.delegation_count }} {{ text('delegations') }}</p></div>
                    <form v-if="['pending', 'proposed', 'stale'].includes(currentHandover.status)" class="space-y-3" @submit.prevent="proposeHandover">
                        <label class="block text-xs font-semibold text-secondary">{{ text('decision') }}<select v-model="handoverForm.decision" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><option value="remove">{{ text('decision_remove') }}</option><option value="assign_successor">{{ text('decision_successor') }}</option></select></label>
                        <label v-if="handoverForm.decision === 'assign_successor'" class="block text-xs font-semibold text-secondary">{{ text('successor') }}<select v-model="handoverForm.successor_user_id" required class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"><option value="" disabled>{{ text('successor') }}</option><option v-for="candidate in eligibleSuccessors" :key="candidate.id" :value="candidate.id">{{ candidate.name }}</option></select></label>
                        <label class="block text-xs font-semibold text-secondary">{{ text('note') }}<textarea v-model.trim="handoverForm.note" maxlength="2000" rows="3" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"></textarea></label>
                        <button type="submit" :disabled="busy" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary disabled:opacity-50">{{ text('propose') }}</button>
                    </form>
                    <div v-if="currentHandover.status === 'proposed'" class="rounded-lg border border-warning/30 bg-warning/10 p-3"><p class="text-xs text-secondary">{{ text('second_person_hint') }}</p><button type="button" :disabled="busy" class="mt-2 rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-bold text-buttonTextPrimary disabled:opacity-50" @click="approveHandover">{{ text('approve') }}</button></div>
                </div>
            </section>

            <button type="button" class="mt-5 w-full rounded-lg border border-border px-4 py-2.5 text-sm font-semibold text-primary" @click="emit('close')">{{ text('close') }}</button>
        </div>
    </Modal>
</template>
