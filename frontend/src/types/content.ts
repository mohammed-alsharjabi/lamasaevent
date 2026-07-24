export interface SeoMeta {
  title: string;
  description: string;
  canonical: string;
  robots: string | null;
  open_graph: Record<string, string | null>;
  twitter: Record<string, string | null>;
  hreflang: Array<{ lang: string; href: string }> | null;
  json_ld: unknown[];
  json_ld_sha256: string;
}

export interface ContentBlock {
  type: "article" | "section" | "navigation" | "component";
  tag: string;
  id: string | null;
  classes: string[];
  headings: Array<{ level: string; id: string | null; text: string }>;
  paragraphs: string[];
  links: Array<{ href: string; text: string }>;
  html: string;
}

export interface ContentEntity {
  id: number;
  title: string;
  legacy_path: string;
  content_blocks: ContentBlock[];
  status: "published";
  published_at: string;
  seo_meta: SeoMeta;
  [key: string]: unknown;
}

export interface RouteRecord {
  id: number;
  path: string;
  exact_url: string;
  routable_type: string;
  routable_id: number;
  is_legacy: boolean;
  is_published: boolean;
}
