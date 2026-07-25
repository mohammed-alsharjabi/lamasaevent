# خطة الرجوع

## قبل التغيير

سجل commit وartifact، وخذ Snapshot متسقًا لقاعدة البيانات والوسائط وإعداد
Reverse Proxy. احتفظ بالنسخة القديمة كما هي ولا تحذف `public_html`.

## الرجوع من Staging

1. أوقف الكتابة وQueue worker للإصدار الفاشل.
2. أعد Reverse Proxy إلى artifact السابق.
3. استعد Snapshot قاعدة البيانات إذا نفذت migration غير متوافقة.
4. استعد storage عند وجود تغييرات وسائط.
5. شغّل health check وعينة المسارات وsitemap.
6. احتفظ بقاعدة الإصدار الفاشل للتحليل ولا تحذفها.

## الرجوع المستقبلي من Production

أعد origin/virtual host إلى النسخة القديمة المحفوظة بدل تغيير URLs أو حذف
redirects عشوائيًا. تحقق من جميع روابط الـManifest وCanonical وJSON-LD،
وراقب 404 و5xx. معيار النجاح هو عودة مجموعة الـ156 رابطًا نفسها دون اختلاف.

لا تستخدم `git reset --hard` أو حذفًا شاملًا كآلية Rollback تشغيلية؛ الرجوع
يتم عبر artifacts وSnapshots موثقة.
