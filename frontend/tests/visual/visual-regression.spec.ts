import { expect, test, type Page } from "@playwright/test";
import { mkdir, writeFile } from "node:fs/promises";
import path from "node:path";
import pixelmatch from "pixelmatch";
import { PNG } from "pngjs";

const newBase = process.env.NEW_BASE_URL || "http://127.0.0.1:4321";
const legacyBase = process.env.LEGACY_BASE_URL || "http://127.0.0.1:4173";
const outputRoot = path.resolve(import.meta.dirname, "actual");
const diffRoot = path.resolve(import.meta.dirname, "diff");
const pages = [
  ["/", "home"],
  ["/services", "services"],
  ["/services/kosh-riyadh", "service"],
  ["/services/category/kosh-wedding-presentation/", "service-category"],
  ["/areas/north-riyadh", "area"],
  ["/blog", "blog"],
  ["/blog/kayfa-takhtar-munassiq-hafalat-riyadh", "article"],
  ["/gallery", "gallery"],
  ["/contact", "contact"],
] as const;

async function prepare(page: Page, url: string): Promise<void> {
  await page.goto(url, { waitUntil: "networkidle" });
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.evaluate(async () => {
    await document.fonts.ready;
    document.documentElement.classList.add("visual-regression");
    const step = Math.max(400, Math.floor(window.innerHeight * 0.75));
    for (let y = 0; y <= document.documentElement.scrollHeight; y += step) {
      window.scrollTo(0, y);
      await new Promise((resolve) => setTimeout(resolve, 25));
    }
    await Promise.all([...document.images].map((image) => image.decode().catch(() => undefined)));
    window.scrollTo(0, 0);
  });
}

test.beforeAll(async () => {
  await Promise.all([
    mkdir(outputRoot, { recursive: true }),
    mkdir(diffRoot, { recursive: true }),
  ]);
});

for (const [route, slug] of pages) {
  test(`${route} keeps the recovered main content visually equivalent`, async ({
    browser,
  }) => {
    const legacy = await browser.newPage();
    const current = await browser.newPage();

    await Promise.all([
      prepare(legacy, new URL(route, legacyBase).href),
      prepare(current, new URL(route, newBase).href),
    ]);

    const [legacyPng, currentPng] = await Promise.all([
      legacy.locator("main").screenshot({ animations: "disabled" }),
      current.locator("main").screenshot({ animations: "disabled" }),
    ]);
    await Promise.all([
      writeFile(path.join(outputRoot, `${slug}-legacy.png`), legacyPng),
      writeFile(path.join(outputRoot, `${slug}-current.png`), currentPng),
    ]);

    const before = PNG.sync.read(legacyPng);
    const after = PNG.sync.read(currentPng);
    expect(
      { width: after.width, height: after.height },
      "main dimensions changed",
    ).toEqual({ width: before.width, height: before.height });

    const diff = new PNG({ width: before.width, height: before.height });
    const changed = pixelmatch(
      before.data,
      after.data,
      diff.data,
      before.width,
      before.height,
      { threshold: 0.12, includeAA: false },
    );
    const ratio = changed / (before.width * before.height);
    await writeFile(path.join(diffRoot, `${slug}.png`), PNG.sync.write(diff));

    expect(
      ratio,
      `visual mismatch ratio for ${route}: ${(ratio * 100).toFixed(3)}%`,
    ).toBeLessThanOrEqual(0.005);

    await Promise.all([legacy.close(), current.close()]);
  });
}
