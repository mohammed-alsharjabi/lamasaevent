import {
  areas,
  articles,
  galleries,
  serviceCategories,
  services,
} from "./content";
import type {
  ContentBlock,
  ContentEntity,
  GalleryEntity,
  GalleryItem,
} from "../types/content";

interface NativeListingResult {
  blocks: ContentBlock[];
  supplementalHtml: string;
}

const cmsEntities = (entities: ContentEntity[]): ContentEntity[] =>
  entities
    .filter((entity) => entity.legacy_path === null)
    .sort((left, right) => {
      const orderDifference =
        Number(left.sort_order ?? 0) - Number(right.sort_order ?? 0);

      return orderDifference || left.id - right.id;
    });

const cmsServices = cmsEntities(services);
const cmsServiceCategories = cmsEntities(serviceCategories);
const cmsAreas = cmsEntities(areas);
const cmsArticles = cmsEntities(articles);

const escapeHtml = (value: unknown): string =>
  String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");

const publicPath = (entity: ContentEntity): string => {
  const candidate = entity.public_path ?? entity.legacy_path;

  return typeof candidate === "string" && candidate.startsWith("/")
    ? candidate
    : "/";
};

const description = (entity: ContentEntity): string => {
  for (const candidate of [entity.excerpt, entity.summary]) {
    if (typeof candidate === "string" && candidate.trim() !== "") {
      return candidate.trim();
    }
  }

  const parent = entity.parent as { title?: unknown } | null | undefined;

  return typeof parent?.title === "string"
    ? `خدمة فرعية من ${parent.title}`
    : "";
};

const image = (
  entity: ContentEntity,
  dimensions: { width: number; height: number },
): string => {
  const hero = entity.hero_media_summary;

  if (!hero?.public_url) {
    return "";
  }

  return `<img src="${escapeHtml(hero.public_url)}" alt="${escapeHtml(
    hero.alt || entity.title,
  )}" width="${dimensions.width}" height="${dimensions.height}" loading="lazy" decoding="async">`;
};

const slot = (name: string, html: string): string =>
  html === ""
    ? ""
    : `<!--cms-native:${name}:start-->${html}<!--cms-native:${name}:end-->`;

const homeServiceCard = (service: ContentEntity): string => `
  <a class="ph-svc-card" href="${escapeHtml(publicPath(service))}" data-cms-additions data-cms-native-entry data-cms-kind="service" data-cms-id="${service.id}">
    <div class="ph-svc-card__media">
      ${image(service, { width: 800, height: 550 })}
      <div class="ph-svc-card__veil" aria-hidden="true"></div>
      <h3 class="ph-svc-card__title">${escapeHtml(service.title)}</h3>
    </div>
    <div class="ph-svc-card__body">
      ${description(service) ? `<p class="ph-svc-card__desc">${escapeHtml(description(service))}</p>` : ""}
      <span class="ph-svc-card__more">عرض التفاصيل</span>
    </div>
  </a>`;

const servicesCard = (
  entity: ContentEntity,
  kind: "service" | "service-category",
): string => `
  <li data-cms-additions data-cms-native-entry data-cms-kind="${kind}" data-cms-id="${entity.id}">
    <a class="svx-card" href="${escapeHtml(publicPath(entity))}">
      <div class="svx-card__media">
        ${image(entity, { width: 640, height: 800 })}
        <div class="svx-card__veil" aria-hidden="true"></div>
        <div class="svx-card__body">
          <h2 class="svx-card__h2">${escapeHtml(entity.title)}</h2>
          ${description(entity) ? `<p class="svx-card__line">${escapeHtml(description(entity))}</p>` : ""}
          <span class="svx-card__cta">عرض التفاصيل</span>
        </div>
      </div>
    </a>
  </li>`;

const categoryServicePath = (service: ContentEntity): string => `
  <li data-cms-additions data-cms-native-entry data-cms-kind="service" data-cms-id="${service.id}">
    <a class="sct-path" href="${escapeHtml(publicPath(service))}">
      <span class="sct-path__main">
        <span class="sct-path__title">${escapeHtml(service.title)}</span>
        <span class="sct-path__chev" aria-hidden="true">‹</span>
      </span>
      ${description(service) ? `<span class="sct-path__desc">${escapeHtml(description(service))}</span>` : ""}
    </a>
  </li>`;

