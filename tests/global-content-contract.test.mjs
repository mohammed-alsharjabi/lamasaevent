import assert from "node:assert/strict";
import { readFileSync, readdirSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import test from "node:test";

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const sourceRoot = join(projectRoot, "frontend", "src");
const exportPayload = JSON.parse(
  readFileSync(join(sourceRoot, "data", "content-export.json"), "utf8"),
);

const componentFiles = [
  "components/Header.astro",
  "components/Footer.astro",
  "components/BookingForm.astro",
  "components/FloatDock.astro",
  "layouts/BaseLayout.astro",
];

test("global site content is exported from CMS-managed records", () => {
  assert.ok(exportPayload.contact);
  assert.deepEqual(
    exportPayload.menus.map((menu) => menu.location).sort(),
    ["footer", "header"],
  );

  for (const key of [
    "brand",
    "footer",
    "booking_form",
    "booking_options",
    "ui_labels",
  ]) {
    assert.ok(exportPayload.settings[key], `Missing CMS setting: ${key}`);
  }
});

test("Astro global components do not contain fixed business content", () => {
  const source = componentFiles
    .map((file) => readFileSync(join(sourceRoot, file), "utf8"))
    .join("\n");

  for (const fixedValue of [
    "info@lams-event.com",
    "966502560106",
    "لمسه التميز",
    "تنسيق مناسبات — الرياض",
    "برمجة م/ محمد الشرجبي",
    "إرسال الطلب للمدير العام",
  ]) {
    assert.equal(
      source.includes(fixedValue),
      false,
      `Fixed business content remains in Astro source: ${fixedValue}`,
    );
  }
});

test("admin forms never expose slug fields and use the shared media picker", () => {
  const filamentRoot = join(projectRoot, "backend", "app", "Filament");
  const adminSource = readdirSync(filamentRoot, {
    recursive: true,
    encoding: "utf8",
  })
    .filter((file) => file.endsWith(".php"))
    .map((file) => readFileSync(join(filamentRoot, file), "utf8"))
    .join("\n");

  for (const forbidden of [
    "TextInput::make('slug')",
    "TextEntry::make('slug')",
    "TextColumn::make('slug')",
    "الرابط المختصر",
    "slugRedirectAction",
  ]) {
    assert.equal(
      adminSource.includes(forbidden),
      false,
      `Admin still exposes slug UI: ${forbidden}`,
    );
  }

  for (const form of [
    "Resources/Articles/Schemas/ArticleForm.php",
    "Resources/Services/Schemas/ServiceForm.php",
    "Resources/Pages/Schemas/PageForm.php",
    "Resources/Areas/Schemas/AreaForm.php",
    "Resources/ServiceCategories/Schemas/ServiceCategoryForm.php",
    "Resources/ArticleCategories/Schemas/ArticleCategoryForm.php",
  ]) {
    assert.match(
      readFileSync(join(filamentRoot, form), "utf8"),
      /ManagedContentFields::heroMedia\(\)/,
      `Form does not use the shared media picker: ${form}`,
    );
  }
});
