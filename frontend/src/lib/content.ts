import { loadContentExport } from "./api-client";
import type {
  ContactSettings,
  ContentEntity,
  Menu,
  RouteRecord,
} from "../types/content";

const { payload: content, source } = await loadContentExport();

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
export const contentSource = source;
export const contact = content.contact as ContactSettings | null;
export const menus = content.menus as Menu[];
export const settings = content.settings as Record<
  string,
  Record<string, string>
>;

export function requireContact(): ContactSettings {
  if (!contact) {
    throw new Error("Published contact settings are missing from the CMS export.");
  }

  return contact;
}

export function requireMenu(location: string): Menu {
  const menu = menus.find((candidate) => candidate.location === location);
  if (!menu) {
    throw new Error(`Published ${location} menu is missing from the CMS export.`);
  }

  return menu;
}

export function requireSetting(key: string): Record<string, string> {
  const value = settings[key];
  if (!value) {
    throw new Error(`Published ${key} settings are missing from the CMS export.`);
  }

  return value;
}

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
