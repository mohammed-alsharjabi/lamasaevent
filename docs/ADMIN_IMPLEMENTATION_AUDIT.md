# تدقيق تنفيذ لوحة إدارة موقع لمسة

تاريخ التدقيق: 2026-07-25  
الفرع: `feature/admin-cms`  
المستودع: `mohammed-alsharjabi/lamasaevent`  
مرجع النسخة القديمة: `/Users/mohammedalsharjabi/Desktop/public_html` (قراءة فقط)

## 1. الملخص التنفيذي

المشروع الحالي ليس نسخة أولية فارغة؛ هو استعادة عاملة تتكون من Laravel 12
في `backend/` وAstro 7 في `frontend/`. جرى استيراد المحتوى الحقيقي إلى
SQLite محلية وإنتاج 156 صفحة ثابتة مطابقة لمسارات `sitemap.xml`. توجد لوحة
Blade مخصصة بسيطة وAPI تصدير وImporter ومعالجة WebP واختبارات لعقد المسارات
والمقارنة المرئية.

الأساس الحالي يحافظ على SEO والواجهة، لكنه لا يحقق بعد متطلبات CMS
الاحترافية: لا يوجد Filament، ولا RBAC دقيق، ولا تصنيفات مقالات أو FAQ أو
قوائم أو مراجعات أو مهام نشر، والاختبارات الإدارية وE2E والتشغيل الموحد من
جذر المشروع محدودة. لذلك ستتم ترقية الـBackend تدريجيًا دون إعادة تصميم
الواجهة العامة أو تغيير أي slug قديم.

## 2. بنية المشروع الحالية

```text
/
├── backend/                 Laravel 12، لوحة Blade الحالية، API، Importer
├── frontend/                Astro 7 static output
├── docs/                    توثيق المشروع (يبدأ بهذا التقرير)
├── scripts/                 manifest، مزامنة assets، staging
├── staging/                 Gateway محلي يضيف noindex
├── tests/                   Route contract
├── route-manifest.json      عقد 156 رابطًا وبصمات SEO وHTML
├── RECOVERY_AUDIT.md        تقرير الاستعادة الأول
└── README.md
```

لن يعاد نقل `backend/` و`frontend/` إلى `apps/` في هذه المرحلة؛ النقل لا
يضيف قيمة تشغيلية ويزيد خطر كسر المسارات والـscripts. سيبقى الفصل الحالي
موثقًا كالتالي:

- `backend/` = CMS/API.
- `frontend/` = الموقع العام.
- النسخة القديمة خارج المستودع وتقرأ عبر `LEGACY_DIST`.

## 3. الإصدارات والاعتماديات

- PHP: `^8.2`.
- Laravel: `^12.0`.
- Astro: `^7.1.3`.
- Node المطلوب للواجهة: `>=22.12.0`.
- Filament غير مثبت حاليًا.
- لا توجد مكتبة صلاحيات أو تحليل PHP ثابت.
- Playwright وPixelmatch موجودان في الواجهة.

القرار: استخدام Filament 5، وهو الإصدار المستقر الحالي والمدعوم، ومتوافق مع
PHP 8.2+ وLaravel 12. ستستخدم Laravel Policies مع نموذج Roles/Permissions
محلي واضح لتجنب ربط صلاحيات المشروع بإضافة غير ضرورية.

## 4. المسارات والمحتوى الواجب الحفاظ عليه

`route-manifest.json` هو مصدر القبول الثابت:

| النوع | العدد |
|---|---:|
| الرئيسية | 1 |
| فهرس الخدمات | 1 |
| معرض الصور | 1 |
| فهرس المدونة | 1 |
| من نحن | 1 |
| التواصل | 1 |
| فهرس المناطق | 1 |
| تصنيفات الخدمات | 10 |
| الخدمات | 43 |
| المناطق | 5 |
| المقالات | 91 |
| **الإجمالي** | **156** |

بصمة `legacy sitemap.xml`:

```text
65765fca188fcb24f884163a785b97343ad273485c2c8efe7158411541c95130
```

كل رابط يملك في الـManifest:

- URL ومسار HTML القديم وبصمته.
- Title وMeta Description وCanonical وRobots.
- Open Graph وTwitter Cards.
- JSON-LD وبصمته.
- الروابط الداخلية.
- lastmod وchangefreq وpriority.

لا توجد Canonical مكررة، وكل الصفحات الـ156 تحتوي JSON-LD.

## 5. المكونات والواجهة العامة

المكونات المشتركة المستخرجة:

