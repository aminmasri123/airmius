<?php

return [
    'flash' => [
        'profile_saved' => 'تم حفظ ملف الراعي. تتم الآن مراجعة البيانات العامة.',
        'created' => 'تم إنشاء الراعي.',
        'updated' => 'تم تحديث الراعي.',
        'deleted' => 'تم حذف الراعي.',
        'deliverable_created' => 'تم إنشاء الالتزام المتفق عليه.',
        'deliverable_updated' => 'تم تحديث الالتزام المتفق عليه.',
        'deliverable_deleted' => 'تم حذف الالتزام المتفق عليه.',
        'sponsor_notified' => 'تم إشعار الراعي.',
        'notification_failed' => 'تعذر إنشاء الإشعار.',
    ],
    'validation' => [
        'verification_incomplete' => 'ملف الراعي غير جاهز للتحقق بعد (:count متطلبات إلزامية متبقية).',
        'notification_recipient_missing' => 'لا يوجد حساب مستلم مرتبط بهذا الراعي.',
    ],
    'notifications' => [
        'review_requested_title' => 'ملف الراعي بانتظار المراجعة',
        'review_requested_body' => 'قام :sponsor بتغيير بيانات الملف العامة.',
        'review_approved_title' => 'تمت الموافقة على ملف الراعي',
        'review_approved_body' => 'تمت الموافقة على الملف العام لـ :sponsor.',
        'review_rejected_title' => 'لم تتم الموافقة على ملف الراعي',
        'review_rejected_body' => 'يحتاج ملف :sponsor إلى تعديلات. افتح لوحة التحكم لقراءة ملاحظة المراجعة.',
        'deliverable_update_title' => 'تحديث التزام الرعاية',
        'deliverable_update_body' => 'يرجى مراجعة الالتزام «:title» في لوحة تحكم الراعي.',
    ],
    'partnership' => [
        'requested' => 'تم إرسال طلب الشراكة.',
        'withdrawn' => 'تم سحب طلب الشراكة.',
        'duplicate' => 'توجد شراكة مفتوحة أو نشطة مع هذا النادي.',
        'withdraw_unavailable' => 'يمكن سحب طلبات الشراكة المفتوحة فقط.',
        'already_decided' => 'تمت معالجة طلب الشراكة هذا بالفعل.',
        'decided' => 'تمت معالجة طلب الشراكة.',
        'notification_title' => 'طلب شراكة جديد',
        'notification_body' => 'يرغب :sponsor في الشراكة مع :club.',
        'approved_title' => 'تم قبول طلب الشراكة',
        'approved_body' => 'وافق :club على طلب الشراكة.',
        'rejected_title' => 'تم رفض طلب الشراكة',
        'rejected_body' => 'رفض :club طلب الشراكة.',
    ],
];
