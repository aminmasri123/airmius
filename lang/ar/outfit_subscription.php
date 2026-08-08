<?php

return [
    'responses' => [
        'profile_saved' => 'تم حفظ ملف الأسلوب.',
        'paypal_continue' => 'يمكنك متابعة الدفع عبر PayPal.',
        'requested' => 'تم طلب اشتراك الملابس.',
        'requested_web' => 'تم طلب اشتراك الملابس، ولن يُفعّل إلا بعد الدفع.',
        'issue_sent' => 'تم إرسال بلاغك.',
        'issue_sent_web' => 'تم إرسال بلاغك، وسيراجع فريقنا الشحنة.',
    ],
    'notifications' => [
        'pending_payment_title' => 'اشتراك الملابس بانتظار الدفع',
        'pending_payment_body' => 'تم حجز اشتراك الملابس :plan، ولن يُفعّل إلا بعد تأكيد الدفع.',
        'paid_title' => 'تم تفعيل اشتراك الملابس',
        'paid_body' => 'تم تأكيد دفعتك لخطة :plan، وأصبح اشتراك الملابس نشطاً الآن.',
        'requested_title' => 'طلب جديد لاشتراك الملابس',
        'requested_body' => 'طلب :user خطة :plan، وما زال الدفع معلقاً.',
        'issue_requested_title' => 'شحنة ملابس تحتاج إلى دعم',
        'issue_requested_body' => 'أبلغ :user عن مشكلة في شحنة ملابس.',
        'plan_fallback' => 'اشتراك الملابس',
        'unpaid_title' => 'دفعة اشتراك الملابس مستحقة',
        'unpaid_body' => 'تم تسجيل دفعة مستحقة لاشتراك الملابس :plan.:reason',
        'address_updated_title' => 'تم تحديث عنوان الشحن',
        'address_updated_body' => 'تم تحديث عنوان شحن اشتراك الملابس :plan.',
        'cancelled_title' => 'تم إلغاء طلب اشتراك الملابس',
        'cancelled_body' => 'تم إلغاء طلب اشتراك الملابس :plan.:reason',
        'issue_status_updated_title' => 'تم تحديث طلب الدعم',
        'issue_status' => [
            'reviewing' => 'بلاغك بشأن :plan قيد المراجعة.',
            'approved' => 'تمت الموافقة على بلاغك بشأن :plan.',
            'return_waiting' => 'ننتظر إعادة شحنتك بشأن :plan.',
            'replacement_preparing' => 'يجري تجهيز البديل الخاص بك لـ :plan.',
            'resolved' => 'تم حل طلب الدعم الخاص بك بشأن :plan.',
            'rejected' => 'تم إغلاق طلب الدعم الخاص بك بشأن :plan.',
            'updated' => 'تم تحديث طلب الدعم الخاص بك بشأن :plan.',
        ],
    ],
];
