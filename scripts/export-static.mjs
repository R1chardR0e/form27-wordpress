import fs from "node:fs/promises";
import path from "node:path";
import { load } from "cheerio";
import {
  outputPathForUrl,
  pagePathFromUrl,
  repositoryRoot,
  resolveInsideRepository,
  staticReference,
} from "./lib/project-paths.mjs";
import { startForm27Playground, stopForm27Playground } from "./playground.mjs";

const requiredRoutes = [
  "/",
  "/catalog/",
  "/projects/",
  "/specification/",
  "/contacts/",
  "/privacy/",
];
const excludedExtensions = new Set([".php", ".xml"]);
const assetExtensions = new Set([
  ".avif",
  ".css",
  ".gif",
  ".ico",
  ".jpeg",
  ".jpg",
  ".js",
  ".json",
  ".map",
  ".mp4",
  ".png",
  ".svg",
  ".webm",
  ".webmanifest",
  ".webp",
  ".woff",
  ".woff2",
]);

function argumentValue(name, fallback = null) {
  const exact = process.argv.indexOf(name);
  if (exact >= 0 && process.argv[exact + 1]) {
    return process.argv[exact + 1];
  }

  const equals = process.argv.find((argument) =>
    argument.startsWith(`${name}=`),
  );
  return equals ? equals.slice(name.length + 1) : fallback;
}

async function writeFile(destination, contents) {
  await fs.mkdir(path.dirname(destination), { recursive: true });
  await fs.writeFile(destination, contents);
}

async function copyDeploymentFiles(outputDirectory) {
  for (const name of ["_headers", "_redirects", "robots.txt"]) {
    await fs.copyFile(
      path.join(repositoryRoot, "cloudflare", name),
      path.join(outputDirectory, name),
    );
  }
}

async function fetchRequired(url) {
  const response = await fetch(url, { redirect: "follow" });
  if (!response.ok) {
    throw new Error(`${response.status} ${response.statusText}: ${url}`);
  }
  return response;
}

function isAssetUrl(url, sourceOrigin) {
  const parsed = new URL(url, sourceOrigin);
  if (parsed.origin !== sourceOrigin) {
    return false;
  }

  return (
    parsed.pathname.startsWith("/wp-content/") ||
    parsed.pathname.startsWith("/wp-includes/") ||
    assetExtensions.has(path.posix.extname(parsed.pathname).toLowerCase())
  );
}

function runtimeScript() {
  const runtime = {
    mode: "static",
    demo: true,
    productsUrl: "/data/products.v1.json",
    requestEndpoint: null,
    requestsEnabled: false,
  };

  return `<script id="form27-static-runtime">window.FORM27_RUNTIME=Object.freeze(${JSON.stringify(runtime)});document.documentElement.dataset.form27Runtime="static";</script>`;
}

function addSrcsetAssets(value, currentUrl, sourceOrigin, assetQueue) {
  const rewritten = value
    .split(",")
    .map((candidate) => {
      const [reference, ...descriptor] = candidate.trim().split(/\s+/);
      const absolute = new URL(reference, currentUrl);
      if (isAssetUrl(absolute, sourceOrigin)) {
        assetQueue.add(absolute.href);
      }
      return [staticReference(reference, sourceOrigin), ...descriptor].join(
        " ",
      );
    })
    .join(", ");

  return rewritten;
}

function transformHtml({
  html,
  currentUrl,
  sourceOrigin,
  pageQueue,
  assetQueue,
}) {
  const $ = load(html, { decodeEntities: false });

  $("base").remove();
  $(
    [
      'meta[name="generator"]',
      'link[rel="shortlink"]',
      'link[rel="EditURI"]',
      'link[rel="wlwmanifest"]',
      'link[type*="oembed"]',
      'link[type="application/rss+xml"]',
      'link[rel="https://api.w.org/"]',
      'link[type="application/json"][href*="/wp-json/"]',
      "style#wp-emoji-styles-inline-css",
      "script#wp-emoji-settings",
      'script[src*="wp-emoji-release"]',
      'script[type="speculationrules"]',
    ].join(", "),
  ).remove();
  $("script").each((_, element) => {
    const source = $(element).html() || "";
    if (
      source.includes("wp-emoji-settings") ||
      source.includes("_wpemojiSettings") ||
      source.includes("wpEmojiSettingsSupports")
    ) {
      $(element).remove();
    }
  });
  $(
    `link[rel="dns-prefetch"][href="//${new URL(sourceOrigin).hostname}"]`,
  ).remove();
  $('link[rel="canonical"]').remove();
  $('meta[name="robots"]').remove();
  $("head").prepend(
    `${runtimeScript()}<meta name="robots" content="noindex,nofollow,noarchive">`,
  );

  $("a[href]").each((_, element) => {
    const href = $(element).attr("href");
    if (!href) {
      return;
    }

    const pagePath = pagePathFromUrl(href, sourceOrigin);
    if (
      pagePath &&
      !excludedExtensions.has(path.posix.extname(pagePath).toLowerCase())
    ) {
      pageQueue.add(pagePath);
    }
    $(element).attr("href", staticReference(href, sourceOrigin));
  });

  const resourceAttributes = [
    ["script[src]", "src"],
    ["img[src]", "src"],
    ["source[src]", "src"],
    ["video[poster]", "poster"],
    ["link[href]", "href"],
  ];

  for (const [selector, attribute] of resourceAttributes) {
    $(selector).each((_, element) => {
      const reference = $(element).attr(attribute);
      if (!reference) {
        return;
      }
      const absolute = new URL(reference, currentUrl);
      if (isAssetUrl(absolute, sourceOrigin)) {
        assetQueue.add(absolute.href);
      }
      $(element).attr(attribute, staticReference(reference, sourceOrigin));
    });
  }

  $("img[srcset], source[srcset]").each((_, element) => {
    const srcset = $(element).attr("srcset");
    if (srcset) {
      $(element).attr(
        "srcset",
        addSrcsetAssets(srcset, currentUrl, sourceOrigin, assetQueue),
      );
    }
  });

  $(
    'form[data-form27-request], form[data-f27-request-form], form[action*="admin-post.php"], form[action*="/requests"]',
  ).each((_, element) => {
    $(element)
      .attr("action", "/demo-request/")
      .attr("method", "post")
      .attr("data-form27-static", "true");
  });

  const inlineAssets = html.matchAll(/url\(["']?([^"')]+)["']?\)/g);
  for (const match of inlineAssets) {
    const absolute = new URL(match[1], currentUrl);
    if (isAssetUrl(absolute, sourceOrigin)) {
      assetQueue.add(absolute.href);
    }
  }

  return $.html()
    .replaceAll(sourceOrigin, "")
    .replaceAll(encodeURIComponent(sourceOrigin), "");
}

