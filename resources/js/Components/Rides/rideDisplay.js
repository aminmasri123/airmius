export const joinBlockLabels = {
    driver: 'Du bist der Fahrer dieser Fahrt.',
    already_joined: 'Du bist bereits beigetreten.',
    pending_request: 'Anfrage ausstehend.',
    full: 'Diese Fahrgemeinschaft ist voll.',
    past: 'Die Abfahrtszeit liegt in der Vergangenheit.',
    not_allowed: 'Du hast keinen Zugriff auf diese Fahrt.',
    unavailable: 'Diese Fahrt ist aktuell nicht buchbar.',
}

export const joinStateMeta = {
    driver: {
        icon: 'la-user-check',
        label: 'Eigene Fahrt',
        statusClass: 'border border-air-blue/40 bg-air-blue/10 text-air-blue',
    },
    pending_request: {
        icon: 'la-hourglass-half',
        label: 'Anfrage offen',
        statusClass: 'border border-air-blue/40 bg-air-blue/10 text-air-blue',
    },
    full: {
        icon: 'la-exclamation-triangle',
        label: 'Voll',
        statusClass: 'border border-error/40 bg-error/10 text-error',
    },
    past: {
        icon: 'la-calendar-times',
        label: 'Vergangenheit',
        statusClass: 'border border-border bg-muted text-secondary',
    },
    not_allowed: {
        icon: 'la-lock',
        label: 'Nicht sichtbar',
        statusClass: 'border border-border bg-muted text-secondary',
    },
    unavailable: {
        icon: 'la-ban',
        label: 'Nicht buchbar',
        statusClass: 'border border-border bg-muted text-secondary',
    },
}

export const joinBlockLegend = [
    { reason: 'not_allowed', label: 'Kein Zugriff (Sichtbarkeit/Status).' },
    { reason: 'full', label: 'Volle Fahrt.' },
    { reason: 'past', label: 'Vergangene Fahrt.' },
    { reason: 'pending_request', label: 'Anfrage ausstehend.' },
]

export const joinStateFor = (reason) => joinStateMeta[reason] || joinStateMeta.unavailable

export const formatDateTime = (value) => {
    if (!value) return 'Nicht gesetzt'

    return new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value))
}

export const visibilityLabel = (visibilities, value) => {
    return visibilities.find((visibility) => visibility.value === value)?.label || value
}

export const joinBlockLabel = (reason) => joinBlockLabels[reason] || 'Nicht verfügbar.'

export const rideIsFull = (ride) => Number(ride.participants_count) >= Number(ride.seats)

export const joinStatusMeta = (ride) => {
    if (ride.can_join) {
        return null
    }

    return joinStateFor(ride.join_block_reason || 'unavailable')
}

export const roleBadgeMeta = (ride) => {
    if (ride.is_driver) {
        return {
            icon: 'la-steering-wheel',
            label: 'Fahrer',
            className: 'border border-air-blue/40 bg-air-blue/10 text-air-blue',
        }
    }

    if (ride.is_joined) {
        return {
            icon: 'la-check-circle',
            label: 'Dabei',
            className: 'border border-success/40 bg-success/10 text-success',
        }
    }

    if (ride.has_pending_request) {
        return {
            icon: 'la-hourglass-half',
            label: 'Anfrage offen',
            className: 'border border-air-blue/40 bg-air-blue/10 text-air-blue',
        }
    }

    return null
}

export const rideCardClass = (ride) => {
    if (rideIsFull(ride)) {
        return 'border-error/40'
    }

    if (ride.has_pending_request || ride.is_driver || ride.is_joined) {
        return 'border-air-blue/40'
    }

    return 'border-border'
}

export const rideActionHint = (ride) => {
    if (ride.can_join) {
        return 'Du kannst eine Anfrage senden. Private Treffpunkt- und Kontaktdaten werden erst nach Annahme sichtbar.'
    }

    if (ride.has_pending_request) {
        return 'Deine Anfrage wartet auf Rückmeldung. Du kannst sie jederzeit zurückziehen.'
    }

    if (ride.is_joined) {
        return 'Du bist dabei und siehst die freigegebenen Treffpunkt- und Kontaktdaten.'
    }

    if (ride.is_driver) {
        return ride.pending_requests?.length
            ? 'Prüfe offene Anfragen und halte die Sitzplätze aktuell.'
            : 'Du verwaltest diese Fahrt.'
    }

    return joinBlockLabel(ride.join_block_reason)
}

export const seatBadgeMeta = (ride) => {
    if (rideIsFull(ride)) {
        return {
            icon: 'la-exclamation-circle',
            label: `${ride.participants_count}/${ride.seats} Plätze - Voll`,
            className: 'border border-error/40 bg-error/10 text-error',
            hint: 'Diese Fahrgemeinschaft ist voll.',
        }
    }

    if (ride.join_block_reason === 'past') {
        return {
            icon: 'la-calendar-times',
            label: `${ride.participants_count}/${ride.seats} Plätze - Vergangen`,
            className: 'border border-border bg-muted text-secondary',
            hint: joinBlockLabel(ride.join_block_reason),
        }
    }

    return {
        icon: 'la-users',
        label: `${ride.participants_count}/${ride.seats} Plätze`,
        className: 'border border-border bg-inputBg text-secondary',
        hint: 'Platzbelegung',
    }
}

