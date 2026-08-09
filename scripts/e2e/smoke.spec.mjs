import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

const staticMode = process.env.FORM27_STATIC === "1";
const publicRoutes = [
  "/",
  "/catalog/",
  "/projects/",
  "/specification/",
  "/contacts/",
  "/privacy/",
];

async function addConfiguredProduct(page) {
  const configurator = page.locator("[data-f27-config-form]").first();
  await expect(configurator).toBeVisible();
  await configurator
    .getByRole("button", { name: /добавить в проект/i })
    .click();
  await expect
    .poll(() =>
      page.evaluate(() => {
        const project = JSON.parse(
          window.localStorage.getItem("form27.project.v1") || "null",
        );
        return project?.items?.length || 0;
      }),
    )
    .toBe(1);
}

async function expectPdfDownload(page) {
  const downloadPromise = page.waitForEvent("download");
  await page.locator("[data-f27-print]").first().click();
  const download = await downloadPromise;
  expect(download.suggestedFilename()).toBe("FORM-27-specification.pdf");

  const stream = await download.createReadStream();
  const chunks = [];
  for await (const chunk of stream) {
    chunks.push(chunk);
  }
  const contents = Buffer.concat(chunks);
  expect(contents.byteLength).toBeGreaterThan(5_000);
  expect(contents.subarray(0, 8).toString("ascii")).toBe("%PDF-1.4");
}

async function expectRouteImagesLoaded(page, route) {
  const images = page.locator("img[src]");
  for (let index = 0; index < (await images.count()); index += 1) {
    const image = images.nth(index);
    await image.scrollIntoViewIfNeeded();
    await expect
      .poll(
        () =>
          image.evaluate((element) =>
            element.complete ? element.naturalWidth : 0,
          ),
        { message: `${route} contains an image that did not load` },
      )
      .toBeGreaterThan(0);
  }
}

test.beforeEach(async ({ page }) => {
  await page.goto("/", { waitUntil: "networkidle" });
});

test("dynamic runtime reports a fully seeded site", async ({ request }) => {
  test.skip(staticMode);
  const response = await request.get("/wp-json/form27/v1/health");
  expect(response.status()).toBe(200);
  await expect(response.json()).resolves.toMatchObject({
    schemaVersion: 1,
    status: "ready",
  });
});

test("the first viewport establishes the brand and primary action", async ({
  page,
}) => {
  await expect(page.locator("body")).toContainText("FORM 27");
  await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
  await expect(page.getByText(/демонстрац|вымышлен/i).first()).toBeVisible();

  const primaryAction = page
    .getByRole("link", { name: /собрать проект/i })
    .first();
  await expect(primaryAction).toBeVisible();
  const box = await primaryAction.boundingBox();
  const viewport = page.viewportSize();
  expect(box && viewport && box.y + box.height <= viewport.height).toBe(true);
});

test("demo metadata and responsive picture sources are safe", async ({
  page,
  request,
}) => {
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
    "content",
    /noindex/,
  );
  await expect(page.locator('script[type="application/ld+json"]')).toHaveCount(
    0,
  );

  const sources = page.locator("picture source[srcset]");
  expect(await sources.count()).toBeGreaterThanOrEqual(3);
  for (let index = 0; index < (await sources.count()); index += 1) {
    const srcset = await sources.nth(index).getAttribute("srcset");
    for (const candidate of (srcset || "").split(",")) {
      const reference = candidate.trim().split(/\s+/)[0];
      if (!reference || reference.startsWith("data:")) continue;
      const response = await request.get(reference);
      expect(response.ok(), `picture source must load: ${reference}`).toBe(
        true,
      );
    }
  }

  const pictureImages = page.locator("picture img");
  expect(await pictureImages.count()).toBeGreaterThanOrEqual(3);
  await expectRouteImagesLoaded(page, "/");
});

test("primary public routes render complete images without overflow", async ({
  page,
}) => {
  for (const route of publicRoutes) {
    const response = await page.goto(route, { waitUntil: "networkidle" });
    expect(response?.ok(), `${route} must return HTTP 2xx`).toBe(true);
    await expect(page.locator("body")).toContainText("FORM 27");
    await expectRouteImagesLoaded(page, route);
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - window.innerWidth,
    );
    expect(
      overflow,
      `${route} must not overflow horizontally`,
    ).toBeLessThanOrEqual(1);
  }
});

test("mobile navigation opens above the page and closes with Escape", async ({
  page,
}, testInfo) => {
  test.skip(testInfo.project.name !== "mobile-chromium");

  const menuButton = page.getByRole("button", { name: /меню/i });
  await expect(menuButton).toBeVisible();
  await menuButton.click();
  await expect(
    page.getByRole("link", { name: /каталог/i }).first(),
  ).toBeVisible();
  await page.keyboard.press("Escape");
  await expect(menuButton).toHaveAttribute("aria-expanded", "false");
});

