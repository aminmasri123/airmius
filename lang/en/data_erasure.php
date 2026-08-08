<?php

return [
    'flash' => [
        'code_sent' => 'We sent you a confirmation code by email. It is valid for 15 minutes.',
        'completed' => 'The selected data was deleted or anonymized where required. Your account remains active.',
    ],
    'validation' => [
        'identity_required' => 'Please confirm your identity.',
        'categories_required' => 'Select at least one data area.',
        'code_required' => 'Enter the code from your email.',
        'code_invalid' => 'The code is invalid, expired, or does not match the selected data deletion. Request a new code.',
        'email_mismatch' => 'The email address entered does not match your account.',
        'password_incorrect' => 'The password entered is incorrect.',
        'category_invalid' => 'At least one valid data category must be selected.',
    ],
    'categories' => [
        'profile' => [
            'label' => 'Profile and contact data',
            'description' => 'Your name, photo, phone number, address, bio, and other profile details are removed. Your email address and credentials remain for your account.',
        ],
        'content' => [
            'label' => 'Posts, comments, and stories',
            'description' => 'Your posts, comments, stories, and associated personal media are permanently removed.',
        ],
        'messages' => [
            'label' => 'Your chat messages',
            'description' => 'The content of your messages and attachments is removed. Only a neutral deletion notice remains in conversations.',
        ],
        'files' => [
            'label' => 'Files and folders',
            'description' => 'Files, previews, and personal folders you uploaded are permanently removed.',
        ],
        'sport_and_health' => [
            'label' => 'Sport, location, and health data',
            'description' => 'Imported activities, routes, tracks, sport profiles, training logs, and nutrition data are removed.',
        ],
        'social_and_integrations' => [
            'label' => 'Social connections and integrations',
            'description' => 'Friendships, follows, notifications, device identifiers, and external sport links are removed. The technical link for Google or Microsoft sign-in remains.',
        ],
        'commerce' => [
            'label' => 'Shop and order data',
            'description' => 'Carts, shipping addresses, wishlists, and reviews are removed. Completed orders with no retention obligation are anonymized.',
        ],
    ],
    'summary' => [
        'pseudonym' => 'Airmius User #:id',
        'retained_account' => 'Your account, email address, and credentials remain active.',
        'retained_relationships' => 'Club, team, and permission assignments are not deleted automatically so that data belonging to other people or organizations is not lost.',
        'profile' => 'Profile and contact data',
        'content' => 'Posts, comments, and stories',
        'content_media' => 'Media from posts and stories',
        'messages' => 'Your chat messages',
        'message_attachments' => 'Chat attachments',
        'files' => 'Files',
        'folders' => 'Folders',
        'sport_and_health' => 'Sport, location, and health data',
        'social_and_integrations' => 'Social connections, notifications, and integrations',
        'commerce' => 'Carts, shipping addresses, wishlists, and reviews',
        'retained_orders' => ':count order records remain temporarily because they may relate to payment, invoicing, delivery, or an unresolved issue.',
        'financial_records' => 'Invoices, payments, subscriptions, and other records required by law or contract are not deleted by this function.',
        'message_deleted' => '[Message deleted]',
        'personal_data_removed' => '[Personal data removed]',
    ],
    'email_templates' => [
        'data_erasure_code' => [
            'subject' => 'Confirmation code for data deletion',
            'greeting' => 'Hello,',
            'body' => "You requested the deletion of selected personal data from your Airmius account without deleting the account.\nYour confirmation code is: {{ code }}\nThe code is valid for 15 minutes.\nIf you did not make this request, ignore this email and secure your account.",
            'action_label' => '',
        ],
        'data_erasure_completed' => [
            'subject' => 'Your requested data has been processed',
            'greeting' => 'Hello {{ name }},',
            'body' => "Your selected data was deleted or anonymized where required. Your Airmius account remains active.\nInvoices, payments, contracts, and other records that must be retained by law may continue to be stored.\nIf you did not make this request, contact Airmius support.",
            'action_label' => '',
        ],
        'subscription_renewed' => [
            'subject' => 'Your Airmius subscription has been renewed',
            'greeting' => 'Hello {{ name }},',
            'body' => "Your Airmius subscription has been renewed.\nPlan: {{ plan_name }}\nNew end date: {{ end_date }}\nThank you for using Airmius.",
            'action_label' => 'View plans',
        ],
        'subscription_resumed' => [
            'subject' => 'Your Airmius subscription will continue',
            'greeting' => 'Hello {{ name }},',
            'body' => "Your scheduled cancellation has been withdrawn.\nPlan: {{ plan_name }}\nNext renewal date: {{ renewal_date }}\nYour subscription will continue without interruption.",
            'action_label' => 'View plans',
        ],
        'subscription_payment_issue' => [
            'subject' => 'Payment for your Airmius subscription is outstanding',
            'greeting' => 'Hello {{ name }},',
            'body' => "A payment for your Airmius subscription is outstanding or your trial has expired.\nPlan: {{ plan_name }}\nStatus: payment outstanding\nPlease update your payment so all booked features remain active.",
            'action_label' => 'Renew plan',
        ],
        'subscription_ending_soon' => [
            'subject' => 'Your Airmius subscription will end soon',
            'greeting' => 'Hello {{ name }},',
            'body' => "{{ ending_message }}\nPlan: {{ plan_name }}\nEnd date: {{ end_date }}\nChoose a suitable plan in time if you would like to keep using Airmius.",
            'action_label' => 'View plans',
        ],
        'subscription_cancelled' => [
            'subject' => 'Airmius subscription cancellation confirmed',
            'greeting' => 'Hello {{ name }},',
            'body' => "{{ cancel_message }}\nPlan: {{ plan_name }}\nEnds on: {{ end_date }}\nYou can activate a suitable plan again at any time.",
            'action_label' => 'View plans',
        ],
        'subscription_invoice_awaiting_transfer' => [
            'subject' => 'Airmius invoice {{ invoice_number }} is awaiting bank transfer',
            'greeting' => 'Hello {{ name }},',
            'body' => "Your Airmius invoice has been created and is awaiting payment by bank transfer.\nInvoice: {{ invoice_number }}\nPlan: {{ plan_name }}\nAmount: {{ amount }}\nDue date: {{ due_date }}\nPayment reference: {{ payment_reference }}\nAccount holder: {{ bank_account_holder }}\nBank: {{ bank_name }}\nIBAN: {{ iban }}\nBIC: {{ bic }}\nWe will confirm the payment in Airmius once it arrives.",
            'action_label' => 'Download invoice',
        ],
        'subscription_invoice_paid' => [
            'subject' => 'Payment for Airmius invoice {{ invoice_number }} confirmed',
            'greeting' => 'Hello {{ name }},',
            'body' => "Your payment has been confirmed and your Airmius subscription is active.\nInvoice: {{ invoice_number }}\nPlan: {{ plan_name }}\nAmount: {{ amount }}\nPaid on: {{ paid_date }}\nPayment method: {{ payment_method }}\nThank you for using Airmius.",
            'action_label' => 'Download invoice',
        ],
        'together' => 'there',
    ],
];
