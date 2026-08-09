import path from "node:path";
import { fileURLToPath } from "node:url";

export const repositoryRoot = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

export function resolveInsideRepository(relativePath) {
  const resolved = path.resolve(repositoryRoot, relativePath);
  const relative = path.relative(repositoryRoot, resolved);

  if (
    relative === "" ||
    relative.startsWith(`..${path.sep}`) ||
    path.isAbsolute(relative)
  ) {
    throw new Error(`Unsafe repository path: ${relativePath}`);
  }

  return resolved;
}

export function outputPathForUrl(outputDirectory, pathname) {
  const decoded = decodeURIComponent(pathname);
  if (decoded.split("/").includes("..")) {
    throw new Error(`Unsafe URL path: ${pathname}`);
  }
  const normalized = path.posix.normalize(decoded).replace(/^\/+/, "");

  if (normalized.startsWith("../") || normalized.includes("/../")) {
    throw new Error(`Unsafe URL path: ${pathname}`);
  }

  if (!normalized || normalized.endsWith("/")) {
    return path.join(outputDirectory, normalized, "index.html");
  }

  if (path.posix.extname(normalized)) {
    return path.join(outputDirectory, normalized);
  }

  return path.join(outputDirectory, normalized, "index.html");
}

export function pagePathFromUrl(url, sourceOrigin) {
  const parsed = new URL(url, sourceOrigin);
  if (parsed.origin !== sourceOrigin) {
    return null;
  }

  const pathname = parsed.pathname;
  if (
    pathname.startsWith("/wp-admin") ||
    pathname.startsWith("/wp-json") ||
    pathname.startsWith("/wp-login") ||
    pathname.includes("/feed/") ||
    pathname.includes("/author/") ||
    pathname.includes("/category/")
  ) {
    return null;
  }

  return pathname;
}

export function staticReference(value, sourceOrigin) {
  if (!value || value.startsWith("#") || value.startsWith("data:")) {
    return value;
  }

  const parsed = new URL(value, sourceOrigin);
  if (parsed.origin !== sourceOrigin) {
    return value;
  }

  return `${parsed.pathname}${parsed.search}${parsed.hash}`;
}
