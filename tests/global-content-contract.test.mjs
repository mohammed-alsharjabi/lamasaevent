import assert from "node:assert/strict";
import { readFileSync, readdirSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import test from "node:test";

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const sourceRoot = join(projectRoot, "frontend", "src");
const distRoot = join(projectRoot, "frontend", "dist");
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

const outputFileFor = (routePath) =>
  routePath === "/"
    ? join(distRoot, "index.html")
    : join(distRoot, routePath.replace(/^\/|\/$/g, ""), "index.html");

const nativeSlot = (html, name) =>
  html.match(
    new RegExp(
      `<!--cms-native:${name}:start-->([\\s\\S]*?)<!--cms-native:${name}:end-->`,
      "i",
    ),
  )?.[1] || "";

const internalHrefs = (html) =>
  [...html.matchAll(/<a\b[^>]*\bhref=["']([^"']+)["']/gi)].map(
    (match) => match[1],
  );

test("CMS services share the native home and services grids", () => {
  const expected = exportPayload.services
    .filter((service) => service.legacy_path === null)
    .sort(
      (left, right) =>
        left.sort_order - right.sort_order || left.id - right.id,
    )
    .map((service) => service.public_path);
  const home = readFileSync(outputFileFor("/"), "utf8");
  const services = readFileSync(outputFileFor("/services"), "utf8");

  assert.deepEqual(
    internalHrefs(nativeSlot(home, "home-services")),
    expected,
  );
  assert.deepEqual(
    internalHrefs(nativeSlot(services, "services-index")).filter((href) =>
      href.startsWith("/services/") && !href.startsWith("/services/category/"),
    ),
    expected,
  );
  assert.equal(home.includes('class="cms-additions"'), false);
  assert.equal(services.includes('class="cms-additions"'), false);
});

test("CMS service hierarchy is reflected in native category and parent grids", () => {
  const categories = new Map(
    exportPayload.service_categories.map((category) => [
      category.id,
      category,
    ]),
  );
  const services = new Map(
    exportPayload.services.map((service) => [service.id, service]),
  );

  for (const service of exportPayload.services.filter(
    (candidate) => candidate.legacy_path === null,
  )) {
    const category = categories.get(service.service_category_id);

    if (category) {
      const html = readFileSync(
        outputFileFor(category.public_path || category.legacy_path),
        "utf8",
      );
      assert.match(
        html,
        new RegExp(
          `<li[^>]*data-cms-id=["']${service.id}["'][^>]*>[\\s\\S]*?href=["']${service.public_path}["']`,
          "i",
        ),
      );
    }

    if (service.parent?.id) {
      const parent = services.get(service.parent.id);
      const html = readFileSync(
        outputFileFor(parent.public_path || parent.legacy_path),
        "utf8",
      );
      assert.match(
        html,
        new RegExp(
          `<a[^>]*href=["']${service.public_path}["'][^>]*data-cms-id=["']${service.id}["']`,
          "i",
        ),
      );
    }
  }
});
