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

  await page.close();
});
