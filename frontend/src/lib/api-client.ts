import { z } from "zod";
import fallbackContent from "../data/content-export.json";

const seoSchema = z.looseObject({
    title: z.string().min(1),
    description: z.string().min(1),
    canonical: z.url(),
    robots: z.string().nullable(),
    open_graph: z.record(z.string(), z.string().nullable()),
    twitter: z.record(z.string(), z.string().nullable()),
    hreflang: z
      .array(z.object({ lang: z.string(), href: z.url() }))
      .nullable(),
    json_ld: z.array(z.unknown()),
    json_ld_sha256: z.string(),
  });

const contentBlockSchema = z.looseObject({
    type: z.enum(["article", "section", "navigation", "component"]),
    tag: z.string(),
    id: z.string().nullable(),
    classes: z.array(z.string()),
    headings: z.array(
      z.object({
        level: z.string(),
        id: z.string().nullable(),
        text: z.string(),
      }),
    ),
    paragraphs: z.array(z.string()),
    links: z.array(z.object({ href: z.string(), text: z.string() })),
    html: z.string(),
  });

const entitySchema = z.looseObject({
    id: z.number().int(),
    title: z.string().min(1),
    legacy_path: z.string().startsWith("/"),
    content_blocks: z.array(contentBlockSchema),
    status: z.literal("published"),
    published_at: z.string(),
    seo_meta: seoSchema,
  });

const routeSchema = z.looseObject({
    id: z.number().int(),
    path: z.string().startsWith("/"),
    exact_url: z.url(),
    routable_type: z.string().nullable(),
    routable_id: z.number().int().nullable(),
    is_legacy: z.boolean(),
    is_published: z.boolean(),
  });

const sitemapSchema = z.looseObject({
    loc: z.url(),
    path: z.string().startsWith("/"),
    lastmod: z.string().nullable(),
    changefreq: z.string().nullable(),
    priority: z.union([z.string(), z.number()]),
    position: z.number().int(),
    is_included: z.boolean(),
  });

const menuItemSchema = z.looseObject({
    id: z.number().int(),
    label: z.string().min(1),
    url: z.string().min(1),
    is_external: z.boolean(),
    open_in_new_tab: z.boolean(),
    is_active: z.boolean(),
    sort_order: z.number().int(),
  });

const menuSchema = z.looseObject({
    id: z.number().int(),
    name: z.string().min(1),
    location: z.string().min(1),
    is_active: z.boolean(),
    all_items: z.array(menuItemSchema),
  });

const contactSchema = z.looseObject({
    phone: z.string().min(1),
    phone_display: z.string().min(1),
    whatsapp: z.string().min(1),
    email: z.email(),
    city: z.string().min(1),
    region: z.string().min(1),
    country_code: z.string().length(2),
    social_links: z.record(z.string(), z.string()).nullable(),
  });

const settingSchema = z.record(z.string(), z.string());

export const contentExportSchema = z.looseObject({
    schema_version: z.union([z.literal(1), z.literal(2)]),
    production_origin: z.url(),
    generated_at: z.string(),
    pages: z.array(entitySchema),
    service_categories: z.array(entitySchema),
    services: z.array(entitySchema),
    areas: z.array(entitySchema),
    articles: z.array(entitySchema),
    galleries: z.array(z.record(z.string(), z.unknown())),
    routes: z.array(routeSchema),
    redirects: z.array(z.record(z.string(), z.unknown())),
    sitemap: z.array(sitemapSchema),
    contact: contactSchema.nullable(),
    settings: z.record(z.string(), settingSchema),
    menus: z.array(menuSchema),
  });

export type ContentExport = z.infer<typeof contentExportSchema>;
export type ContentSource = "api" | "static-fallback";

let cached:
  | { payload: ContentExport; source: ContentSource }
  | undefined;

export async function loadContentExport(): Promise<{
  payload: ContentExport;
  source: ContentSource;
}> {
  if (cached) return cached;

  const apiBase = import.meta.env.CMS_API_URL?.replace(/\/+$/, "");
  const allowFallback =
    import.meta.env.CMS_ALLOW_STATIC_FALLBACK !== "false";

  if (apiBase) {
    try {
      const response = await fetch(`${apiBase}/api/v1/content-export`, {
        headers: { Accept: "application/json" },
        signal: AbortSignal.timeout(5_000),
      });

      if (!response.ok) {
        throw new Error(`CMS API returned ${response.status}`);
      }

      cached = {
        payload: contentExportSchema.parse(await response.json()),
        source: "api",
      };

      return cached;
    } catch (error) {
      if (!allowFallback) throw error;
      console.warn(
        `[content] CMS API unavailable; using validated static snapshot: ${String(error)}`,
      );
    }
  }

  cached = {
    payload: contentExportSchema.parse(fallbackContent),
    source: "static-fallback",
  };

  return cached;
}
