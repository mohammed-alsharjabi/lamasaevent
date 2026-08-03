import { expect, test } from "@playwright/test";
import { readFileSync } from "node:fs";
import path from "node:path";

interface ExportedService {
  id: number;
  legacy_path: string | null;
  public_path?: string;
  sort_order: number;
  parent?: { id: number } | null;
}

const exportPayload = JSON.parse(
  readFileSync(
    path.resolve(import.meta.dirname, "../../src/data/content-export.json"),
    "utf8",
  ),
) as { services: ExportedService[] };
const cmsServices = exportPayload.services
  .filter((service) => service.legacy_path === null)
  .sort(
    (left, right) =>
      left.sort_order - right.sort_order || left.id - right.id,
  );
const cmsServicePaths = cmsServices.map((service) => service.public_path);

test("recovered public page preserves Arabic SEO shell", async ({ page }) => {
  await page.goto("/", { waitUntil: "domcontentloaded" });

  await expect(page.locator("html")).toHaveAttribute("dir", "rtl");
  await expect(page.locator("link[rel='canonical']")).toHaveAttribute(
    "href",
    "https://lams-event.com/",
  );
  await expect(page.locator("main")).toBeVisible();
});

test("published CMS services use the native grids everywhere", async ({
  page,
}) => {
  await page.goto("/", { waitUntil: "domcontentloaded" });
  const homePaths = await page
    .locator(".ph-services__grid [data-cms-kind='service']")
    .evaluateAll((nodes) =>
      nodes.map((node) => node.getAttribute("href")),
    );
  expect(homePaths).toEqual(cmsServicePaths);
  await expect(page.locator(".cms-additions")).toHaveCount(0);

  await page.goto("/services", { waitUntil: "domcontentloaded" });
  const servicesPaths = await page
    .locator(".svx-grid [data-cms-kind='service'] .svx-card")
    .evaluateAll((nodes) =>
      nodes.map((node) => node.getAttribute("href")),
    );
  expect(servicesPaths).toEqual(cmsServicePaths);

  const child = cmsServices.find((service) => service.parent);

  if (!child) {
    return;
  }

  const parent = exportPayload.services.find(
    (service) => service.id === child.parent?.id,
  );

  expect(child.public_path).toBeTruthy();
  expect(parent?.public_path || parent?.legacy_path).toBeTruthy();

  await page.goto((parent?.public_path || parent?.legacy_path) as string, {
    waitUntil: "domcontentloaded",
  });
  await expect(
    page.locator(
      `.svc-detail__sibling-grid [data-cms-id='${child?.id}']`,
    ),
  ).toHaveAttribute("href", child.public_path as string);
});

test("article and service details stay dark and editorial cards stay aligned", async ({
  page,
}) => {
  await page.goto("/blog/riyadh-venue-timing", {
    waitUntil: "domcontentloaded",
  });
  await expect(page.locator("main")).toHaveClass(/content-detail--dark/);
  await expect(page.locator("main")).toHaveCSS(
    "background-color",
    "rgb(5, 5, 5)",
  );
  await expect(page.locator(".bla-prose")).toHaveCSS(
    "color",
    "rgba(255, 255, 255, 0.7)",
  );

  const managedServicePath = cmsServicePaths.find(Boolean);

  if (managedServicePath) {
    await page.goto(managedServicePath, { waitUntil: "domcontentloaded" });
    await expect(page.locator("main")).toHaveClass(/content-detail--dark/);
    await expect(page.locator("main")).toHaveCSS(
      "background-color",
      "rgb(5, 5, 5)",
    );
  }

  await page.goto("/blog", { waitUntil: "domcontentloaded" });
  const cards = page.locator(".blog-card");
  await expect(cards.first()).toBeVisible();
  await expect(page.locator(".blog-card__media img")).toHaveCount(
    await cards.count(),
  );

  const firstRowMetrics = await cards.evaluateAll((nodes) =>
    nodes.slice(0, 3).map((card) => {
      const media = card.querySelector(".blog-card__media");

      return {
        card: Math.round(card.getBoundingClientRect().height),
        media: Math.round(media?.getBoundingClientRect().height ?? 0),
      };
    }),
  );
  expect(new Set(firstRowMetrics.map(({ card }) => card)).size).toBe(1);
  expect(new Set(firstRowMetrics.map(({ media }) => media)).size).toBe(1);

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/blog/riyadh-venue-timing", {
    waitUntil: "domcontentloaded",
  });
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
    ),
  ).toBe(false);

  if (managedServicePath) {
    await page.goto(managedServicePath, { waitUntil: "domcontentloaded" });
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
      ),
    ).toBe(false);
  }
});

test("local administrator can enter the Arabic Filament dashboard", async ({
  browser,
}) => {
  const page = await browser.newPage({ locale: "ar-SA" });
  await page.goto("http://127.0.0.1:8000/admin");

  await page.getByLabel(/البريد|Email/i).fill("admin@lams-event.local");
  await page.locator("input[type='password']").fill("LocalOnly!2026");
  await page.getByRole("button", { name: /دخول|Sign in/i }).click();

  await expect(page).toHaveURL(/\/admin\/?$/);
  await expect(page.locator("html")).toHaveAttribute("dir", "rtl");
  await expect(page.getByText("لوحة معلومات لمسة")).toBeVisible();

  await page.goto("http://127.0.0.1:8000/admin/services/create");
  await expect(page.getByRole("region", { name: "معلومات الخدمة" })).toBeVisible();
  await expect(page.getByLabel("اسم الخدمة*")).toBeVisible();
  await expect(page.getByRole("textbox", { name: "تفاصيل الخدمة" })).toBeVisible();
  await expect(page.getByLabel("معرض الصور — اختياري")).toBeVisible();
  await expect(page.getByRole("button", { name: "حفظ كمسودة" })).toBeVisible();
  await expect(page.getByRole("button", { name: "نشر", exact: true })).toBeVisible();
  await expect(page.getByText("قالب جاهز — اختياري")).toHaveCount(0);
  await expect(page.getByText("محتوى صفحة الخدمة")).toHaveCount(0);
  await expect(page.getByText("زر الإجراء وواتساب")).toHaveCount(0);
  await expect(page.getByText("اقتراح محتوى")).toHaveCount(0);

  await page.getByLabel("اسم الخدمة*").fill("تنسيق حفلات التخرج");
  await page.getByLabel("اسم الخدمة*").blur();
  await expect(page.getByLabel("الوصف المختصر")).toHaveValue(/تنسيق حفلات التخرج/);

  const seo = page.getByRole("region", { name: "تحسين الظهور في محركات البحث" });
  await seo.getByRole("button").click();
  await expect(seo.getByLabel("الرابط الأساسي Canonical تلقائي")).not.toHaveAttribute("required");
  await expect(seo.getByText("عبارة البحث المستهدفة — اختياري")).toBeVisible();
  await expect(seo.getByLabel(/Meta Keywords/)).toHaveCount(0);
  await expect(seo.getByText("معاينة نتيجة Google")).toBeVisible();
  await expect(page.getByRole("region", { name: "Schema" })).toHaveCount(0);

  const developer = page.getByRole("region", { name: "وضع المطور" });
  await developer.getByRole("button").click();
  await developer.getByRole("switch", { name: "تفعيل تعديل JSON المتقدم" }).check();
  await expect(page.getByRole("region", { name: "Schema" })).toBeVisible();

  await page.close();
});
