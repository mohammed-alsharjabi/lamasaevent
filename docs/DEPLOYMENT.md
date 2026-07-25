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
3. شغّل `composer install --no-dev --optimize-autoloader`.
4. شغّل `php artisan migrate --force`.
5. شغّل `legacy:import` فقط عند bootstrap الأول، ثم `legacy:verify`.
6. شغّل `php artisan optimize`.
7. شغّل Queue worker عبر Supervisor/systemd بالأمر
   `php artisan queue:work --timeout=180 --tries=3 --max-time=3600`، واجعل
   `DB_QUEUE_RETRY_AFTER=240` أكبر من مهلة المهمة.
8. ابنِ Astro مع `CMS_API_URL` الخاص بـStaging.
9. شغّل العقد والروابط وE2E والمقارنة البصرية.
10. انشر الـartifact على Staging واطلب اعتمادًا بشريًا.

تُقدّم `frontend/dist` ملفات ثابتة، وتُمرّر `/admin`, `/api`, `/storage` إلى
Laravel/PHP-FPM. استخدم MySQL في Staging/Production؛ SQLite محلي واختباري فقط.

## بوابة Production المستقبلية

تتطلب موافقة منفصلة، Snapshot نهائيًا، نافذة تغيير، نتائج قبول Staging،
وتجربة Rollback. لا يحتوي المستودع على أي أمر يغيّر DNS أو الدومين الرئيسي.
