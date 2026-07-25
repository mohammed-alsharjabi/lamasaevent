import { expect, test } from "@playwright/test";

test("recovered public page preserves Arabic SEO shell", async ({ page }) => {
  await page.goto("/");

  await expect(page.locator("html")).toHaveAttribute("dir", "rtl");
  await expect(page.locator("link[rel='canonical']")).toHaveAttribute(
    "href",
    "https://lams-event.com/",
  );
  await expect(page.locator("main")).toBeVisible();
});

test("published CMS additions are visible on public listing pages", async ({
  page,
}) => {
  await page.goto("/");
  await expect(
    page.locator(".cms-additions").getByRole("heading", {
      name: "تصنيف الخدمة",
    }),
  ).toBeVisible();

  await page.goto("/services");
  await expect(
    page.locator(".cms-additions").getByRole("link", {
      name: /تصنيف الخدمة/,
    }),
  ).toHaveAttribute("href", "/services/tsnyf-alkhdm");

  await page.goto("/services/party-arches-riyadh");
  await expect(
    page.locator(".cms-additions").getByRole("heading", {
      name: "الخدمات الفرعية",
    }),
  ).toBeVisible();
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
  await page
    .getByRole("region", {
      name: "تحسين محركات البحث وبيانات المشاركة",
    })
    .getByRole("button")
    .click();
  await expect(page.getByLabel("Canonical URL (اختياري)")).not.toHaveAttribute(
    "required",
  );
  await expect(page.getByLabel("Meta Keywords (اختياري)")).toBeVisible();

  await page.close();
});