const siblingServiceCard = (service: ContentEntity): string => `
  <a class="svc-detail__sibling-card" href="${escapeHtml(publicPath(service))}" data-cms-additions data-cms-native-entry data-cms-kind="service" data-cms-id="${service.id}">
    <div class="svc-detail__sibling-media">
      ${image(service, { width: 640, height: 440 })}
      <div class="svc-detail__sibling-veil" aria-hidden="true"></div>
      <span class="svc-detail__sibling-title">${escapeHtml(service.title)}</span>
    </div>
    ${description(service) ? `<p class="svc-detail__sibling-intro">${escapeHtml(description(service))}</p>` : ""}
    <span class="svc-detail__sibling-hint">التفاصيل</span>
  </a>`;

const articleCard = (article: ContentEntity): string => {
  const topic =
    typeof article.topic === "string" && article.topic.trim() !== ""
      ? article.topic
      : "مقالات لمسة";

  return `
    <article class="blog-card" data-cms-additions data-cms-native-entry data-cms-kind="article" data-cms-id="${article.id}">
      <a class="blog-card__link" href="${escapeHtml(publicPath(article))}">
        <div class="blog-card__media">
          ${image(article, { width: 800, height: 500 })}
          <div class="blog-card__veil" aria-hidden="true"></div>
        </div>
        <div class="blog-card__body">
          <span class="blog-card__topic">${escapeHtml(topic)}</span>
          <h2 class="blog-card__title">${escapeHtml(article.title)}</h2>
          ${description(article) ? `<p class="blog-card__excerpt">${escapeHtml(description(article))}</p>` : ""}
          <span class="blog-card__cta">قراءة المقال</span>
        </div>
      </a>
    </article>`;
};

const areaCard = (area: ContentEntity): string => `
  <li data-astro-cid-hrnj52xi data-cms-additions data-cms-native-entry data-cms-kind="area" data-cms-id="${area.id}">
    <a class="areas-hub__card" href="${escapeHtml(publicPath(area))}" data-astro-cid-hrnj52xi>
      <h2 class="areas-hub__h2" data-astro-cid-hrnj52xi>${escapeHtml(area.title)}</h2>
      ${description(area) ? `<p class="areas-hub__desc" data-astro-cid-hrnj52xi>${escapeHtml(description(area))}</p>` : ""}
      <span class="areas-hub__more" data-astro-cid-hrnj52xi>التفاصيل</span>
    </a>
  </li>`;

const galleryImage = (
  item: GalleryItem,
  className: string,
  eager = false,
): string => `
  <figure class="${className}">
    <img src="${escapeHtml(item.media.public_url)}" alt="${escapeHtml(
      item.alt || item.media.alt || item.title || item.media.original_name,
    )}" loading="${eager ? "eager" : "lazy"}" decoding="async">
  </figure>`;

const gallerySection = (gallery: GalleryEntity): string => {
  const items = gallery.items.filter(
    (item) => item.is_active && Boolean(item.media?.public_url),
  );
  const [featured, ...remaining] = items;
  const headingId = `cms-gallery-title-${gallery.id}`;

  return `
    <section class="gallery-portfolio__section" aria-labelledby="${headingId}" data-cms-additions data-cms-native-entry data-cms-kind="gallery" data-cms-id="${gallery.id}">
      <header class="gallery-portfolio__head">
        <p class="gallery-portfolio__kicker">معرض الأعمال</p>
        <h2 class="gallery-portfolio__title" id="${headingId}">${escapeHtml(gallery.title)}</h2>
        ${gallery.description ? `<p class="gallery-portfolio__lead">${escapeHtml(gallery.description)}</p>` : ""}
      </header>
      ${featured ? `<figure class="gallery-portfolio__feature"><div class="gallery-portfolio__feature-frame"><img src="${escapeHtml(featured.media.public_url)}" alt="${escapeHtml(featured.alt || featured.media.alt || featured.title || gallery.title)}" loading="lazy" decoding="async"></div></figure>` : ""}
      ${remaining.length > 0 ? `<div class="gallery-portfolio__grid">${remaining.map((item) => galleryImage(item, "gallery-portfolio__cell")).join("")}</div>` : ""}
    </section>`;
};

