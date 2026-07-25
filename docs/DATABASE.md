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
الذاتية ومنع مستوى ثالث، ويعيد `nullOnDelete` الخدمات الفرعية إلى مستوى
رئيسي إذا حذفت خدمتها الرئيسية.

## الحالات والمراجعات

كل محتوى قابل للنشر يحمل `status=draft|published` و`published_at`. تحفظ
التحديثات السابقة في `content_revisions.snapshot` مع رقم revision والمستخدم.
الحذف المنطقي مفعل للمحتوى والوسائط، بينما الحذف النهائي محصور بصلاحية
`force-delete`.

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
