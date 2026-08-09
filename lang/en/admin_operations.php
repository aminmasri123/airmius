<?php

return [
    'workspaces' => [
        'platform' => ['label' => 'Platform Ops', 'description' => 'Approvals, support SLAs, and technical delivery.'],
        'trust' => ['label' => 'Trust & Safety', 'description' => 'Moderation cases, appeals, and decisions.'],
        'revenue' => ['label' => 'Revenue Ops', 'description' => 'Order issues, returns, payouts, and outfit cases.'],
    ],
    'kinds' => [
        'club_verification' => 'Club verification', 'trainer_application' => 'Coach application',
        'mail_delivery' => 'Mail delivery', 'support_ticket' => 'Support ticket',
        'moderation_flag' => 'Moderation flag', 'content_report' => 'Content report',
        'content_appeal' => 'Appeal', 'order_issue' => 'Order issue',
        'return_request' => 'Return', 'seller_application' => 'Seller application',
        'payout' => 'Payout', 'outfit_issue' => 'Outfit case',
    ],
    'case_title' => ':kind #:id',
    'priorities' => ['urgent' => 'Urgent', 'high' => 'High', 'normal' => 'Normal', 'low' => 'Low'],
    'statuses' => [
        'pending' => 'Open', 'pending_verification' => 'Verification pending', 'failed' => 'Failed',
        'open' => 'Open', 'in_progress' => 'In progress', 'waiting_user' => 'Waiting for reply',
        'appeal_pending' => 'Appeal pending', 'reported' => 'Reported', 'reviewing' => 'Under review',
        'requested' => 'Requested', 'approved' => 'Approved', 'received' => 'Received',
        'prepared' => 'Prepared', 'seller_recovery_required' => 'Recovery required',
        'return_waiting' => 'Waiting for return', 'replacement_preparing' => 'Replacement being prepared',
    ],
    'actions' => ['open_workspace' => 'Open in specialist workspace'],
    'timeline' => [
        'case_opened' => 'Case opened', 'review_recorded' => 'Moderation decision recorded',
        'commerce_recorded' => 'Commerce action recorded',
    ],
    'ui' => [
        'page_title' => 'Operations Center', 'eyebrow' => 'Airmius Control Plane',
        'title' => 'One inbox for operational cases',
        'intro' => 'Work by priority and deadline. Each workspace loads only the minimum necessary data and stays inactive until opened.',
        'privacy_badge' => 'GDPR-minimised view', 'workspace_tabs' => 'Operations workspaces',
        'refresh' => 'Refresh',
        'loading' => 'Loading workspace …', 'load_error' => 'The workspace could not be loaded.',
        'retry' => 'Try again', 'cached' => 'Cached', 'live' => 'Loaded now',
        'metrics' => ['visible' => 'Visible cases', 'urgent' => 'Urgent', 'overdue' => 'Overdue', 'sources' => 'Case sources'],
        'filters' => [
            'title' => 'Filter cases', 'search' => 'Search reference, type, or status',
            'priority' => 'Priority', 'source' => 'Case source', 'all' => 'All', 'result' => ':count results',
        ],
        'cases' => [
            'title' => 'Open work queue', 'empty' => 'There are no open cases for this filter.',
            'opened' => 'Opened', 'due' => 'Target time', 'overdue' => 'Overdue', 'amount' => 'Amount',
            'metadata_only' => 'Metadata only – specialist access not granted',
            'access' => 'Access',
            'more_available' => 'More cases are available in the specialist workspace. This overview is intentionally capped.',
        ],
        'timeline' => [
            'title' => 'Audit timeline', 'intro' => 'Minimised events without people, free text, or raw data.',
            'empty' => 'No events yet.',
        ],
        'privacy' => [
            'title' => 'Privacy by default',
            'text' => 'This projection contains no names, email addresses, messages, reasons, error text, or unprocessed audit payloads.',
            'lazy' => 'Workspaces load via AJAX on demand; there is no polling.',
        ],
    ],
];
