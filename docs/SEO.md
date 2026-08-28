# عقد SEO والحفاظ على الروابط

## المرجع غير القابل للتفاوض

`route-manifest.json` يحتوي 156 رابطًا مطابقًا لـ`legacy sitemap.xml` ذات
البصمة:

`65765fca188fcb24f884163a785b97343ad273485c2c8efe7158411541c95130`

لا يغير التطبيق أي مسار أو trailing slash أو ترتيب sitemap موروث. حقول title،
Meta Description، Canonical، robots، Open Graph، Twitter وSchema JSON-LD
تستورد حرفيًا وتختبر لكل مسار.

## تغيير Slug

- حقل Slug غير موجود في لوحة Filament.
- عند إنشاء سجل جديد، يولد Backend قيمة فريدة داخليًا مرة واحدة ولا يغيرها
  عند تعديل العنوان، سواء كان السجل مسودة أو منشورًا.
- التغيير الاستثنائي عبر `SlugRedirectService` ينشئ Redirect دائمًا داخل
  transaction، ويرفض الحلقات والتعارضات، ثم يحدث Route Registry وCanonical
  وOpen Graph وsitemap.
- يمنع Model Observer أي تعديل مباشر يتجاوز الخدمة.

## Sitemap

حقلا `loc` و`path` وحالة تضمين الروابط الموروثة مقفلة في لوحة التحكم. يمكن
تعديل `lastmod`, `changefreq`, `priority` فقط. `legacy:verify` يقارن مجموعة
الروابط بقاعدة البيانات، واختبار العقد يقارن XML المبني كاملًا بالـManifest.
الصفحات الجديدة تضاف تلقائيًا بعد الصفوف الموروثة؛ يتحقق الاختبار من بقاء
الصفوف الـ156 القديمة حرفيًا وبالترتيب نفسه ومن عدم تكرار أي رابط جديد.

## اختبارات القبول

- `npm run test:contract`: كل المسارات وSEO وJSON-LD والروابط الداخلية.
- `node scripts/check-links.mjs`: يتحقق من أهداف الروابط الداخلية.
- `php artisan legacy:verify`: يطابق Laravel مع المرجع.
- `npm run test:visual`: مقارنة بكسل للأنواع الرئيسية بحد 0.5%.

Staging تستخدم Canonical الإنتاجي للحفاظ على العقد، لكن يمنع فهرستها عبر
Basic Auth وترويسة `X-Robots-Tag: noindex, nofollow, noarchive`.