- `Header.astro`
- `Footer.astro`
- `BookingForm.astro`
- `FloatDock.astro`
- `BaseLayout.astro`
- `LegacyContent.astro`

المحتوى الحالي يأتي من
`frontend/src/data/content-export.json`، ويعرض عبر catch-all static route.
CSS وJavaScript والصور الأصلية منسوخة في `frontend/public` مع الحفاظ على
أسمائها ومساراتها. التصميم العام مطابق بصريًا للمرجع وفق Playwright.

## 6. SEO والملفات العامة

- `sitemap.xml` يولد في Astro من بيانات Laravel وبنفس ترتيب وروابط المرجع.
- `robots.txt` العام يسمح بالفهرسة ويربط sitemap الإنتاج.
- Staging Gateway يفرض `X-Robots-Tag: noindex` و`Disallow: /`.
- ملفات Google Verification والملف النصي الحالي محفوظة.
- Base Layout يحافظ على Canonical وOG وTwitter وJSON-LD.
- صفحة 404 الأصلية محفوظة.

المشكلة: تشغيل Astro المحلي مباشرة يقدم `robots.txt` الإنتاجي. سيضاف توليد
بيئي يضمن `Disallow: /` في Local وStaging مع إبقاء ملف الإنتاج دون تغيير
عند build الإنتاج المرشح.

## 7. قاعدة البيانات الحالية

الجداول الموجودة:

- users, sessions, cache, jobs, failed_jobs.
- articles, services, service_categories, areas, pages.
- galleries, gallery_media, media, mediaables.
- seo_meta, route_registry, redirects, sitemap_entries.
- contact_settings, site_settings, activity_logs.

نقاط القوة:

- Unique indexes للـslugs والمسارات وCanonical.
- Soft Deletes لمعظم المحتوى والوسائط.
- Foreign keys أساسية.
- قفل slug المنشور وخدمة Redirect 301 داخل transaction.
- حالات draft/published/scheduled وتواريخ نشر.

النواقص:

- roles, permissions, role_user/permission_role.
- article_categories وارتباطها بالمقالات.
- service_area.
- faqs وارتباطاتها polymorphic.
- gallery_items ككيان قابل للتحرير بدل pivot محدود.
- menus وmenu_items.
- content_revisions.
- publish_jobs.
- created_by وupdated_by وحقول featured/CTA والكلمات الإدارية.
- counters وآخر استخدام للتحويلات.
- فهارس إضافية للبحث وحالة النشر.

سيضاف migration توسعي ولا تعدل migration الاستعادة الأصلية، لضمان ترقية
قواعد البيانات الحالية وإمكانية تشغيل migrations من قاعدة فارغة.

## 8. لوحة الإدارة الحالية ومشكلاتها

لوحة Blade الحالية توفر تسجيل دخول وCRUD عام ووسائط وتحويلات وإعدادات
وsitemap، لكنها:

- تعتمد `is_admin` فقط دون صلاحيات مورد/عملية.
- لا توفر Restore/Force Delete أو مراجعات أو معاينة ونشر مجدول متكامل.
- محرر المحتوى JSON خام وغير مناسب للعميل.
- لا توجد لوحة مؤشرات تشغيلية كاملة.
- لا توجد مكتبة وسائط متعددة الرفع أو تتبع استخدام شامل.
- لا توجد إدارة مستخدمين أو أدوار.
- لا توجد إدارة FAQ أو القوائم أو تصنيفات المقالات.
- لا يوجد UX عربي RTL متكامل على Filament.

ستستبدل مسارات `/admin` بPanel Filament مع الاحتفاظ بخدمات الأعمال الحالية
وتطويرها. ستبقى صلاحيات Laravel Policies هي مصدر القرار في الـBackend.

## 9. المعمارية المستهدفة للربط

```text
Filament Admin
      │
      ▼
Laravel Actions / Services ──► MySQL (production)
      │                           SQLite (local/tests only)
      ├── revisions / audit / publish jobs
      ├── cache invalidation
      └── versioned content snapshot
                      │
                      ▼
             Central Astro data client
             validation + timeout + fallback
                      │
                      ▼
              Astro static generation
```

لن تستدعي صفحات Astro الـAPI منفردة. سيضاف Client مركزي يقرأ snapshot
معتمدًا وقت البناء، يتحقق من schema، ويعود إلى آخر snapshot صالح عند تعذر
الـAPI. النشر الحقيقي لن ينفذ؛ ستجهز Jobs وسجل builds ووثيقة atomic release.

## 10. خطة الاستيراد

الأمر الحالي `legacy:import`:

