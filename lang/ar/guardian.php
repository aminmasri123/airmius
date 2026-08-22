<?php

return [
    'responses' => [
        'consent_resent' => 'تمت إعادة إرسال طلب الموافقة.',
        'consent_approved' => 'تم منح الموافقة.',
        'consent_revoked' => 'تم سحب الموافقة.',
        'registration_approved' => 'تم تأكيد التسجيل.',
        'registration_rejected' => 'تم رفض التسجيل.',
        'email_resent' => 'تمت إعادة إرسال البريد الإلكتروني.',
        'access_revoked' => 'تم سحب الموافقة.',
        'access_approved' => 'تم إلغاء الرفض ومنح الموافقة.',
        'account_created' => 'تم إنشاء حساب ولي الأمر. يمكنك تسجيل الدخول الآن.',
    ],
    'validation' => [
        'confirmation_required' => 'يرجى تأكيد أنك ولي الأمر القانوني للطفل.',
        'already_approved' => 'تم منح الموافقة بالفعل.',
        'guardian_email_missing' => 'لا يوجد بريد إلكتروني محفوظ لولي الأمر.',
        'consent_required' => 'تظل الميزات المحمية مقفلة حتى يمنح ولي الأمر موافقته.',
        'resend_wait' => 'انتظر :seconds ثانية أخرى قبل إعادة إرسال البريد الإلكتروني.',
    ],
    'notifications' => [
        'approved_title' => 'تم منح الموافقة',
        'approved_body' => 'وافق ولي الأمر على حسابك في Airmius.',
        'revoked_title' => 'تم سحب الموافقة',
        'revoked_body' => 'تم سحب الموافقة على حسابك في Airmius. يرجى مناقشة ذلك مع ولي أمرك.',
        'rejected_title' => 'تم رفض الموافقة',
        'rejected_body' => 'تم رفض الموافقة على حسابك في Airmius. يمكنك إرسال طلب جديد.',
    ],
];