async function discoverRestRoutes(sourceOrigin, pageQueue) {
  const endpoints = [
    "/wp-json/wp/v2/pages?per_page=100&_fields=link,slug",
    "/wp-json/wp/v2/f27_product?per_page=100&_fields=link",
    "/wp-json/wp/v2/f27_case?per_page=100&_fields=link",
  ];

  for (const endpoint of endpoints) {
    const response = await fetch(new URL(endpoint, sourceOrigin));
    if (!response.ok) {
      throw new Error(`Route discovery failed: ${response.status} ${endpoint}`);
    }
    const records = await response.json();
    for (const record of records) {
      if (
        endpoint.includes("/pages?") &&
        !["home", "specification", "contacts", "privacy"].includes(record.slug)
      ) {
        continue;
      }
      const pagePath = pagePathFromUrl(record.link, sourceOrigin);
      if (pagePath) {
        pageQueue.add(pagePath);
      }
    }
  }
}

async function exportProducts(sourceOrigin, outputDirectory) {
  const endpoint = new URL("/wp-json/form27/v1/products", sourceOrigin);
  const response = await fetchRequired(endpoint);
  const payload = await response.json();

  if (
    payload?.schemaVersion !== 1 ||
    !Array.isArray(payload.products) ||
    payload.products.length < 1
  ) {
    throw new Error("Product endpoint does not satisfy CatalogEnvelopeV1");
  }

  await writeFile(
    path.join(outputDirectory, "data/products.v1.json"),
    `${JSON.stringify(payload).replaceAll(sourceOrigin, "")}\n`,
  );
}

async function fileExists(filePath) {
  try {
    const stats = await fs.stat(filePath);
    return stats.isFile();
  } catch {
    return false;
  }
}

function localPathFromReference(reference, currentPath) {
  if (
    !reference ||
    reference.startsWith("#") ||
    reference.startsWith("data:")
  ) {
    return null;
  }

  const base = new URL(currentPath, "https://form27.static");
  const parsed = new URL(reference, base);
  return parsed.origin === base.origin ? parsed.pathname : null;
}

async function validateStaticReferences(
  outputDirectory,
  pagePaths,
  assetPaths,
) {
  const failures = [];

  async function validateReference(reference, currentPath, owner) {
    let pathname;
    try {
      pathname = localPathFromReference(reference, currentPath);
    } catch {
      failures.push(`${owner}: invalid reference ${reference}`);
      return;
    }
    if (!pathname) {
      return;
    }

    const target = outputPathForUrl(outputDirectory, pathname);
    if (!(await fileExists(target))) {
      failures.push(`${owner}: ${reference} -> ${pathname}`);
    }
  }

  for (const pagePath of pagePaths) {
    const filePath = outputPathForUrl(outputDirectory, pagePath);
    const $ = load(await fs.readFile(filePath, "utf8"), {
      decodeEntities: false,
    });

    for (const attribute of ["href", "src", "poster"]) {
      const elements = $(`[${attribute}]`).toArray();
      for (const element of elements) {
        await validateReference(
          $(element).attr(attribute),
          pagePath,
          `${pagePath} [${attribute}]`,
        );
      }
    }

    for (const element of $("[srcset]").toArray()) {
      const candidates = ($(element).attr("srcset") || "").split(",");
      for (const candidate of candidates) {
        await validateReference(
          candidate.trim().split(/\s+/)[0],
          pagePath,
          `${pagePath} [srcset]`,
        );
      }
    }

    for (const element of $("[style]").toArray()) {
      const style = $(element).attr("style") || "";
      for (const match of style.matchAll(/url\(["']?([^"')]+)["']?\)/g)) {
        await validateReference(match[1], pagePath, `${pagePath} [style]`);
      }
    }
  }

  for (const assetPath of assetPaths) {
    if (path.posix.extname(assetPath).toLowerCase() !== ".css") {
      continue;
    }
    const cssPath = outputPathForUrl(outputDirectory, assetPath);
    const css = await fs.readFile(cssPath, "utf8");
    for (const match of css.matchAll(/url\(["']?([^"')]+)["']?\)/g)) {
      await validateReference(match[1], assetPath, `${assetPath} url()`);
    }
  }

  if (failures.length) {
    throw new Error(
      `Static export contains broken local references:\n${failures
        .map((failure) => `- ${failure}`)
        .join("\n")}`,
    );
  }
}

