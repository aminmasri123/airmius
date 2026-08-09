<?php

return [
    'workspaces' => [
        'platform' => ['label' => 'تشغيل المنصة', 'description' => 'الموافقات ومهل الدعم والتسليم التقني.'],
        'trust' => ['label' => 'الثقة والسلامة', 'description' => 'حالات الإشراف والطعون والقرارات.'],
        'revenue' => ['label' => 'عمليات الإيرادات', 'description' => 'مشكلات الطلبات والمرتجعات والمدفوعات وحالات الملابس.'],
    ],
    'kinds' => [
        'club_verification' => 'التحقق من النادي', 'trainer_application' => 'طلب مدرب',
        'mail_delivery' => 'تسليم البريد', 'support_ticket' => 'تذكرة دعم',
        'moderation_flag' => 'تنبيه إشراف', 'content_report' => 'بلاغ محتوى',
        'content_appeal' => 'طعن', 'order_issue' => 'مشكلة طلب',
        'return_request' => 'طلب إرجاع', 'seller_application' => 'طلب بائع',
        'payout' => 'دفعة', 'outfit_issue' => 'حالة ملابس',
    ],
    'case_title' => ':kind رقم :id',
    'priorities' => ['urgent' => 'عاجل', 'high' => 'مرتفع', 'normal' => 'عادي', 'low' => 'منخفض'],
    'statuses' => [
        'pending' => 'مفتوح', 'pending_verification' => 'بانتظار التحقق', 'failed' => 'فشل',
        'open' => 'مفتوح', 'in_progress' => 'قيد المعالجة', 'waiting_user' => 'بانتظار الرد',
        'appeal_pending' => 'الطعن معلق', 'reported' => 'مُبلّغ عنه', 'reviewing' => 'قيد المراجعة',
        'requested' => 'مطلوب', 'approved' => 'مقبول', 'received' => 'مستلم',
        'prepared' => 'مُجهز', 'seller_recovery_required' => 'الاسترداد مطلوب',
        'return_waiting' => 'بانتظار الإرجاع', 'replacement_preparing' => 'جارٍ تجهيز البديل',
    ],
    'actions' => ['open_workspace' => 'فتح مساحة الاختصاص'],
    'timeline' => [
        'case_opened' => 'فُتحت الحالة', 'review_recorded' => 'سُجل قرار الإشراف',
        'commerce_recorded' => 'سُجل إجراء التجارة',
    ],
    'ui' => [
        'page_title' => 'مركز العمليات', 'eyebrow' => 'مستوى تحكم Airmius',
        'title' => 'صندوق واحد للحالات التشغيلية',
        'intro' => 'اعمل حسب الأولوية والمهلة. لا تُحمّل كل مساحة إلا الحد الأدنى من البيانات وعند فتحها فقط.',
        'privacy_badge' => 'عرض مُصغر ومتوافق مع GDPR', 'workspace_tabs' => 'مساحات العمل التشغيلية',
        'refresh' => 'تحديث',
        'loading' => 'جارٍ تحميل المساحة…', 'load_error' => 'تعذر تحميل مساحة العمل.',
        'retry' => 'إعادة المحاولة', 'cached' => 'مخزن مؤقتاً', 'live' => 'محمّل الآن',
        'metrics' => ['visible' => 'الحالات الظاهرة', 'urgent' => 'العاجلة', 'overdue' => 'المتأخرة', 'sources' => 'مصادر الحالات'],
        'filters' => [
            'title' => 'تصفية الحالات', 'search' => 'ابحث بالمرجع أو النوع أو الحالة',
            'priority' => 'الأولوية', 'source' => 'مصدر الحالة', 'all' => 'الكل', 'result' => ':count نتيجة',
        ],
        'cases' => [
            'title' => 'قائمة العمل المفتوحة', 'empty' => 'لا توجد حالات مفتوحة لهذا المرشح.',
            'opened' => 'فُتحت', 'due' => 'المهلة', 'overdue' => 'متأخرة', 'amount' => 'المبلغ',
            'metadata_only' => 'بيانات وصفية فقط — لم يُمنح وصول الاختصاص',
            'access' => 'الوصول',
            'more_available' => 'توجد حالات إضافية في مساحة الاختصاص. هذه النظرة محدودة عمداً.',
        ],
        'timeline' => [
            'title' => 'الخط الزمني للتدقيق', 'intro' => 'أحداث مصغرة بلا أسماء أو نص حر أو بيانات خام.',
            'empty' => 'لا توجد أحداث بعد.',
        ],
        'privacy' => [
            'title' => 'الخصوصية افتراضياً',
            'text' => 'لا يحتوي هذا العرض على أسماء أو عناوين بريد أو رسائل أو أسباب أو أخطاء نصية أو حمولات تدقيق خام.',
            'lazy' => 'تُحمّل المساحات عند الطلب عبر AJAX من دون استطلاع دوري.',
        ],
    ],
];
