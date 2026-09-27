<?php

return [
    'confirmation' => 'Yes, I am sure',
    'requested_title' => 'Club deletion scheduled',
    'requested_body' => 'Deletion of ":club" has been requested. Earliest deletion date: :date. The owner can cancel the request in the club workspace until deletion takes place.',
    'cancelled_title' => 'Club deletion cancelled',
    'cancelled_body' => 'Deletion of ":club" has been cancelled. The club and its data will be kept.',
    'reminder_title' => 'Club deletion reminder',
    'reminder_body' => '":club" is scheduled for deletion from :date. The owner can still cancel deletion in the club workspace.',
    'blocked_title' => 'Club deletion paused',
    'blocked_body' => '":club" was not deleted. A retention or subscription restriction requires review. Please open the club workspace.',
    'completed_title' => 'Club deleted',
    'completed_body' => '":club" and its operational club data have been deleted. Personal accounts and retained billing records remain.',
    'open' => 'Open club workspace',
    'retention' => 'SEPA history currently prevents complete deletion. Please contact support for review.',
    'finance_retention' => 'This club has accounting records. Automatic deletion is blocked until support has reviewed retention requirements.',
    'subscription' => 'An active payment-provider subscription must be ended before deleting the club.',
    'wrong_confirmation' => 'Please enter the full confirmation phrase shown.',
];