test("page has no critical or serious axe violations", async ({ page }) => {
  const results = await new AxeBuilder({ page }).analyze();
  const blocking = results.violations.filter((violation) =>
    ["critical", "serious"].includes(violation.impact),
  );
  expect(blocking).toEqual([]);
});

test("catalog exposes the versioned product contract", async ({ request }) => {
  const response = await request.get(
    staticMode
      ? "/data/products.v1.json"
      : "/wp-json/form27/v1/products?per_page=24",
  );
  expect(response.ok()).toBe(true);
  const catalog = await response.json();
  expect(catalog.schemaVersion).toBe(1);
  expect(catalog.demo).toBe(true);
  expect(catalog.products.length).toBeGreaterThanOrEqual(6);
  expect(catalog.products[0]).toEqual(
    expect.objectContaining({
      slug: expect.any(String),
      wattages: expect.any(Array),
      cct: expect.any(Array),
      finishes: expect.any(Array),
    }),
  );
});

test("catalog filtering and configurator deep links preserve intent", async ({
  page,
}) => {
  await page.goto("/catalog/", { waitUntil: "networkidle" });
  const catalog = page.locator("[data-f27-catalog]").first();
  const cards = catalog.locator("[data-f27-product]");
  expect(await cards.count()).toBeGreaterThanOrEqual(6);

  await catalog.locator("[data-f27-search]").fill("SPOT S48");
  await expect(catalog.locator("[data-f27-product]:visible")).toHaveCount(1);
  await catalog.locator("[data-f27-search]").fill("");
  await catalog.locator('[data-f27-filter="system-48"]').click();
  await expect(catalog.locator("[data-f27-product]:visible")).toHaveCount(2);

  const query = new URLSearchParams({
    product: "spot-s48",
    power: "18",
    cct: "4000",
    cri: "95",
    beam: "36°",
    finish: "Белый RAL 9003",
    control: "DALI-2",
  });
  await page.goto(`/?${query}`, { waitUntil: "networkidle" });
  const form = page.locator("[data-f27-config-form]").first();
  await expect(form.locator('[name="product"]')).toHaveValue("spot-s48");
  await expect(form.locator('[name="power"]')).toHaveValue("18");
  await expect(form.locator('[name="cct"]')).toHaveValue("4000");
  await expect(form.locator('[name="cri"]')).toHaveValue("95");
  await expect(form.locator('[name="beam"]')).toHaveValue("36°");
  await expect(form.locator('[name="finish"]')).toHaveValue("Белый RAL 9003");
  await expect(form.locator('[name="control"]')).toHaveValue("DALI-2");
});

test("theme preference persists", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop-chromium");

  const toggle = page.locator("[data-f27-theme-toggle]:visible").first();
  await expect(toggle).toHaveText("Тема: авто");
  await toggle.click();
  await expect(page.locator("html")).toHaveAttribute("data-theme", "light");
  expect(
    await page.evaluate(() => window.localStorage.getItem("form27.theme")),
  ).toBe("light");
  await page.reload({ waitUntil: "networkidle" });
  await expect(page.locator("html")).toHaveAttribute("data-theme", "light");
});

test("reduced motion leaves reveal content visible", async ({
  page,
}, testInfo) => {
  test.skip(testInfo.project.name !== "desktop-chromium");

  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.reload({ waitUntil: "networkidle" });
  await expect(page.locator("html")).not.toHaveClass(/f27-motion-ready/);
  const revealState = await page
    .locator(".f27-reveal")
    .evaluateAll((elements) =>
      elements.map((element) => {
        const styles = window.getComputedStyle(element);
        return { opacity: styles.opacity, transform: styles.transform };
      }),
    );
  expect(revealState.length).toBeGreaterThan(0);
  expect(
    revealState.every(
      ({ opacity, transform }) =>
        opacity === "1" && (transform === "none" || transform.includes("1, 0")),
    ),
  ).toBe(true);
});

test("sticky configurator clears the sticky header", async ({
  page,
}, testInfo) => {
  test.skip(testInfo.project.name !== "desktop-chromium");

  const configurator = page.locator("[data-f27-configurator]").first();
  await configurator.evaluate((element) => {
    const top = element.getBoundingClientRect().top + window.scrollY;
    window.scrollTo(0, top + 260);
  });
  await expect
    .poll(() => page.evaluate(() => window.scrollY))
    .toBeGreaterThan(0);
  const geometry = await page.evaluate(() => {
    const header = document.querySelector(".f27-site-header");
    const visual = document.querySelector(".f27-configurator__visual");
    if (!header || !visual) return null;
    const headerBox = header.getBoundingClientRect();
    const visualBox = visual.getBoundingClientRect();
    return { headerBottom: headerBox.bottom, visualTop: visualBox.top };
  });
  expect(geometry).not.toBeNull();
  expect(geometry.visualTop).toBeGreaterThanOrEqual(geometry.headerBottom + 8);
});

