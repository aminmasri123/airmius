<?php

return [
    'responses' => [
        'consent_resent' => 'Consent request resent.',
        'consent_approved' => 'Consent was granted.',
        'consent_revoked' => 'Consent was revoked.',
        'registration_approved' => 'Registration confirmed.',
        'registration_rejected' => 'Registration rejected.',
        'email_resent' => 'The email was sent again.',
        'access_revoked' => 'Consent was revoked.',
        'access_approved' => 'The rejection was withdrawn and consent was granted.',
        'account_created' => 'Your parent account was created. You can now sign in.',
    ],
    'validation' => [
        'confirmation_required' => 'Confirm that you are the child’s legal guardian.',
        'already_approved' => 'Consent has already been granted.',
        'guardian_email_missing' => 'No guardian email address is stored.',
        'resend_wait' => 'Wait another :seconds seconds before sending the email again.',
    ],
    'notifications' => [
        'approved_title' => 'Consent granted',
        'approved_body' => 'Your Airmius account was approved by a guardian.',
        'revoked_title' => 'Consent revoked',
        'revoked_body' => 'Approval for your Airmius account was revoked. Please discuss this with your guardian.',
        'rejected_title' => 'Consent rejected',
        'rejected_body' => 'Approval for your Airmius account was rejected. You can request consent again.',
    ],
];
