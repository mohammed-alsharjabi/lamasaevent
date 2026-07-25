import assert from "node:assert/strict";
import { createHash } from "node:crypto";
import { existsSync, readFileSync } from "node:fs";
import { realpath } from "node:fs/promises";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import test from "node:test";

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const manifest = JSON.parse(
  readFileSync(join(projectRoot, "route-manifest.json"), "utf8"),
);
const distRoot = join(projectRoot, "frontend", "dist");
const legacyOption = process.env.LEGACY_DIST;

const sha256 = (value) =>
  createHash("sha256").update(value).digest("hex");

const decodeEntities = (value = "") =>
  value
    .replaceAll("&amp;", "&")
    .replaceAll("&quot;", '"')
    .replaceAll("&#39;", "'")
    .replaceAll("&#x27;", "'")
    .replaceAll("&lt;", "<")
    .replaceAll("&gt;", ">");

const stripTags = (value = "") =>
  decodeEntities(value.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim());

const stripCmsAdditions = (html = "") =>
  html
    .replace(
      /<!--cms-native:[^>]*:start-->[\s\S]*?<!--cms-native:[^>]*:end-->/gi,
      "",
    )
    .replace(
      /<section\b[^>]*\bdata-cms-additions\b[^>]*>[\s\S]*?<\/section>/gi,
      "",
    );

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

const getInternalLinks = (html) => {
  const links = new Set();
  for (const match of html.matchAll(/<a\b[^>]*\bhref=(["'])(.*?)\1/gi)) {
    const href = decodeEntities(match[2]).trim();
    if (href.startsWith("/")) {
      links.add(href);
      continue;
    }
    try {
      const url = new URL(href);
      if (url.hostname === "lams-event.com") {
        links.add(`${url.pathname}${url.search}${url.hash}`);
      }
    } catch {
      // Ignore non-URL schemes and malformed external values.
    }
  }
  return [...links].sort();
};

const outputFileFor = (path) =>
  path === "/"
    ? join(distRoot, "index.html")
    : join(distRoot, path.replace(/^\/|\/$/g, ""), "index.html");

const parseSitemapRows = (xml) =>
  [...xml.matchAll(/<url>([\s\S]*?)<\/url>/gi)].map((match) => {
    const value = (name) => {
      const field = match[1].match(
        new RegExp(`<${name}>([\\s\\S]*?)</${name}>`, "i"),
      );
      return field ? decodeEntities(field[1].trim()) : null;
    };
    return {
      loc: value("loc"),
      lastmod: value("lastmod"),
      changefreq: value("changefreq"),
      priority: value("priority"),
    };
  });

test("Astro emits every immutable route with the complete SEO contract", () => {
  assert.equal(
    manifest.routes.length,
    manifest.legacy.route_count,
    "The manifest route count is internally inconsistent.",
  );

  const failures = [];
  for (const route of manifest.routes) {
    const outputFile = outputFileFor(route.path);
    if (!existsSync(outputFile)) {
      failures.push(`${route.path}: missing ${outputFile}`);
      continue;
    }

    const html = readFileSync(outputFile, "utf8");
    const head = html.match(/<head\b[^>]*>([\s\S]*?)<\/head>/i)?.[1] || "";
    const jsonLd = [...head.matchAll(
      /<script\b[^>]*type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi,
    )].map((match) => JSON.parse(match[1]));

    const actual = {
      title: stripTags(head.match(/<title\b[^>]*>([\s\S]*?)<\/title>/i)?.[1]),
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
      json_ld_sha256: sha256(JSON.stringify(jsonLd)),
      internal_links: getInternalLinks(stripCmsAdditions(html)),
    };

    const expected = {
      title: route.seo.title,
      meta_description: route.seo.meta_description,
      canonical: route.seo.canonical,
      robots: route.seo.robots,
      open_graph: route.seo.open_graph,
      twitter: route.seo.twitter,
      json_ld_sha256: route.seo.json_ld_sha256,
      internal_links: route.internal_links,
    };

    try {
      assert.deepEqual(actual, expected);
    } catch (error) {
      failures.push(`${route.path}: ${error.message}`);
    }
  }

  assert.deepEqual(failures, [], failures.join("\n\n"));
});

test("generated sitemap preserves every legacy row and its order", () => {
  const xml = readFileSync(join(distRoot, "sitemap.xml"), "utf8");
  const actual = parseSitemapRows(xml);
  const expected = manifest.routes.map((route) => ({
    loc: route.url,
    lastmod: route.sitemap.lastmod,
    changefreq: route.sitemap.changefreq,
    priority: route.sitemap.priority,
  }));

  assert.deepEqual(actual.slice(0, expected.length), expected);

  const allLocations = actual.map((entry) => entry.loc);
  assert.equal(
    new Set(allLocations).size,
    allLocations.length,
    "Sitemap contains duplicate locations.",
  );
});

test(
  "optional immutable source verification matches the audited hashes",
  { skip: !legacyOption && "Set LEGACY_DIST to verify the immutable source." },
  async () => {
    const legacyRoot = await realpath(legacyOption);
    const sitemap = readFileSync(join(legacyRoot, "sitemap.xml"));
    assert.equal(sha256(sitemap), manifest.legacy.sitemap_sha256);

    for (const route of manifest.routes) {
      const html = readFileSync(join(legacyRoot, route.legacy_file));
      assert.equal(
        sha256(html),
        route.legacy_html_sha256,
        `${route.legacy_file} changed after the audit`,
      );
    }
  },
);
