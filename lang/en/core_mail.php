<?php

$catalog = require __DIR__.'/../de/core_mail.php';

$catalog['common'] = [
    'greeting' => 'Hello :name,', 'together' => 'there', 'not_set' => 'not set yet', 'salutation' => 'Best regards, your Airmius team',
    'fields' => [
        'subscription' => 'Subscription: :value', 'amount' => 'Amount: :value', 'due_on' => 'Due by: :value', 'due_since' => 'Overdue since: :value',
        'payment_reference' => 'Payment reference: :value', 'delivery_month' => 'Delivery month: :value', 'shipping' => 'Shipping: :value',
        'tracking_link' => 'Tracking link: :value', 'note' => 'Note: :value', 'invoice_number' => 'Invoice number: :value', 'title' => 'Title: :value',
        'old_status' => 'Previous status: :value', 'new_status' => 'New status: :value', 'status' => 'Status: :value', 'club' => 'Club: :value',
        'country' => 'Country: :value', 'requested_club_number' => 'Requested club number: :value', 'account_holder' => 'Account holder: :value',
        'iban' => 'IBAN: :value', 'bic' => 'BIC: :value', 'purpose' => 'Payment reference: :value', 'message' => 'Message: :value',
    ],
    'actions' => [
        'invoice' => 'View invoice', 'review_club' => 'Review application', 'open_club' => 'Open club', 'marketplace' => 'Open marketplace',
        'outfit' => 'View outfit subscription', 'outfits' => 'View outfit subscriptions', 'verify_email' => 'Verify email address',
        'trainer_cockpit' => 'Open trainer cockpit', 'view_application' => 'View application', 'review_trainers' => 'Review trainer applications',
    ],
];
$catalog['verification'] = [
    'subject' => 'Verify your email address for Airmius',
    'body' => 'Verify your email address to fully activate your Airmius account.',
    'security_note' => 'This security link expires. If you did not create the account, you can ignore this message.',
];
$catalog['invoice'] = array_replace_recursive($catalog['invoice'], [
    'new_subject' => 'New invoice from :sender', 'new_body' => 'You have received a new invoice.', 'club_body' => 'You have received a new invoice from :club.',
    'settle_if_open' => 'Please review the invoice and pay it on time if it is still outstanding.', 'settle' => 'Please review the invoice and pay it on time.',
    'status_subject' => 'Your invoice status was updated', 'status_body' => 'The status of your invoice was updated.',
    'status_review' => 'Please review your invoices if a payment is still outstanding.', 'bank_transfer' => 'Payment by bank transfer:', 'club_fallback' => 'your club',
    'statuses' => ['paid' => 'Paid', 'open' => 'Open', 'pending' => 'Pending', 'awaiting_transfer' => 'Awaiting bank transfer', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled', 'failed' => 'Failed'],
]);
$catalog['club_registration'] = [
    'review_subject' => 'New club application: :club', 'review_body' => ':applicant registered a club.', 'submitted_subject' => 'Your club application was submitted',
    'submitted_body' => 'Your club “:club” was created and is now awaiting review.', 'owner_body' => 'You are registered as the club owner immediately and can manage the club from the dashboard.',
    'visibility_body' => 'The club will become publicly visible and marked as official only after approval.',
];
$catalog['commerce_return'] = [
    'subject' => 'Return updated', 'body' => 'Your return for :item was updated.', 'item_fallback' => 'your order',
    'default_note' => 'You can view the current status in your marketplace area.',
    'statuses' => ['requested' => 'Requested', 'approved' => 'Approved', 'received' => 'Received', 'refunded' => 'Refunded', 'rejected' => 'Rejected'],
];
$catalog['outfit'] = array_replace_recursive($catalog['outfit'], [
    'plan_fallback' => 'Outfit subscription', 'carrier_fallback' => 'Parcel service', 'payment_reminder_subject' => 'Reminder: outfit subscription payment outstanding',
    'payment_reminder_body' => 'We have not yet received payment for your outfit subscription.', 'dunning_subject' => 'Payment reminder :level: outfit subscription payment outstanding',
    'final_dunning_subject' => 'Final reminder: outfit subscription payment outstanding', 'dunning_body' => 'A payment for your active outfit subscription is outstanding.',
    'dunning_continue' => 'Please settle the payment so your outfit subscription can continue without interruption.',
    'dunning_paused' => 'Your outfit subscription has been paused until payment is received. No further deliveries will be prepared during this time.',
    'expired_subject' => 'Outfit subscription request deleted', 'expired_body' => 'Your outfit subscription request was deleted because no payment was received within 9 days.',
    'expired_restart' => 'You can start a new request at any time if you still want to use the outfit subscription.',
    'delivery_subjects' => ['planned' => 'Your outfit delivery was scheduled', 'preparing' => 'Your outfit delivery is being prepared', 'shipped' => 'Your outfit delivery was shipped', 'delivered' => 'Your outfit delivery was delivered', 'cancelled' => 'Your outfit delivery was cancelled'],
    'delivery_bodies' => ['planned' => 'Your next sportswear box was scheduled.', 'preparing' => 'Your sportswear box is being prepared.', 'shipped' => 'Your sportswear box was shipped.', 'delivered' => 'Your sportswear box was marked as delivered.', 'cancelled' => 'Your sportswear box was cancelled.'],
]);
$catalog['trainer'] = [
    'approved_subject' => 'Your trainer application was approved', 'rejected_subject' => 'Your trainer application was rejected',
    'approved_body' => 'Airmius reviewed and approved your trainer application. Your trainer access remains active.',
    'rejected_body' => 'Airmius rejected your trainer application.', 'disabled_body' => 'Your trainer access was disabled again.',
    'review_subject' => 'New trainer application: :applicant', 'review_body' => ':applicant wants to use the trainer area on Airmius.',
    'review_pending' => 'Trainer access was activated immediately and is awaiting review by Airmius.',
];

return $catalog;
