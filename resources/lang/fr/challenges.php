<?php

return [
    'errors' => [
        'not_joinable' => 'Il n’est plus possible de rejoindre ce défi.',
        'invitation_required' => 'Une invitation est requise pour ce défi privé.',
        'participant_required' => 'Vous devez participer au défi.',
        'not_active' => 'Ce défi n’est pas actif.',
        'automatic_only' => 'Ce défi est suivi uniquement automatiquement.',
        'invalid_date' => 'La date est en dehors de la période du défi.',
        'public_forbidden' => 'Seuls les administrateurs et collaborateurs autorisés peuvent créer des défis publics.',
        'club_forbidden' => 'Vous ne pouvez pas créer de défi pour ce club.',
        'team_forbidden' => 'Vous ne pouvez pas créer de défi pour cette équipe.',
        'invitee_forbidden' => 'Cette personne ne peut pas être invitée à ce défi privé.',
    ],
    'notifications' => [
        'invitation_title' => 'Nouvelle invitation à un défi',
        'invitation_body' => ':user vous invite à « :challenge ».',
        'accepted_title' => 'Défi accepté',
        'accepted_body' => ':user participe à « :challenge ».',
        'declined_title' => 'Défi refusé',
        'declined_body' => ':user a refusé « :challenge ».',
        'comment_title' => 'Nouveau commentaire',
        'comment_body' => ':user a commenté « :challenge ».',
    ],
];