const classList = (tag: string): string[] => {
  const match = tag.match(/\bclass\s*=\s*(["'])(.*?)\1/i);

  return match ? match[2].split(/\s+/).filter(Boolean) : [];
};

const openingTag = (
  html: string,
  className: string,
): { index: number; end: number; name: string } | null => {
  for (const match of html.matchAll(/<([a-z][\w:-]*)\b[^>]*>/gi)) {
    if (!classList(match[0]).includes(className)) {
      continue;
    }

    return {
      index: match.index,
      end: match.index + match[0].length,
      name: match[1],
    };
  }

  return null;
};

const injectIntoClass = (
  html: string,
  className: string,
  marker: string,
  markup: string,
): string | null => {
  if (markup === "" || html.includes(`<!--cms-native:${marker}:start-->`)) {
    return html;
  }

  const opening = openingTag(html, className);

  if (!opening) {
    return null;
  }

  const pattern = new RegExp(`<\\/?${opening.name}\\b[^>]*>`, "gi");
  let depth = 1;

  for (const match of html.slice(opening.end).matchAll(pattern)) {
    const tag = match[0];
    const absoluteIndex = opening.end + match.index;

    if (tag.startsWith("</")) {
      depth -= 1;
    } else if (!tag.endsWith("/>")) {
      depth += 1;
    }

    if (depth === 0) {
      return `${html.slice(0, absoluteIndex)}${slot(marker, markup)}${html.slice(absoluteIndex)}`;
    }
  }

  return null;
};

const injectBeforeClass = (
  html: string,
  className: string,
  marker: string,
  markup: string,
): string | null => {
  if (markup === "" || html.includes(`<!--cms-native:${marker}:start-->`)) {
    return html;
  }

  const opening = openingTag(html, className);

  return opening
    ? `${html.slice(0, opening.index)}${slot(marker, markup)}${html.slice(opening.index)}`
    : null;
};

const integrate = (
  blocks: ContentBlock[],
  className: string,
  marker: string,
  markup: string,
  position: "inside" | "before" = "inside",
): { blocks: ContentBlock[]; inserted: boolean } => {
  if (markup === "") {
    return { blocks, inserted: true };
  }

  for (let index = 0; index < blocks.length; index += 1) {
    const injected =
      position === "before"
        ? injectBeforeClass(blocks[index].html, className, marker, markup)
        : injectIntoClass(blocks[index].html, className, marker, markup);

    if (injected === null) {
      continue;
    }

    const next = [...blocks];
    next[index] = { ...blocks[index], html: injected };

    return { blocks: next, inserted: true };
  }

  return { blocks, inserted: false };
};

export function integrateNativeListings(
  blocks: ContentBlock[],
  routePath: string,
): NativeListingResult {
  let result = { blocks, inserted: true };
  let supplementalHtml = "";

  if (routePath === "/") {
    result = integrate(
      blocks,
      "ph-services__grid",
      "home-services",
      cmsServices.map(homeServiceCard).join(""),
    );
  } else if (routePath === "/services") {
    result = integrate(
      blocks,
      "svx-grid",
      "services-index",
      [
        ...cmsServiceCategories.map((category) =>
          servicesCard(category, "service-category"),
        ),
        ...cmsServices.map((service) => servicesCard(service, "service")),
      ].join(""),
    );
  } else if (routePath === "/blog") {
    result = integrate(
      blocks,
      "blog-index__grid",
      "articles-index",
      cmsArticles.map(articleCard).join(""),
    );
  } else if (routePath === "/areas") {
    result = integrate(
      blocks,
      "areas-hub__grid",
      "areas-index",
      cmsAreas.map(areaCard).join(""),
    );
  } else if (routePath === "/gallery") {
    result = integrate(
      blocks,
      "gallery-page__footnote",
      "galleries-index",
      galleries
        .filter((gallery) => !gallery.is_legacy)
        .map(gallerySection)
        .join(""),
      "before",
    );
  } else {
    const category = serviceCategories.find(
      (candidate) => publicPath(candidate) === routePath,
    );

    if (category) {
      const categoryServices = cmsServices.filter(
        (service) => Number(service.service_category_id) === category.id,
      );

      result = integrate(
        blocks,
        "sct-paths",
        `service-category-${category.id}`,
        categoryServices.map(categoryServicePath).join(""),
      );
    } else {
      const parent = services.find(
        (candidate) => publicPath(candidate) === routePath,
      );
      const children = parent
        ? cmsServices.filter(
            (service) =>
              Number(
                (
                  service.parent as
                    | { id?: number }
                    | null
                    | undefined
                )?.id,
              ) === parent.id,
          )
        : [];
      const childrenMarkup = children.map(siblingServiceCard).join("");

      result = integrate(
        blocks,
        "svc-detail__sibling-grid",
        `service-children-${parent?.id ?? 0}`,
        childrenMarkup,
      );

      if (childrenMarkup !== "" && !result.inserted) {
        supplementalHtml = slot(
          `service-children-${parent?.id ?? 0}`,
          `<section class="svc-detail__section container" data-cms-additions data-cms-kind="service-children">
            <h2 class="svc-detail__h2">الخدمات الفرعية</h2>
            <div class="svc-detail__sibling-grid">${childrenMarkup}</div>
          </section>`,
        );
      }
    }
  }

  return {
    blocks: result.blocks,
    supplementalHtml,
  };
}