- يتحقق من بصمة sitemap وكل HTML.
- idempotent باستخدام `updateOrCreate`.
- يستخرج main blocks وSEO والصور.
- يحافظ على slugs والمسارات.

سيستكمل بـ:

- `legacy:inspect`: تقرير قراءة فقط.
- `legacy:verify`: مقارنة DB/ملفات التصدير بالـManifest.
- تقرير JSON/Markdown للإضافات والتحديثات والتجاوزات والأخطاء.
- عدم حذف أي سجل.
- Dry-run قبل كل استيراد فعلي.
- اختبارات idempotency وحفظ slugs والصور.

## 11. خطة SEO

1. اعتبار `route-manifest.json` عقد قبول لا يتغير.
2. قفل كل slug قديم بعد النشر.
3. Redirect 301 تلقائي وآمن لأي تغيير مصرح به.
4. تحديث الروابط الداخلية والسitemap داخل transaction.
5. استبعاد المسودات والمعاينات من sitemap وإجبار noindex.
6. الاحتفاظ بكل Metadata وSchemas الحالية.
7. إضافة تنبيهات SEO في لوحة التحرير دون وعود ترتيب.
8. التحقق آليًا من H1 وAlt والروابط الداخلية وCanonical.

## 12. خطة الاختبارات

### Backend

- Authentication ومحاولات الدخول الفاشلة.
- الأدوار والصلاحيات وPolicies.
- CRUD/restore/publish/schedule للمقالات والخدمات.
- قفل slug وRedirect 301 ومنع loops/duplicates.
- Media MIME/size/deduplication/derivatives.
- API Resources والمسودات وsitemap والإعدادات.
- Importer idempotency وForeign keys وAudit logs.

### Frontend

- عقد 156 رابطًا وكل Metadata وSchema والروابط الداخلية.
- 404 والصور والقوائم والتوافق المحمول.
- TypeScript/Astro/ESLint/build.
- broken links.

### E2E

- دخول الإدارة بالحساب المحلي.
- دورة مقال كاملة: draft → preview → publish → sitemap → slug redirect.
- رفع صورة وتعديل خدمة.
- منع Editor من عمليات غير مصرح بها.
- تسجيل الخروج.

## 13. المخاطر وإجراءات المنع

| الخطر | الإجراء |
|---|---|
| كسر رابط قديم | Manifest + unique route registry + اختبارات 156 رابطًا |
| تغيير التصميم | إعادة استخدام HTML/CSS/assets والمقارنة المرئية |
| استيراد مكرر | مفاتيح legacy_path وslug وhash وعمليات idempotent |
| نشر Build فاشل | snapshot versioned + tests + atomic switch موثق |
| رفع ملف خبيث | MIME حقيقي، حد حجم وأبعاد، random filename، منع SVG النشط |
| تسرب أسرار | `.gitignore` وفحص Git قبل push |
| صلاحية زائدة | Policies وpermissions في Backend |
| حذف محتوى مرتبط | قيود FK وPolicy/Action تمنع الحذف قبل المعالجة |
| فهرسة Local/Staging | robots + X-Robots-Tag + noindex |
| N+1 أو بطء لوحة الإدارة | eager loading وpagination وcache |

## 14. استراتيجية الرجوع

1. لا يمس العمل `lams-event.com` أو `public_html`.
2. يبقى build القديم قابلًا للتقديم كما هو.
3. كل مرحلة في commit مستقل على `feature/admin-cms`.
4. قبل أي إطلاق مستقبلي تؤخذ snapshots لقاعدة البيانات والوسائط.
5. التبديل يتم على مستوى release symlink/reverse proxy لا بحذف الإصدار السابق.
6. عند فشل health checks يعاد المؤشر فورًا للإصدار السابق.
7. لا تحذف قاعدة الإصدار الفاشل؛ تجمد للتحليل.
8. يعاد اختبار Route Manifest وCanonical وsitemap بعد الرجوع.

## 15. معايير القبول لهذه المرحلة

- Filament عربي RTL يعمل على `/admin`.
- الحساب المحلي المطلوب يعمل فقط في `APP_ENV=local`.
- المحتوى الحقيقي مستورد وقابل للإدارة.
- API وAstro يعملان بطبقة بيانات مركزية.
- migrations تعمل من قاعدة فارغة.
- جميع الاختبارات الأساسية ناجحة.
- `npm run setup/dev/test/build/verify` تعمل من الجذر.
- الموقع على `localhost:4321` واللوحة على `127.0.0.1:8000/admin`.
- الفرع يرفع إلى GitHub دون merge أو نشر حي.