test("configured project persists and downloads a real PDF", async ({
  page,
}) => {
  await addConfiguredProduct(page);

  const project = await page.evaluate(() =>
    JSON.parse(window.localStorage.getItem("form27.project.v1") || "null"),
  );
  expect(project).toEqual(
    expect.objectContaining({
      version: 1,
      items: [
        expect.objectContaining({
          slug: expect.any(String),
          quantity: 1,
          sku: expect.stringMatching(/^F27-/),
        }),
      ],
    }),
  );

  await expectPdfDownload(page);
});

test("storage failure keeps project controls and PDF usable", async ({
  page,
}, testInfo) => {
  test.skip(testInfo.project.name !== "desktop-chromium");

  const pageErrors = [];
  page.on("pageerror", (error) => pageErrors.push(error.message));
  await page.addInitScript(() => {
    for (const method of ["getItem", "setItem", "removeItem"]) {
      Object.defineProperty(window.Storage.prototype, method, {
        configurable: true,
        value() {
          throw new Error(`Storage ${method} is unavailable`);
        },
      });
    }
  });
  await page.reload({ waitUntil: "networkidle" });

  const configurator = page.locator("[data-f27-config-form]").first();
  await configurator
    .getByRole("button", { name: /добавить в проект/i })
    .click();
  const item = page.locator(".f27-project-item").first();
  await expect(item).toBeVisible();
  await item.locator('[data-f27-quantity="1"]').click();
  await expect(item.locator("strong").last()).toHaveText("2");
  await expectPdfDownload(page);
  expect(pageErrors).toEqual([]);
});

test("full WordPress validates schema and reports mail truthfully", async ({
  page,
}, testInfo) => {
  test.skip(staticMode, "Requests are disabled in the static runtime");
  test.skip(testInfo.project.name !== "desktop-chromium");

  const incompatible = await page.evaluate(async () => {
    const response = await fetch("/wp-json/form27/v1/requests", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": window.F27_CONFIG.nonce,
      },
      body: JSON.stringify({ schemaVersion: 2 }),
    });
    return { status: response.status, body: await response.json() };
  });
  expect(incompatible.status).toBe(422);
  expect(incompatible.body.code).toBe("f27_unsupported_schema");

  await addConfiguredProduct(page);
  const form = page.locator("[data-f27-request-form]").first();
  await form.locator('[name="name"]').fill("Тестовый проект");
  await form.locator('[name="email"]').fill("demo@example.test");
  await form.locator('[name="consent"]').check();
  await form.locator('[name="startedAt"]').evaluate((input) => {
    input.value = String(Date.now() - 4_000);
  });
  const responsePromise = page.waitForResponse(
    (response) =>
      response.request().method() === "POST" &&
      response.url().includes("/wp-json/form27/v1/requests"),
  );
  await form.getByRole("button", { name: /отправить проект/i }).click();
  const response = await responsePromise;
  expect(response.status()).toBe(201);
  const result = await response.json();
  expect(result.schemaVersion).toBe(1);
  expect(result.request.stored).toBe(true);
  expect(result.request.mailSent).toEqual(expect.any(Boolean));
  expect(result.message).toMatch(
    result.request.mailSent
      ? /сохранена и отправлена/i
      : /письмо не отправлено/i,
  );
  await expect(form.locator("[data-f27-request-status]")).toHaveText(
    result.message,
  );
});

test("static request form is explicitly inert", async ({ page, request }) => {
  test.skip(!staticMode, "The full WordPress runtime owns request persistence");

  await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
    "content",
    /noindex/,
  );
  await expect(
    page.locator(
      'link[type="application/rss+xml"], link[href*="/wp-json/"], #wp-emoji-settings, #wp-emoji-styles-inline-css',
    ),
  ).toHaveCount(0);
  const robots = await request.get("/robots.txt");
  expect(robots.ok()).toBe(true);
  expect(await robots.text()).toMatch(/Disallow:\s*\//i);

  await addConfiguredProduct(page);
  let requestPosts = 0;
  page.on("request", (request) => {
    if (
      request.method() === "POST" &&
      request.url().includes("/wp-json/form27/v1/requests")
    ) {
      requestPosts += 1;
    }
  });

  const form = page.locator("[data-f27-request-form]").first();
  await expect(form).toBeVisible();
  await form.locator('[name="name"]').fill("Тестовый проект");
  await form.locator('[name="email"]').fill("demo@example.test");
  await form.locator('[name="consent"]').check();
  await form.getByRole("button", { name: /проверить демо-форму/i }).click();
  await expect(form.locator("[data-f27-request-status]")).toHaveText(
    "Демо-режим: данные не отправлены и не сохранены.",
  );
  expect(requestPosts).toBe(0);
});
