# استعادة موقع لمسه التميز

مشروع صيانة جديد يفصل إدارة المحتوى عن العرض، مع إبقاء نسخة الإنتاج القديمة مرجعًا للقبول دون تعديلها:

- `backend/`: Laravel 12 للوحة التحكم، API، الاستيراد، النشر، التحويلات 301، الوسائط وsitemap.
- `frontend/`: Astro 7 يبني 156 مسارًا ثابتًا من بيانات Laravel المنظمة.
- `route-manifest.json`: عقد ثابت لكل روابط `sitemap.xml` وبيانات SEO والروابط الداخلية وبصمات HTML.
- `tests/`: اختبارات عقد المسارات وSEO.
- `frontend/tests/visual/`: مقارنة Playwright مرئية بين المرجع والنسخة المستعادة.
- `staging/`: Gateway معزول يجمع Astro وLaravel ويضيف `noindex`.

التقرير المرجعي الكامل موجود في `RECOVERY_AUDIT.md`.

## المتطلبات

- PHP 8.2 أو أحدث، مع SQLite وGD.
- Composer 2.
- Node.js 22.12 أو أحدث وnpm.
- مسار النسخة القديمة عبر متغير `LEGACY_DIST`.

لا تكتب أي أداة في `LEGACY_DIST`. الاستيراد يقرأها فقط ويرفض المتابعة إذا اختلفت بصمة sitemap أو أي صفحة عن Route Manifest.

## إعداد Staging محلي معزول

```bash
export LEGACY_DIST="/absolute/path/to/legacy-dist"
export ADMIN_EMAIL="admin@example.com"
export ADMIN_PASSWORD="replace-with-a-long-random-password"
./scripts/staging-prepare.sh
./scripts/staging-start.sh
```

تفتح الواجهة على `http://127.0.0.1:8080`، ولوحة التحكم على
`http://127.0.0.1:8080/admin`. تستخدم البيئة قاعدة
`backend/database/staging.sqlite` المنفصلة، وترسل دائمًا ترويسة
`X-Robots-Tag: noindex, nofollow, noarchive`.

عند ربط Staging بشبكة عامة يجب تعيين `STAGING_BASIC_USER` و
`STAGING_BASIC_PASSWORD`، واستخدام HTTPS و`SESSION_SECURE_COOKIE=true`.
الـGateway يرفض أصلًا الاستماع على عنوان غير loopback من دون Basic Auth.

## سير تحديث المحتوى

1. يعدّل المحرر المحتوى في Laravel ويحفظه كمسودة أو منشور مع تاريخ النشر.
2. تغيير slug منشور لا يتم مباشرة؛ خدمة النشر تنشئ Redirect 301 أولًا.
3. ينفّذ `php artisan content:export` لتحديث بيانات Astro المنشورة.
4. ينفّذ `npm run build` داخل `frontend/`.
5. تمر اختبارات العقود والمقارنة المرئية في Staging قبل أي تحويل للدومين.

## أوامر القبول

```bash
cd backend
php artisan test
./vendor/bin/pint --test
php artisan legacy:import --legacy="$LEGACY_DIST" --manifest="../route-manifest.json" --dry-run

cd ../frontend
npm run check
npm run build
LEGACY_DIST="$LEGACY_DIST" npm run test:contract
npm run test:visual
```

اختبار العقود يمر على 156 رابطًا ويقارن العنوان والوصف وCanonical وOpen
Graph وTwitter وJSON-LD والروابط الداخلية، ثم يقارن sitemap كاملة بترتيبها
وحقولها. اختبار Playwright يغطي نموذجًا من كل نوع صفحة بحد اختلاف قدره
`0.5%` للمحتوى الرئيسي.

## النشر والرجوع

لم تُضبط أي أداة لنشر الدومين الرئيسي. تفاصيل بوابة القبول، النسخ الاحتياطي،
التحويل التدريجي، والرجوع الفوري موجودة في `STAGING_RUNBOOK.md`.
