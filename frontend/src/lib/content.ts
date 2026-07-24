import content from "../data/content-export.json";
import type { ContentEntity, RouteRecord } from "../types/content";

const collections = [
  content.pages,
  content.service_categories,
  content.services,
  content.areas,
  content.articles,
] as unknown as ContentEntity[][];

const entitiesByPath = new Map<string, ContentEntity>(
  collections.flat().map((entity) => [entity.legacy_path, entity]),
);

export const routes = content.routes as unknown as RouteRecord[];
export const sitemap = content.sitemap;
export const contact = content.contact;

export function getContentByPath(path: string): ContentEntity {
  const entity = entitiesByPath.get(path);
  if (!entity) {
    throw new Error(`No structured content entity found for ${path}`);
  }

  return entity;
}

export function behaviorScriptFor(path: string): string {
  if (path === "/") return "/_astro/hoisted.Cn6M5UBF.js";
  if (path === "/about") return "/_astro/hoisted.CV4RsOnG.js";
  return "/_astro/hoisted.D7SKyjh4.js";
}
