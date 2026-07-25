# التطوير المحلي

## المتطلبات

PHP 8.2+ مع GD وSQLite، Composer 2، Node.js 22.12+، وnpm. يمكن تحديد Node
صراحة عبر `LAMS_NODE_BIN`.

## الإعداد والتشغيل

```bash
npm run setup
npm run dev
```

يشغل `dev`:

- Astro: `http://127.0.0.1:4321`
- Laravel/Filament/API: `http://127.0.0.1:8000`
- Queue worker بخلفية مستقلة

تسجل PIDs داخل `.run/` والسجلات داخل `backend/storage/logs/local-*.log`.
للإيقاف:

```bash
npm run stop
```

## الأوامر الجذرية

| الأمر | الوظيفة |
|---|---|
| `npm run setup` | dependencies، migrations، seed، import، Playwright، build |
| `npm run dev` | تشغيل الموقع واللوحة والـQueue وفتح المتصفح |
| `npm run build` | export متحقق ثم Astro check/build |
| `npm test` | Pint، PHPStan، PHPUnit، Astro، العقد، E2E، الروابط |
| `npm run verify` | كل ما سبق مع `legacy:verify` والمقارنة البصرية |

يجب ضبط `LEGACY_DIST` داخل `backend/.env`. لا تكتب الأوامر إلى هذا المسار؛
جميع عمليات المصدر قراءة وفحص checksum فقط.
