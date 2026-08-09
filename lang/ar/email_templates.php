<?php

$source = require __DIR__.'/data_erasure.php';
$templates = $source['email_templates'] ?? [];
unset($templates['together']);

return array_replace($templates, [
    'account_welcome' => [
        'subject' => 'مرحبًا بك في Airmius',
        'greeting' => 'مرحبًا {{ name }}،',
        'body' => "مرحبًا بك في Airmius. تم إنشاء حسابك بنجاح.\nيمكنك الآن تسجيل الدخول وإكمال ملفك الشخصي واستخدام Airmius للتدريب والأندية والفرق والفعاليات وروتينك الرياضي اليومي.\nإذا لم تنشئ هذا الحساب، فتواصل مع دعم Airmius.",
        'action_label' => 'فتح Airmius',
    ],
    'account_created_with_credentials' => [
        'subject' => 'تم إنشاء حسابك في Airmius',
        'greeting' => 'مرحبًا {{ name }}،',
        'body' => "تم إنشاء حساب Airmius لك.\nالبريد الإلكتروني: {{ email }}\nكلمة المرور المؤقتة: {{ temporary_password }}\nيرجى تسجيل الدخول وتغيير كلمة المرور مباشرة بعد أول دخول.\nإذا لم تكن تتوقع إنشاء هذا الحساب، فتواصل مع دعم Airmius.",
        'action_label' => 'تسجيل الدخول إلى Airmius',
    ],
    'account_deletion_code' => [
        'subject' => 'رمز تأكيد حذف الحساب',
        'greeting' => 'مرحبًا،',
        'body' => "لقد طلبت حذف حسابك في Airmius.\nرمز التأكيد هو: {{ code }}\nالرمز صالح لمدة 15 دقيقة.\nإذا كنت لا تريد حذف حسابك، فيمكنك تجاهل هذه الرسالة.",
        'action_label' => '',
    ],
    'account_deletion_completed' => [
        'subject' => 'تم حذف حسابك في Airmius',
        'greeting' => 'مرحبًا {{ name }}،',
        'body' => "تم حذف حسابك في Airmius بنجاح.\nتؤكد هذه الرسالة اكتمال حذف الحساب.\nإذا لم تطلب هذا الحذف، فتواصل مع دعم Airmius.",
        'action_label' => '',
    ],
    'guardian_access_code' => [
        'subject' => 'رمز دخول ولي الأمر في Airmius',
        'greeting' => 'مرحبًا،',
        'body' => "لقد طلبت رمز دخول إلى مساحة ولي الأمر في Airmius.\nرمزك هو: {{ code }}\nالرمز صالح لمدة 15 دقيقة.\nإذا لم تطلب هذا الرمز، فيمكنك تجاهل هذه الرسالة.",
        'action_label' => '',
    ],
    'guardian_consent_requested' => [
        'subject' => 'الموافقة على التسجيل في Airmius',
        'greeting' => 'مرحبًا،',
        'body' => "سجّل {{ minor_name }} في Airmius وعمره أقل من 16 عامًا.\nيرجى مراجعة الطلب. يمكنك الموافقة على التسجيل أو رفضه.\nإذا لم تكن تتوقع هذا الطلب، فيمكنك تجاهل هذه الرسالة.",
        'action_label' => 'الموافقة أو الرفض',
    ],
    'password_reset' => [
        'subject' => 'إعادة تعيين كلمة المرور',
        'greeting' => 'مرحبًا!',
        'body' => "تلقيت هذه الرسالة لأننا استلمنا طلبًا لإعادة تعيين كلمة مرور حسابك.\nتنتهي صلاحية هذا الرابط خلال {{ expires_minutes }} دقيقة.",
        'action_label' => 'إعادة تعيين كلمة المرور',
    ],
    'contact_form_admin' => [
        'subject' => 'طلب جديد من نموذج التواصل من {{ name }}',
        'greeting' => 'طلب تواصل جديد',
        'body' => "الاسم: {{ name }}\nالبريد الإلكتروني: {{ email }}\n\n{{ message }}",
        'action_label' => '',
    ],
    'login_successful' => [
        'subject' => 'تسجيل دخول جديد إلى Airmius',
        'greeting' => 'مرحبًا،',
        'body' => "تم للتو تسجيل دخول ناجح إلى حسابك في Airmius.\nالوقت: {{ logged_in_at }}\nعنوان IP: {{ ip_address }}\nالجهاز/المتصفح: {{ user_agent }}\nإذا كنت أنت، فلا يلزم اتخاذ أي إجراء.\nإذا لم تكن أنت، فغيّر كلمة المرور فورًا وتواصل مع دعم Airmius.",
        'action_label' => '',
    ],
    'login_two_factor_code' => [
        'subject' => 'رمز الأمان الخاص بك في Airmius',
        'greeting' => 'مرحبًا {{ name }}،',
        'body' => "رمز أمان تسجيل الدخول هو: {{ code }}\nالرمز صالح لمدة {{ expires_minutes }} دقيقة ولا يمكن استخدامه إلا مرة واحدة.\nإذا لم تبدأ تسجيل الدخول هذا، فغيّر كلمة المرور.",
        'action_label' => '',
    ],
    'login_lockout' => [
        'subject' => 'محاولات تسجيل دخول فاشلة متعددة في Airmius',
        'greeting' => 'مرحبًا،',
        'body' => "تم اكتشاف عدة محاولات تسجيل دخول فاشلة إلى حسابك في Airmius.\nتم حظر تسجيل الدخول مؤقتًا لحماية حسابك.\nالوقت: {{ locked_at }}\nعنوان IP: {{ ip_address }}\nالجهاز/المتصفح: {{ user_agent }}\nإذا كنت أنت، فانتظر قليلًا ثم حاول مرة أخرى.\nإذا لم تكن أنت، فغيّر كلمة المرور وراجع أمان حسابك.",
        'action_label' => '',
    ],
    'account_suspended' => [
        'subject' => 'تم تعليق حسابك في Airmius مؤقتًا',
        'greeting' => 'مرحبًا {{ name }}،',
        'body' => "تم تعليق حسابك في Airmius مؤقتًا.\nالسبب: {{ reason }}\nمعلق حتى: {{ suspended_until }}\nإذا كنت تعتقد أن التعليق غير صحيح، فتواصل مع الدعم واطلب المراجعة.",
        'action_label' => 'التواصل مع الدعم',
    ],
    'inactive_account_first' => [
        'subject' => 'حسابك في Airmius غير نشط منذ مدة',
        'greeting' => 'مرحبًا {{ name }}،',
        'body' => "لم يُستخدم حسابك في Airmius منذ مدة.\nلأسباب تتعلق بالخصوصية، نراجع الحسابات غير النشطة بانتظام.\nإذا أردت مواصلة استخدام Airmius، فسجّل الدخول مجددًا ليبقى حسابك نشطًا.",
        'action_label' => 'تسجيل الدخول إلى Airmius',
    ],
    'inactive_account_second' => [
        'subject' => 'تذكير: حسابك في Airmius ما زال غير نشط',
        'greeting' => 'مرحبًا {{ name }}،',
        'body' => "ما زال حسابك في Airmius غير نشط.\nسيؤدي تسجيل الدخول مجددًا إلى إلغاء مراجعة الخصوصية المجدولة.\nفي حال عدم الاستجابة، قد يُعطّل حسابك لاحقًا وتُزال هويته.",
        'action_label' => 'إبقاء الحساب نشطًا',
    ],
    'inactive_account_scheduled' => [
        'subject' => 'جدولة إزالة هوية حساب Airmius',
        'greeting' => 'مرحبًا {{ name }}،',
        'body' => "حسابك في Airmius غير نشط منذ مدة.\nلذلك جرت جدولة إزالة هويته لأسباب تتعلق بالخصوصية.\nالتاريخ المجدول: {{ scheduled_date }}\nإذا سجلت الدخول قبل هذا التاريخ، فسيبقى حسابك نشطًا.",
        'action_label' => 'إبقاء الحساب نشطًا',
    ],
    'subscription_invoice_reminder' => [
        'subject' => 'تذكير: فاتورة Airmius رقم {{ invoice_number }} ما زالت مستحقة',
        'greeting' => 'مرحبًا {{ name }}،',
        'body' => "لم تُسجل دفعة لفاتورة Airmius حتى الآن.\nالفاتورة: {{ invoice_number }}\nالخطة: {{ plan_name }}\nالمبلغ: {{ amount }}\nمستحقة منذ: {{ due_date }}\nمرجع الدفع: {{ payment_reference }}\nإذا كنت قد دفعت بالفعل، فيمكنك تجاهل هذا التذكير. ستُعلّم الدفعة بعد المطابقة البنكية.",
        'action_label' => 'تنزيل الفاتورة',
    ],
    'commerce_order_completed' => [
        'subject' => 'تم تأكيد طلب Airmius',
        'greeting' => 'مرحبًا {{ name }}،',
        'body' => "تم تأكيد طلبك بنجاح.\nالطلب: {{ order_title }}\nالمبلغ: {{ amount }}\nالحالة: مدفوع\nشكرًا لاستخدامك Airmius.",
        'action_label' => 'عرض السوق',
    ],
    'external_club_membership_invitation' => [
        'subject' => 'دعوة للانضمام إلى {{ club_name }} على Airmius',
        'greeting' => 'مرحبًا {{ name }}،',
        'body' => "دعاك {{ inviter_name }} بالنيابة عن {{ club_name }} ويريد ربطك بمنصة Airmius.\nباستخدام حساب Airmius، يمكنك متابعة بيانات النادي والفواتير وسجل المدفوعات والفرق والرسائل.\nإذا كان لديك حساب بهذا البريد الإلكتروني، فسجّل الدخول لإكمال الربط. وإلا فسجّل بهذا البريد الإلكتروني.",
        'action_label' => 'عرض الدعوة',
    ],
    'external_team_invitation' => [
        'subject' => 'دعوة للانضمام إلى {{ team_name }}',
        'greeting' => 'مرحبًا،',
        'body' => "دعاك {{ inviter_name }} للانضمام إلى {{ team_name }} على Airmius.\nاقبل الدعوة للانضمام إلى الفريق.",
        'action_label' => 'قبول الدعوة',
    ],
    'external_friend_invitation' => [
        'subject' => 'يريد {{ sender_name }} التواصل معك على Airmius',
        'greeting' => 'مرحبًا،',
        'body' => "دعاك {{ sender_name }} للتواصل كصديق على Airmius.\nإذا كان لديك حساب بهذا البريد الإلكتروني، فسجّل الدخول واقبل الدعوة. وإلا فسجّل بهذا البريد الإلكتروني.",
        'action_label' => 'قبول الدعوة',
    ],
]);
