<?php

return [
    'flash' => [
        'created' => 'تم إنشاء الفرصة.',
        'updated' => 'تم تحديث الفرصة.',
        'deleted' => 'تم حذف الفرصة.',
        'interest_sent' => 'تم إرسال إبداء اهتمامك.',
    ],
    'pipeline' => [
        'updated' => 'تم حفظ حالة الطلب.',
        'erased' => 'تم حذف الطلب وبيانات الاتصال.',
    ],
    'validation' => [
        'profile_login_required' => 'سجّل الدخول لمشاركة بيانات الملف مع هذا الطلب.',
        'profile_consent_required' => 'أكّد مشاركة الملف المقيدة بهذا الغرض.',
        'invalid_transition' => 'تغيير الحالة هذا غير مسموح في مسار التوظيف.',
        'chat_not_available' => 'تتطلب محادثة الطلب حساباً مرتبطاً وموافقة صريحة على التواصل.',
    ],
    'chat' => [
        'name' => 'طلب: :title',
        'description' => 'محادثة طلب محمية. شارك فقط المعلومات اللازمة لهذا الإجراء.',
    ],
    'notifications' => [
        'chat_title' => 'تم فتح محادثة الطلب',
        'chat_body' => 'بدأ النادي محادثة بشأن طلبك لفرصة «:title».',
        'offer_title' => 'عرض لطلبك',
        'offer_body' => 'قدم لك :club عرضاً لفرصة «:title». يمكنك الآن فتح مسار العضوية.',
        'hired_title' => 'تم قبول طلبك',
        'hired_body' => 'قبل :club طلبك لفرصة «:title». أكمل الآن مسار العضوية.',
    ],
    'mail' => [
        'subject' => 'إبداء اهتمام جديد: :title',
        'greeting' => 'مرحباً،',
        'intro' => 'تم إرسال إبداء اهتمام جديد بالفرصة «:title» لدى :club.',
        'name' => 'الاسم: :name',
        'email' => 'البريد الإلكتروني: :email',
        'phone' => 'الهاتف: :phone',
        'message' => 'الرسالة: :message',
        'action' => 'فتح صفحة الفرص',
    ],
];
