# نشر Staging

لا تنشر هذه المرحلة على الدومين الرئيسي.

## متطلبات Staging

- نطاق مستقل وTLS وBasic Auth أو وصول شبكي مقيد.
- `APP_ENV=staging`, `APP_DEBUG=false`.
- MySQL وقاعدة ومستخدم مستقلان.
- `SESSION_SECURE_COOKIE=true`.
- Queue worker دائم وstorage دائم.
- `X-Robots-Tag: noindex, nofollow, noarchive`.
- مفاتيح وأسرار من Secret Manager، وليست ملفات Git.

## المسار المقترح

1. ابنِ artifact من commit مراجَع.
2. خذ Snapshot لقاعدة ووسائط Staging.
3. انسخ `backend/.env.production.example` إلى `backend/.env` واضبط أسرار
   Staging خارج Git، مع `APP_ENV=staging` و`APP_DEBUG=false`.
4. شغّل `composer install --no-dev --classmap-authoritative`.
5. شغّل `php artisan migrate --force` ثم `php artisan storage:link`.
6. شغّل `legacy:import` فقط عند bootstrap الأول، ثم `legacy:verify`.
7. امنح مستخدم PHP صلاحية الكتابة على `backend/storage` و
   `backend/bootstrap/cache` فقط، ثم شغّل `php artisan optimize`.
8. شغّل Queue worker عبر Supervisor/systemd بالأمر
   `php artisan queue:work --timeout=180 --tries=3 --max-time=3600`، واجعل
   `DB_QUEUE_RETRY_AFTER=240` أكبر من مهلة المهمة.
9. انسخ `frontend/.env.production.example` إلى `frontend/.env.production`
   واضبط `CMS_API_URL` على Staging، ثم شغّل `npm ci` و`npm run build`.
   اترك `CMS_ALLOW_STATIC_FALLBACK=false` كي يفشل الإصدار بدل نشر بيانات
   قديمة إذا تعذر الوصول إلى API.
10. شغّل العقد والروابط وE2E والمقارنة البصرية.
11. انشر الـartifact على Staging واطلب اعتمادًا بشريًا.

تُقدّم `frontend/dist` ملفات ثابتة، وتُمرّر `/admin`, `/api`, `/storage` إلى
Laravel/PHP-FPM. استخدم MySQL في Staging/Production؛ SQLite محلي واختباري فقط.
بعد تعديل محتوى منشور، ينشئ Queue نسخة محتوى جديدة. يجب أن يلتقط مسار
النشر هذه النسخة ويعيد `npm run build` ثم يبدّل `frontend/dist` ذريًا؛ لا
تشغّل Node أو البناء داخل طلب لوحة التحكم.

## بوابة Production المستقبلية

تتطلب موافقة منفصلة، Snapshot نهائيًا، نافذة تغيير، نتائج قبول Staging،
وتجربة Rollback. لا يحتوي المستودع على أي أمر يغيّر DNS أو الدومين الرئيسي.
