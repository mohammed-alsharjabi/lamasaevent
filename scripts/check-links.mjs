import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";
import { dirname, extname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const manifest = JSON.parse(
  readFileSync(join(projectRoot, "route-manifest.json"), "utf8"),
);
const contentExport = JSON.parse(
  readFileSync(
    join(projectRoot, "frontend", "src", "data", "content-export.json"),
    "utf8",
  ),
);
const distRoot = join(projectRoot, "frontend", "dist");
const normalize = (value) => {
  const url = new URL(value, "https://lams-event.com");
  const path = decodeURI(url.pathname).replace(/\/+$/, "") || "/";
  return path;
};
const routes = new Set([
  ...manifest.routes.map((route) => normalize(route.path)),
  ...contentExport.routes
    .filter((route) => route.is_published)
    .map((route) => normalize(route.path)),
]);
const failures = [];

for (const routePath of routes) {
  const outputFile =
    routePath === "/"
      ? join(distRoot, "index.html")
      : join(distRoot, routePath.replace(/^\/|\/$/g, ""), "index.html");
  assert.ok(existsSync(outputFile), `Missing output for ${routePath}`);
  const html = readFileSync(outputFile, "utf8");

  for (const match of html.matchAll(/<a\b[^>]*\bhref=(["'])(.*?)\1/gi)) {
    const href = match[2].replaceAll("&amp;", "&").trim();
    if (
      !href.startsWith("/") ||
      href.startsWith("//") ||
      href.startsWith("/_astro/") ||
      href.startsWith("/assets/") ||
      href.startsWith("/icons/")
    ) {
      continue;
    }

    const target = normalize(href);
    if (extname(target) || routes.has(target)) continue;
    failures.push(`${routePath} -> ${href}`);
  }
}

assert.deepEqual(failures, [], `Broken internal links:\n${failures.join("\n")}`);
console.log(`Verified internal links across ${routes.size} published routes.`);
