<?php

return [
    'providers' => [
        'apple_health' => [
            'label' => 'Apple Health',
            'description' => 'يمكن لـ Apple Health استيراد بيانات التمارين والمسارات الموحّدة عبر HealthKit وبالأذونات التي تمنحها صراحةً.',
        ],
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
            'description' => 'يمكن ربط Mi Fitness عبر Android Health Connect أو استيراد ملف موحّد. لا تستقبل Airmius إلا حقول النشاط التي تسمح بها صراحةً.',
        ],
    ],
    'summary' => [
        'requested' => 'تم طلب الاتصال. سنبلغك عندما يصبح هذا المزوّد متاحًا.',
        'native_ready' => 'الاستيراد الموحّد الآمن جاهز. ابدأ الاستيراد من تطبيق Airmius للجوال.',
        'connected' => 'تم ربط الحساب. يمكنك المزامنة الآن.',
        'normalized_import_ready' => 'يمكن استيراد الأنشطة الموحّدة بأمان.',
        'activity_imported' => 'تم استيراد النشاط.',
    ],
    'flash' => [
        'requested' => 'تم طلب الاتصال بـ :provider.',
        'native_ready' => 'أصبح :provider جاهزًا للاستيراد الآمن عبر تطبيق Airmius للجوال.',
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
        'route_unavailable' => 'المسار المحدد غير متاح لحسابك.',
        'team_unavailable' => 'الفريق المحدد لا ينتمي إلى حسابك.',
        'route_team_mismatch' => 'المسار والفريق لا ينتميان إلى سياق التدريب نفسه.',
    ],
];
