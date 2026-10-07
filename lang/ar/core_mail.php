<?php

$catalog = require __DIR__.'/../de/core_mail.php';

$catalog['common'] = [
    'greeting' => 'مرحبًا :name،', 'together' => 'بكم', 'not_set' => 'لم يُحدَّد بعد', 'salutation' => 'مع أطيب التحيات، فريق Airmius',
    'fields' => [
        'subscription' => 'الاشتراك: :value', 'amount' => 'المبلغ: :value', 'due_on' => 'موعد الاستحقاق: :value', 'due_since' => 'مستحق منذ: :value',
        'payment_reference' => 'مرجع الدفع: :value', 'delivery_month' => 'شهر التسليم: :value', 'shipping' => 'الشحن: :value',
        'tracking_link' => 'رابط التتبع: :value', 'note' => 'ملاحظة: :value', 'invoice_number' => 'رقم الفاتورة: :value', 'title' => 'العنوان: :value',
        'old_status' => 'الحالة السابقة: :value', 'new_status' => 'الحالة الجديدة: :value', 'status' => 'الحالة: :value', 'club' => 'النادي: :value',
        'country' => 'البلد: :value', 'requested_club_number' => 'رقم النادي المطلوب: :value', 'account_holder' => 'صاحب الحساب: :value',
        'iban' => 'IBAN: :value', 'bic' => 'BIC: :value', 'purpose' => 'مرجع التحويل: :value', 'message' => 'الرسالة: :value',
    ],
    'actions' => [
        'invoice' => 'عرض الفاتورة', 'review_club' => 'مراجعة الطلب', 'open_club' => 'فتح النادي', 'marketplace' => 'فتح السوق',
        'outfit' => 'عرض اشتراك الملابس', 'outfits' => 'عرض اشتراكات الملابس', 'verify_email' => 'تأكيد عنوان البريد الإلكتروني',
        'trainer_cockpit' => 'فتح لوحة المدرب', 'view_application' => 'عرض الطلب', 'review_trainers' => 'مراجعة طلبات المدربين',
    ],
];
$catalog['verification'] = [
    'subject' => 'تأكيد عنوان بريدك الإلكتروني في Airmius', 'body' => 'أكّد عنوان بريدك الإلكتروني لتفعيل حسابك في Airmius بالكامل.',
    'security_note' => 'تنتهي صلاحية رابط الأمان هذا. إذا لم تنشئ الحساب، فيمكنك تجاهل هذه الرسالة.',
];
$catalog['invoice'] = array_replace_recursive($catalog['invoice'], [
    'new_subject' => 'فاتورة جديدة من :sender', 'new_body' => 'لقد استلمت فاتورة جديدة.', 'club_body' => 'لقد استلمت فاتورة جديدة من :club.',
    'reminder_subject' => 'تذكير بالدفع للفاتورة :invoice', 'reminder_body' => 'الفاتورة :invoice مستحقة. يرجى مراجعة المبلغ المتبقي.',
    'settle_if_open' => 'يرجى مراجعة الفاتورة وسدادها في الموعد إذا كانت لا تزال مستحقة.', 'settle' => 'يرجى مراجعة الفاتورة وسدادها في الموعد.',
    'status_subject' => 'تم تحديث حالة فاتورتك', 'status_body' => 'تم تحديث حالة فاتورتك.',
    'status_review' => 'يرجى مراجعة فواتيرك إذا كان هناك مبلغ لا يزال مستحقًا.', 'bank_transfer' => 'الدفع عن طريق التحويل المصرفي:', 'club_fallback' => 'ناديك',
    'statuses' => ['paid' => 'مدفوعة', 'open' => 'مفتوحة', 'pending' => 'قيد الانتظار', 'awaiting_transfer' => 'بانتظار التحويل المصرفي', 'overdue' => 'متأخرة', 'cancelled' => 'ملغاة', 'failed' => 'فشلت'],
]);
$catalog['club_registration'] = [
    'review_subject' => 'طلب نادٍ جديد: :club', 'review_body' => 'سجّل :applicant ناديًا.', 'submitted_subject' => 'تم إرسال طلب ناديك',
    'submitted_body' => 'تم إنشاء ناديك «:club» وهو الآن بانتظار المراجعة.', 'owner_body' => 'تم تسجيلك فورًا كمالك للنادي ويمكنك إدارته من لوحة المعلومات.',
    'visibility_body' => 'لن يظهر النادي للعامة ولن يُوسَم كنادٍ رسمي إلا بعد الموافقة.',
];
$catalog['commerce_return'] = [
    'subject' => 'تم تحديث الإرجاع', 'body' => 'تم تحديث طلب إرجاع :item.', 'item_fallback' => 'طلبك',
    'default_note' => 'يمكنك الاطلاع على الحالة الحالية في قسم السوق لديك.',
    'statuses' => ['requested' => 'تم الطلب', 'approved' => 'تمت الموافقة', 'received' => 'تم الاستلام', 'refunded' => 'تم رد المبلغ', 'rejected' => 'مرفوض'],
];
$catalog['outfit'] = array_replace_recursive($catalog['outfit'], [
    'plan_fallback' => 'اشتراك الملابس', 'carrier_fallback' => 'خدمة التوصيل', 'payment_reminder_subject' => 'تذكير: دفعة اشتراك الملابس مستحقة',
    'payment_reminder_body' => 'لم نستلم بعد دفعة اشتراك الملابس الخاص بك.', 'dunning_subject' => 'تذكير الدفع رقم :level: دفعة اشتراك الملابس مستحقة',
    'final_dunning_subject' => 'التذكير الأخير: دفعة اشتراك الملابس مستحقة', 'dunning_body' => 'هناك دفعة مستحقة لاشتراك الملابس النشط الخاص بك.',
    'dunning_continue' => 'يرجى سداد الدفعة حتى يستمر اشتراك الملابس دون انقطاع.',
    'dunning_paused' => 'تم إيقاف اشتراك الملابس مؤقتًا حتى استلام الدفعة. لن يتم تجهيز أي عمليات تسليم أخرى خلال هذه الفترة.',
    'expired_subject' => 'تم حذف طلب اشتراك الملابس', 'expired_body' => 'تم حذف طلب اشتراك الملابس لعدم استلام دفعة خلال 9 أيام.',
    'expired_restart' => 'يمكنك بدء طلب جديد في أي وقت إذا كنت لا تزال ترغب في استخدام اشتراك الملابس.',
    'delivery_subjects' => ['planned' => 'تمت جدولة تسليم ملابسك', 'preparing' => 'يجري تجهيز تسليم ملابسك', 'shipped' => 'تم شحن ملابسك', 'delivered' => 'تم تسليم ملابسك', 'cancelled' => 'تم إلغاء تسليم ملابسك'],
    'delivery_bodies' => ['planned' => 'تمت جدولة صندوق ملابسك الرياضية التالي.', 'preparing' => 'يجري تجهيز صندوق ملابسك الرياضية.', 'shipped' => 'تم شحن صندوق ملابسك الرياضية.', 'delivered' => 'تم وضع علامة تم التسليم على صندوق ملابسك الرياضية.', 'cancelled' => 'تم إلغاء صندوق ملابسك الرياضية.'],
]);
$catalog['trainer'] = [
    'approved_subject' => 'تمت الموافقة على طلب المدرب الخاص بك', 'rejected_subject' => 'تم رفض طلب المدرب الخاص بك',
    'approved_body' => 'راجعت Airmius طلب المدرب الخاص بك ووافقت عليه. سيظل وصولك كمدرب مفعّلًا.',
    'rejected_body' => 'رفضت Airmius طلب المدرب الخاص بك.', 'disabled_body' => 'تم تعطيل وصولك كمدرب مرة أخرى.',
    'review_subject' => 'طلب مدرب جديد: :applicant', 'review_body' => 'يرغب :applicant في استخدام قسم المدربين على Airmius.',
    'review_pending' => 'تم تفعيل وصول المدرب فورًا وهو بانتظار المراجعة من Airmius.',
];

return $catalog;
