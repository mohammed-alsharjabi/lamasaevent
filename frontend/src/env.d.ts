/// <reference types="astro/client" />

interface ImportMetaEnv {
  readonly CMS_API_URL?: string;
  readonly CMS_ALLOW_STATIC_FALLBACK?: "true" | "false";
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
