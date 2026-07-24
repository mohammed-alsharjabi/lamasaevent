import { sitemap } from "../lib/content";

export function GET() {
  const rows = sitemap.map((entry) => {
    const priority = Number(entry.priority);
    const formattedPriority =
      Math.round(priority * 10) === priority * 10
        ? priority.toFixed(1)
        : priority.toFixed(2);

    return [
      "  <url>",
      `    <loc>${escapeXml(entry.loc)}</loc>`,
      entry.lastmod
        ? `    <lastmod>${String(entry.lastmod).slice(0, 10)}</lastmod>`
        : null,
      entry.changefreq
        ? `    <changefreq>${escapeXml(entry.changefreq)}</changefreq>`
        : null,
      `    <priority>${formattedPriority}</priority>`,
      "  </url>",
    ]
      .filter(Boolean)
      .join("\n");
  });

  const xml = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ...rows,
    "</urlset>",
    "",
  ].join("\n");

  return new Response(xml, {
    headers: { "Content-Type": "application/xml; charset=utf-8" },
  });
}

function escapeXml(value: string): string {
  return value
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&apos;");
}
