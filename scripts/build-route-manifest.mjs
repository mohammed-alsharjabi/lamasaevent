#!/usr/bin/env node

import { createHash } from "node:crypto";
import {
  existsSync,
  mkdirSync,
  readFileSync,
  realpathSync,
  writeFileSync,
} from "node:fs";
import { dirname, relative, resolve, sep } from "node:path";

const args = Object.fromEntries(
  process.argv.slice(2).map((argument) => {
    const [key, ...value] = argument.replace(/^--/, "").split("=");
    return [key, value.join("=")];
  }),
);

const legacyRoot = realpathSync(
  resolve(args.legacy || process.env.LEGACY_DIST || "../legacy-dist"),
);
const outputPath = resolve(args.output || "route-manifest.json");
const sitemapPath = resolve(legacyRoot, "sitemap.xml");

if (!existsSync(sitemapPath)) {
  throw new Error(`sitemap.xml was not found below ${legacyRoot}`);
}

const outputRelativeToLegacy = relative(legacyRoot, outputPath);
if (
  outputRelativeToLegacy === "" ||
  (!outputRelativeToLegacy.startsWith(`..${sep}`) &&
    outputRelativeToLegacy !== "..")
) {
  throw new Error("Refusing to write the manifest inside legacy-dist.");
}

const sha256 = (value) =>
  createHash("sha256").update(value).digest("hex");

const decodeEntities = (value = "") =>
  value
    .replaceAll("&amp;", "&")
    .replaceAll("&quot;", '"')
    .replaceAll("&#39;", "'")
    .replaceAll("&lt;", "<")
    .replaceAll("&gt;", ">");

const stripTags = (value = "") =>
  decodeEntities(value.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim());

const getAttribute = (tag, attribute) => {
  const match = tag.match(
    new RegExp(`${attribute}\\s*=\\s*(["'])(.*?)\\1`, "i"),
  );
  return match ? decodeEntities(match[2]) : null;
};

const getMeta = (head, attribute, value) => {
  for (const match of head.matchAll(/<meta\b[^>]*>/gi)) {
    if (getAttribute(match[0], attribute)?.toLowerCase() === value.toLowerCase()) {
      return getAttribute(match[0], "content");
    }
  }
  return null;
};

const getLink = (head, relation) => {
  for (const match of head.matchAll(/<link\b[^>]*>/gi)) {
    const rel = getAttribute(match[0], "rel")?.toLowerCase().split(/\s+/) || [];
    if (rel.includes(relation.toLowerCase())) {
      return getAttribute(match[0], "href");
    }
  }
  return null;
};

const classify = (pathname) => {
  if (pathname === "/") return "home";
  if (pathname === "/services") return "services_index";
  if (pathname.startsWith("/services/category/")) return "service_category";
  if (pathname.startsWith("/services/")) return "service";
  if (pathname === "/blog") return "blog_index";
  if (pathname.startsWith("/blog/")) return "article";
  if (pathname === "/areas") return "areas_index";
  if (pathname.startsWith("/areas/")) return "area";
  if (pathname === "/gallery") return "gallery";
  if (pathname === "/about") return "about";
  if (pathname === "/contact") return "contact";
  return "page";
};

const xml = readFileSync(sitemapPath, "utf8");
const urlBlocks = [...xml.matchAll(/<url>([\s\S]*?)<\/url>/gi)].map(
  (match) => match[1],
);

const xmlValue = (block, name) => {
  const match = block.match(new RegExp(`<${name}>([\\s\\S]*?)</${name}>`, "i"));
  return match ? decodeEntities(match[1].trim()) : null;
};

