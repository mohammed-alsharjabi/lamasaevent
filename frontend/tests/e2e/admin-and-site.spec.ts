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
  await expect(page.getByRole("region", { name: "الإضافة السريعة" })).toBeVisible();
  await expect(page.getByLabel("اسم الخدمة*")).toBeVisible();
  await expect(page.getByLabel("قالب صفحة الخدمة")).toBeVisible();

  const seo = page.getByRole("region", { name: "SEO" });
  await seo.getByRole("button").click();
  await expect(seo.locator("input[type='url']")).not.toHaveAttribute("required");
  await expect(seo.getByText("Meta Keywords — اختياري")).toBeVisible();
  await expect(page.getByRole("region", { name: "Open Graph" })).toBeVisible();
  await expect(page.getByRole("region", { name: "Schema" })).toBeVisible();

  await page.close();
});
