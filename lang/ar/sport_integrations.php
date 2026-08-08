<?php

return [
    'providers' => [
        'google_fit' => [
            'label' => 'Google Fit',
            'description' => 'اتصل عبر Google OAuth. يمكن مزامنة الأنشطة باستخدام الرموز المحفوظة.',
        ],
        'garmin' => [
            'label' => 'Garmin',
            'description' => 'تتطلب Garmin Health API موافقة المزوّد. يمكنك تسجيل اهتمامك حتى يتاح وصول الشريك.',
        ],
        'strava' => [
            'label' => 'Strava',
            'description' => 'يربط Strava بيانات الجري وركوب الدراجات والسباحة والتمارين عبر واجهة OAuth الرسمية.',
        ],
        'fitbit' => [
            'label' => 'Fitbit',
            'description' => 'يمكن لـ Fitbit Web API توفير الأنشطة والخطوات والمسافة والسعرات والبيانات الصحية عبر OAuth. التكامل مخطط له.',
        ],
        'polar' => [
            'label' => 'Polar',
            'description' => 'يوفر Polar AccessLink بيانات التدريب والنشاط عبر OAuth/API. التكامل مخطط له.',
        ],
        'mi_fitness' => [
            'label' => 'Mi Fitness',
            'description' => 'لا يوفر Mi Fitness اتصال OAuth قياسيًا بسيطًا. سيتم تسجيل اهتمامك بهذا الاتصال.',
        ],
    ],
    'summary' => [
        'requested' => 'تم طلب الاتصال. سنبلغك عندما يصبح هذا المزوّد متاحًا.',
        'connected' => 'تم ربط الحساب. يمكنك المزامنة الآن.',
    ],
    'flash' => [
        'requested' => 'تم طلب الاتصال بـ :provider.',
        'not_configured' => 'لم تتم تهيئة :provider بعد. أضف معرّف العميل وسر العميل إلى البيئة.',
        'expired' => 'انتهت صلاحية اتصال :provider. اختر الاتصال مرة أخرى.',
        'token_exchange_failed' => 'تعذر على :provider استبدال رمز التفويض بالرموز: :error',
        'connected' => 'تم الاتصال بـ :provider.',
        'activity_created' => 'تمت إضافة جلسة التدريب.',
        'disconnected' => 'تم حذف اتصال التطبيق الرياضي.',
        'activity_deleted' => 'تم حذف النشاط المستورد.',
        'activity_renamed' => 'تمت إعادة تسمية النشاط المستورد.',
        'activities_deleted' => 'تم حذف :count من الأنشطة المستوردة.',
    ],
    'errors' => [
        'unknown_provider' => 'خطأ غير معروف من المزوّد',
    ],
];
