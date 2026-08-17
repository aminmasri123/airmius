<?php

$source = require __DIR__.'/data_erasure.php';
$templates = $source['email_templates'] ?? [];
unset($templates['together']);

return array_replace($templates, [
    'account_welcome' => [
        'subject' => 'Welcome to Airmius',
        'greeting' => 'Hello {{ name }},',
        'body' => "Welcome to Airmius. Your account was created successfully.\nYou can now sign in, complete your profile, and use Airmius for training, clubs, teams, events, and your daily sports routine.\nIf you did not create this account, please contact Airmius support.",
        'action_label' => 'Open Airmius',
    ],
    'account_created_with_credentials' => [
        'subject' => 'Your Airmius account was created',
        'greeting' => 'Hello {{ name }},',
        'body' => "An Airmius account was created for you.\nEmail: {{ email }}\nTemporary password: {{ temporary_password }}\nPlease sign in and change your password immediately after your first login.\nIf you did not expect this account, please contact Airmius support.",
        'action_label' => 'Sign in to Airmius',
    ],
    'account_deletion_code' => [
        'subject' => 'Account deletion confirmation code',
        'greeting' => 'Hello,',
        'body' => "You requested deletion of your Airmius account.\nYour confirmation code is: {{ code }}\nThe code is valid for 15 minutes.\nIf you do not want to delete your account, you can ignore this email.",
        'action_label' => '',
    ],
    'account_deletion_completed' => [
        'subject' => 'Your Airmius account was deleted',
        'greeting' => 'Hello {{ name }},',
        'body' => "Your Airmius account was deleted successfully.\nThis email confirms that the account deletion is complete.\nIf you did not initiate this deletion, please contact Airmius support.",
        'action_label' => '',
    ],
    'guardian_access_code' => [
        'subject' => 'Your Airmius guardian access code',
        'greeting' => 'Hello,',
        'body' => "You requested an access code for the Airmius guardian area.\nYour code is: {{ code }}\nThe code is valid for 15 minutes.\nIf you did not request this code, you can ignore this email.",
        'action_label' => '',
    ],
    'guardian_consent_requested' => [
        'subject' => 'Consent to register with Airmius',
        'greeting' => 'Hello,',
        'body' => "{{ minor_name }} registered with Airmius and is under 16 years old.\nPlease review the request. You can approve or reject the registration.\nIf you did not expect this request, you can ignore this email.",
        'action_label' => 'Approve or reject',
    ],
    'password_reset' => [
        'subject' => 'Reset password',
        'greeting' => 'Hello!',
        'body' => "You are receiving this email because we received a password reset request for your account.\nThis link expires in {{ expires_minutes }} minutes.",
        'action_label' => 'Reset password',
    ],
    'contact_form_admin' => [
        'subject' => 'New contact form request from {{ name }}',
        'greeting' => 'New contact request',
        'body' => "Name: {{ name }}\nEmail: {{ email }}\n\n{{ message }}",
        'action_label' => '',
    ],
    'login_successful' => [
        'subject' => 'New sign-in to Airmius',
        'greeting' => 'Hello,',
        'body' => "A successful sign-in to your Airmius account just occurred.\nTime: {{ logged_in_at }}\nIP address: {{ ip_address }}\nDevice/browser: {{ user_agent }}\nIf this was you, no action is required.\nIf this was not you, change your password immediately and contact Airmius support.",
        'action_label' => '',
    ],
    'login_two_factor_code' => [
        'subject' => 'Your Airmius security code',
        'greeting' => 'Hello {{ name }},',
        'body' => "Your sign-in security code is: {{ code }}\nThe code is valid for {{ expires_minutes }} minutes and can only be used once.\nIf you did not start this sign-in, change your password.",
        'action_label' => '',
    ],
    'login_lockout' => [
        'subject' => 'Multiple failed sign-in attempts at Airmius',
        'greeting' => 'Hello,',
        'body' => "Multiple failed sign-in attempts were detected for your Airmius account.\nSign-in was temporarily blocked to protect your account.\nTime: {{ locked_at }}\nIP address: {{ ip_address }}\nDevice/browser: {{ user_agent }}\nIf this was you, wait briefly and try again.\nIf this was not you, change your password and review your account security.",
        'action_label' => '',
    ],
    'account_suspended' => [
        'subject' => 'Your Airmius account was temporarily suspended',
        'greeting' => 'Hello {{ name }},',
        'body' => "Your Airmius account was temporarily suspended.\nReason: {{ reason }}\nSuspended until: {{ suspended_until }}\nIf you believe this suspension is incorrect, contact support and request a review.",
        'action_label' => 'Contact support',
    ],
    'inactive_account_first' => [
        'subject' => 'Your Airmius account has been inactive for a while',
        'greeting' => 'Hello {{ name }},',
        'body' => "Your Airmius account has not been used for some time.\nFor privacy reasons, we regularly review inactive accounts.\nIf you want to keep using Airmius, simply sign in again to keep your account active.",
        'action_label' => 'Sign in to Airmius',
    ],
    'inactive_account_second' => [
        'subject' => 'Reminder: your Airmius account is still inactive',
        'greeting' => 'Hello {{ name }},',
        'body' => "Your Airmius account is still inactive.\nSigning in again will reset the scheduled privacy review.\nWithout a response, your account may later be deactivated and anonymized.",
        'action_label' => 'Keep account active',
    ],
    'inactive_account_scheduled' => [
        'subject' => 'Airmius account scheduled for anonymization',
        'greeting' => 'Hello {{ name }},',
        'body' => "Your Airmius account has been inactive for some time.\nWe have therefore scheduled it for privacy anonymization.\nScheduled date: {{ scheduled_date }}\nIf you sign in before this date, your account will remain active.",
        'action_label' => 'Keep account active',
    ],
    'subscription_invoice_reminder' => [
        'subject' => 'Reminder: Airmius invoice {{ invoice_number }} is outstanding',
        'greeting' => 'Hello {{ name }},',
        'body' => "No payment has been recorded for your Airmius invoice yet.\nInvoice: {{ invoice_number }}\nPlan: {{ plan_name }}\nAmount: {{ amount }}\nOverdue since: {{ due_date }}\nPayment reference: {{ payment_reference }}\nIf you have already paid, you can ignore this reminder. The payment will be marked after bank reconciliation.",
        'action_label' => 'Download invoice',
    ],
    'commerce_order_awaiting_transfer' => [
        'subject' => 'Payment details for your Airmius order {{ order_number }}',
        'greeting' => 'Hello {{ name }},',
        'body' => "Your order was created successfully and is awaiting your bank transfer.\nOrder: {{ order_title }}\nOrder number: {{ order_number }}\nAmount: {{ amount }}\nDue by: {{ due_date }}\nPayment reference: {{ payment_reference }}\nAccount holder: {{ bank_account_holder }}\nBank: {{ bank_name }}\nIBAN: {{ iban }}\nBIC: {{ bic }}\nOnce payment is received, we will confirm it and unlock your purchase.",
        'action_label' => 'View order and payment details',
    ],
    'commerce_order_completed' => [
        'subject' => 'Airmius order confirmed',
        'greeting' => 'Hello {{ name }},',
        'body' => "Your order was confirmed successfully.\nOrder: {{ order_title }}\nAmount: {{ amount }}\nStatus: paid\nThank you for using Airmius.",
        'action_label' => 'View marketplace',
    ],
    'external_club_membership_invitation' => [
        'subject' => 'Invitation to {{ club_name }} on Airmius',
        'greeting' => 'Hello {{ name }},',
        'body' => "{{ inviter_name }} invited you on behalf of {{ club_name }} and wants to connect you with Airmius.\nWith an Airmius account, you can keep track of club details, invoices, payment history, teams, and messages.\nIf you already have an account with this email address, sign in to complete the connection. Otherwise, register with this email address.",
        'action_label' => 'View invitation',
    ],
    'external_team_invitation' => [
        'subject' => 'Invitation to {{ team_name }}',
        'greeting' => 'Hello,',
        'body' => "{{ inviter_name }} invited you to {{ team_name }} on Airmius.\nAccept the invitation to join the team.",
        'action_label' => 'Accept invitation',
    ],
    'external_friend_invitation' => [
        'subject' => '{{ sender_name }} wants to connect with you on Airmius',
        'greeting' => 'Hello,',
        'body' => "{{ sender_name }} invited you to connect as a friend on Airmius.\nIf you already have an account with this email address, sign in and accept the invitation. Otherwise, register with this email address.",
        'action_label' => 'Accept invitation',
    ],
]);