async function exportSite(sourceOrigin, outputDirectory) {
  const pageQueue = new Set(requiredRoutes);
  const processedPages = new Set();
  const assetQueue = new Set();
  const processedAssets = new Set();

  await discoverRestRoutes(sourceOrigin, pageQueue);

  while (pageQueue.size) {
    if (processedPages.size > 100) {
      throw new Error("Static crawl exceeded the 100-page safety limit");
    }

    const pathname = pageQueue.values().next().value;
    pageQueue.delete(pathname);
    if (processedPages.has(pathname)) {
      continue;
    }

    const currentUrl = new URL(pathname, sourceOrigin);
    const response = await fetch(currentUrl, { redirect: "follow" });
    if (!response.ok) {
      throw new Error(`Page failed: ${response.status} ${pathname}`);
    }

    const contentType = response.headers.get("content-type") || "";
    if (!contentType.includes("text/html")) {
      processedPages.add(pathname);
      continue;
    }

    const transformed = transformHtml({
      html: await response.text(),
      currentUrl: response.url,
      sourceOrigin,
      pageQueue,
      assetQueue,
    });
    await writeFile(outputPathForUrl(outputDirectory, pathname), transformed);
    processedPages.add(pathname);
  }

  while (assetQueue.size) {
    if (processedAssets.size > 1_500) {
      throw new Error("Static crawl exceeded the 1,500-asset safety limit");
    }

    const assetUrl = assetQueue.values().next().value;
    assetQueue.delete(assetUrl);
    const parsed = new URL(assetUrl);
    if (processedAssets.has(parsed.pathname)) {
      continue;
    }

    const response = await fetch(assetUrl);
    if (!response.ok) {
      throw new Error(
        `Page-referenced asset failed: ${response.status} ${parsed.pathname}`,
      );
    }

    let body = Buffer.from(await response.arrayBuffer());
    const contentType = response.headers.get("content-type") || "";
    if (contentType.includes("text/css")) {
      const css = body
        .toString("utf8")
        .replaceAll(sourceOrigin, "")
        .replaceAll(encodeURIComponent(sourceOrigin), "");
      body = Buffer.from(css);
    }
    await writeFile(outputPathForUrl(outputDirectory, parsed.pathname), body);
    processedAssets.add(parsed.pathname);

    if (contentType.includes("text/css")) {
      const css = body.toString("utf8");
      for (const match of css.matchAll(/url\(["']?([^"')]+)["']?\)/g)) {
        const nested = new URL(match[1], assetUrl);
        if (isAssetUrl(nested, sourceOrigin)) {
          assetQueue.add(nested.href);
        }
      }
    }
  }

  await exportProducts(sourceOrigin, outputDirectory);
  await copyDeploymentFiles(outputDirectory);
  await validateStaticReferences(
    outputDirectory,
    processedPages,
    processedAssets,
  );
  await writeFile(
    path.join(outputDirectory, "build.json"),
    `${JSON.stringify(
      {
        schemaVersion: 1,
        mode: "static-demo",
        generatedAt: new Date().toISOString(),
        pages: [...processedPages].sort(),
        assets: processedAssets.size,
      },
      null,
      2,
    )}\n`,
  );

  console.log(
    `Exported ${processedPages.size} pages and ${processedAssets.size} assets.`,
  );
}

async function main() {
  const outputArgument = argumentValue("--output", "dist");
  const outputDirectory = resolveInsideRepository(outputArgument);
  const requestedSource = argumentValue("--source");
  let playground;

  await fs.rm(outputDirectory, { recursive: true, force: true });
  await fs.mkdir(outputDirectory, { recursive: true });

  try {
    let sourceOrigin;
    if (requestedSource) {
      sourceOrigin = new URL(requestedSource).origin;
    } else {
      const port = Number(argumentValue("--port", "9401"));
      playground = await startForm27Playground({ port, login: false });
      sourceOrigin = new URL(playground.serverUrl).origin;
    }

    await exportSite(sourceOrigin, outputDirectory);
  } finally {
    await stopForm27Playground(playground);
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
