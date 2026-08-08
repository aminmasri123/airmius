<?php

return [
    'responses' => [
        'profile_saved' => 'Style profile saved.',
        'paypal_continue' => 'You can continue with the PayPal payment.',
        'requested' => 'Outfit subscription requested.',
        'requested_web' => 'Outfit subscription requested. It will be activated only after payment.',
        'issue_sent' => 'Your report was sent.',
        'issue_sent_web' => 'Your report was sent. Our team will review the delivery.',
    ],
    'notifications' => [
        'pending_payment_title' => 'Outfit subscription awaiting payment',
        'pending_payment_body' => 'Your :plan outfit subscription has been reserved. It will be activated only after payment is confirmed.',
        'paid_title' => 'Outfit subscription activated',
        'paid_body' => 'Your payment for :plan was confirmed. Your outfit subscription is now active.',
        'requested_title' => 'New outfit subscription requested',
        'requested_body' => ':user requested :plan. Payment is still pending.',
        'issue_requested_title' => 'Outfit delivery needs support',
        'issue_requested_body' => ':user reported a problem with an outfit delivery.',
        'plan_fallback' => 'Outfit subscription',
        'unpaid_title' => 'Outfit subscription payment due',
        'unpaid_body' => 'A payment was marked as due for your :plan outfit subscription.:reason',
        'address_updated_title' => 'Shipping address updated',
        'address_updated_body' => 'The shipping address for your :plan outfit subscription was updated.',
        'cancelled_title' => 'Outfit subscription request cancelled',
        'cancelled_body' => 'Your outfit subscription request for :plan was cancelled.:reason',
        'issue_status_updated_title' => 'Support case updated',
        'issue_status' => [
            'reviewing' => 'Your report for :plan is being reviewed.',
            'approved' => 'Your report for :plan was approved.',
            'return_waiting' => 'We are waiting for your return for :plan.',
            'replacement_preparing' => 'Your replacement for :plan is being prepared.',
            'resolved' => 'Your support case for :plan was resolved.',
            'rejected' => 'Your support case for :plan was closed.',
            'updated' => 'Your support case for :plan was updated.',
        ],
    ],
];
