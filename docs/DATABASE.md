# قاعدة البيانات

## البيئات

- Local والاختبارات: SQLite فقط.
- Staging وProduction: MySQL 8+ بقاعدة منفصلة ومستخدم بأقل صلاحيات.
- لا تُنسخ قاعدة Local أو بيانات اعتماد المدير المحلي إلى Staging/Production.

مثال Production موجود في `backend/.env.production.example`. يحتاج مستخدم
التطبيق صلاحيات `SELECT, INSERT, UPDATE, DELETE`، وتحتاج عملية migrations
المضبوطة زمنيًا صلاحيات DDL. يفضل فصل مستخدم migrations عن مستخدم runtime.

## الجداول الرئيسية

| المجموعة | الجداول |
|---|---|
| المحتوى | `articles`, `article_categories`, `services`, `service_categories`, `areas`, `pages`, `faqs` |
| الوسائط | `media`, `mediaables`, `galleries`, `gallery_items`, `gallery_media` |
| البنية | `menus`, `menu_items`, `service_area` |
| SEO والروابط | `seo_meta`, `route_registry`, `sitemap_entries`, `redirects` |
| الإعدادات | `settings`, `contact_settings` |
| الأمان | `users`, `roles`, `permissions`, `role_user`, `permission_role`, `audit_logs` |
| دورة النشر | `content_revisions`, `publish_jobs`, `jobs`, `failed_jobs`, `cache` |

`site_settings` هو جدول الاستعادة القديم؛ تنسخ migration قيمه إلى `settings`
وتستخدم طبقة التطبيق الجدول الجديد.

حقل `services.parent_id` علاقة ذاتية اختيارية: القيمة الفارغة تعني خدمة
رئيسية، والقيمة المحددة تعني خدمة فرعية مباشرة. يتحقق Model من منع التبعية
الذاتية ومنع مستوى ثالث، ويمنع حذف خدمة رئيسية قبل نقل خدماتها الفرعية.
كما تمنع Policies والـModels حذف تصنيف مرتبط أو صورة مستخدمة.

## الحالات والمراجعات

كل محتوى قابل للنشر يحمل `status=draft|published` و`published_at`. تحفظ
التحديثات السابقة في `content_revisions.snapshot` مع رقم revision والمستخدم.
الحذف المنطقي مفعل للمحتوى والوسائط. الحذف النهائي معطل تشغيليًا لهما حتى
للمدير العام، لأن بقاء
المسارات وSEO والمراجعات أهم من تفريغ السجلات. الحذف العادي قابل للاستعادة،
ويزيل المحتوى الجديد من `route_registry` و`sitemap` وsnapshot تلقائيًا.
محتوى الاستعادة القديم لا يمكن إلغاء نشره أو حذفه.

## RBAC

- `super-admin`: جميع الصلاحيات.
- `content-manager`: إدارة المحتوى والوسائط والقوائم وSEO والنشر دون إدارة
  المستخدمين.
- `editor`: إنشاء وتعديل المسودات وSEO والوسائط، دون نشر أو حذف.

الـSeeder idempotent وينشئ الأدوار والصلاحيات في كل بيئة. حساب
`admin@lams-event.local` ينشأ فقط عندما تكون `APP_ENV=local`.

## النسخ الاحتياطي

قبل كل migration في Staging/Production:

1. Snapshot متسق لقاعدة MySQL.
2. Snapshot لـ`storage/app/public` و`storage/app/private/publish`.
3. تسجيل commit ونسخة migration.
4. تجربة الاستعادة على قاعدة منفصلة.

### MySQL

نفّذ النسخة بمستخدم قراءة مخصص، واجعل كلمة المرور عبر prompt أو Secret
Manager لا ضمن سجل الأوامر:

```bash
mysqldump --single-transaction --quick --routines --triggers \
  --default-character-set=utf8mb4 -h DB_HOST -u BACKUP_USER \
  lamasaevent > lamasaevent-YYYYMMDD-HHMMSS.sql
```

اختبر الاستعادة دائمًا في قاعدة فارغة منفصلة:

```bash
mysql -h DB_HOST -u RESTORE_USER -e \
  "CREATE DATABASE lamasaevent_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -h DB_HOST -u RESTORE_USER lamasaevent_restore \
  < lamasaevent-YYYYMMDD-HHMMSS.sql
```

اضبط نسخة مؤقتة من `.env` على `lamasaevent_restore`، ثم شغّل:

```bash
php artisan migrate:status
php artisan legacy:verify
php artisan test
```

لا تستعد مباشرة فوق قاعدة Production. عند حادث فعلي: أوقف الكتابة وQueue،
استعد إلى قاعدة جديدة، اختبرها، ثم بدّل اتصال التطبيق إليها.

### SQLite المحلية

استخدم أمر SQLite المتسق بدل نسخ الملف أثناء الكتابة:

```bash
sqlite3 backend/database/database.sqlite \
  ".backup 'database-YYYYMMDD-HHMMSS.sqlite'"
```

### الوسائط وملفات النشر

خذ الأرشيف من جذر `backend` واحفظه خارج الإصدار الجاري:

```bash
tar -czf media-YYYYMMDD-HHMMSS.tar.gz \
  storage/app/public storage/app/private/publish
```

بعد الاستعادة تحقق من وجود الملفات والأذونات، ثم شغّل `php artisan
content:export` وProduction Build. يجب أن تحمل نسخة القاعدة ونسخة الوسائط
نفس الطابع الزمني والـcommit كي لا تشير قاعدة مستعادة إلى ملفات مفقودة.
