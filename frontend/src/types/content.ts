export interface SeoMeta {
  title: string;
  description: string;
  canonical: string;
  robots: string | null;
  keywords: string[];
  open_graph: Record<string, string | null>;
  twitter: Record<string, string | null>;
  hreflang: Array<{ lang: string; href: string }> | null;
  json_ld: unknown[];
  json_ld_sha256: string | null;
}

export interface ContentBlock {
  type: string;
  tag?: string;
  id?: string | null;
  classes?: string[];
  headings?: Array<{ level: string; id: string | null; text: string }>;
  paragraphs?: string[];
  links?: Array<{ href: string; text: string }>;
  html?: string;
  heading?: string | null;
  lead?: string | null;
  body?: string | null;
  text?: string | null;
  label?: string | null;
  url?: string | null;
  items?: Array<Record<string, unknown>>;
  media_ids?: number[];
  media?: GalleryMedia[];
  service_ids?: number[];
  services?: Array<{
    id: number;
    title: string;
    excerpt: string | null;
    public_path: string;
  }>;
  [key: string]: unknown;
}

export interface ContentEntity {
  id: number;
  title: string;
  legacy_path: string | null;
  content_blocks: ContentBlock[];
  status: "published";
  published_at: string;
  seo_meta: SeoMeta;
  public_path?: string;
  hero_media_summary?: {
    original_name: string;
    public_url: string;
    alt: string | null;
  } | null;
  summary?: string | null;
  excerpt?: string | null;
  [key: string]: unknown;
}

export interface GalleryMedia {
  id: number;
  original_name: string;
  public_url: string;
  alt: string | null;
  caption: string | null;
}

export interface GalleryItem {
  id: number;
  title: string | null;
  alt: string | null;
  caption: string | null;
  is_active: boolean;
  media: GalleryMedia;
}

export interface GalleryEntity {
  id: number;
  title: string;
  description: string | null;
  status: "published";
  published_at: string;
  is_legacy: boolean;
  media: GalleryMedia[];
  items: GalleryItem[];
}

export interface RouteRecord {
  id: number;
  path: string;
  exact_url: string;
  routable_type: string | null;
  routable_id: number | null;
  is_legacy: boolean;
  is_published: boolean;
}

export interface MenuItem {
  id: number;
  label: string;
  url: string;
  is_external: boolean;
  open_in_new_tab: boolean;
  is_active: boolean;
  sort_order: number;
}

export interface Menu {
  id: number;
  name: string;
  location: string;
  is_active: boolean;
  all_items: MenuItem[];
}

export interface ContactSettings {
  phone: string;
  phone_display: string;
  whatsapp: string;
  email: string;
  city: string;
  region: string;
  country_code: string;
  social_links: Record<string, string> | null;
}