const routes = urlBlocks.map((block, index) => {
  const url = xmlValue(block, "loc");
  const parsedUrl = new URL(url);
  const pathname = parsedUrl.pathname;
  const relativeHtml =
    pathname === "/"
      ? "index.html"
      : `${decodeURIComponent(pathname).replace(/^\/|\/$/g, "")}/index.html`;
  const htmlPath = resolve(legacyRoot, relativeHtml);

  if (!existsSync(htmlPath)) {
    throw new Error(`Missing legacy HTML for sitemap route: ${url}`);
  }

  const html = readFileSync(htmlPath, "utf8");
  const head = html.match(/<head\b[^>]*>([\s\S]*?)<\/head>/i)?.[1] || "";
  const title = stripTags(head.match(/<title\b[^>]*>([\s\S]*?)<\/title>/i)?.[1]);
  const jsonLd = [...head.matchAll(
    /<script\b[^>]*type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi,
  )].map((match) => {
    try {
      return JSON.parse(match[1]);
    } catch (error) {
      throw new Error(`Invalid JSON-LD in ${relativeHtml}: ${error.message}`);
    }
  });

  const internalLinks = new Set();
  for (const match of html.matchAll(/<a\b[^>]*\bhref=(["'])(.*?)\1/gi)) {
    const href = decodeEntities(match[2]).trim();
    if (href.startsWith("/")) {
      internalLinks.add(href);
    } else {
      try {
        const linkUrl = new URL(href);
        if (linkUrl.hostname === "lams-event.com") {
          internalLinks.add(`${linkUrl.pathname}${linkUrl.search}${linkUrl.hash}`);
        }
      } catch {
        // Non-URL schemes and malformed external values are not internal links.
      }
    }
  }

  const route = {
    order: index + 1,
    url,
    path: pathname,
    type: classify(pathname),
    legacy_file: relativeHtml,
    legacy_html_sha256: sha256(html),
    sitemap: {
      lastmod: xmlValue(block, "lastmod"),
      changefreq: xmlValue(block, "changefreq"),
      priority: xmlValue(block, "priority"),
    },
    seo: {
      title,
      meta_description: getMeta(head, "name", "description"),
      canonical: getLink(head, "canonical"),
      robots: getMeta(head, "name", "robots"),
      open_graph: {
        type: getMeta(head, "property", "og:type"),
        locale: getMeta(head, "property", "og:locale"),
        site_name: getMeta(head, "property", "og:site_name"),
        title: getMeta(head, "property", "og:title"),
        description: getMeta(head, "property", "og:description"),
        url: getMeta(head, "property", "og:url"),
        image: getMeta(head, "property", "og:image"),
        image_alt: getMeta(head, "property", "og:image:alt"),
      },
      twitter: {
        card: getMeta(head, "name", "twitter:card"),
        title: getMeta(head, "name", "twitter:title"),
        description: getMeta(head, "name", "twitter:description"),
        image: getMeta(head, "name", "twitter:image"),
        image_alt: getMeta(head, "name", "twitter:image:alt"),
      },
      json_ld: jsonLd,
      json_ld_sha256: sha256(JSON.stringify(jsonLd)),
    },
    internal_links: [...internalLinks].sort(),
  };

  if (
    !route.seo.title ||
    !route.seo.meta_description ||
    !route.seo.canonical ||
    !route.seo.open_graph.url ||
    route.seo.json_ld.length === 0
  ) {
    throw new Error(`Required SEO metadata is missing from ${relativeHtml}`);
  }

  return route;
});

if (routes.length === 0) {
  throw new Error("The sitemap contains no URL entries.");
}

const duplicateUrls = routes
  .map((route) => route.url)
  .filter((url, index, all) => all.indexOf(url) !== index);
if (duplicateUrls.length > 0) {
  throw new Error(`Duplicate sitemap URLs: ${duplicateUrls.join(", ")}`);
}

const manifest = {
  schema_version: 1,
  generated_from: "legacy-dist/sitemap.xml",
  production_origin: "https://lams-event.com",
  legacy: {
    sitemap_sha256: sha256(xml),
    route_count: routes.length,
    read_only: true,
  },
  counts: routes.reduce((counts, route) => {
    counts[route.type] = (counts[route.type] || 0) + 1;
    return counts;
  }, {}),
  routes,
};

mkdirSync(dirname(outputPath), { recursive: true });
writeFileSync(outputPath, `${JSON.stringify(manifest, null, 2)}\n`, "utf8");
console.log(
  `Wrote ${routes.length} immutable legacy routes to ${outputPath}`,
);
