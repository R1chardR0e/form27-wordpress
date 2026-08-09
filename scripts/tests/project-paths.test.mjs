import path from "node:path";
import test from "node:test";
import assert from "node:assert/strict";
import {
  outputPathForUrl,
  pagePathFromUrl,
  staticReference,
} from "../lib/project-paths.mjs";

test("maps directory-style routes to index.html", () => {
  assert.equal(
    outputPathForUrl("dist", "/catalog/system-48/"),
    path.join("dist", "catalog", "system-48", "index.html"),
  );
});

test("keeps asset filenames", () => {
  assert.equal(
    outputPathForUrl("dist", "/wp-content/theme.css"),
    path.join("dist", "wp-content", "theme.css"),
  );
});

test("rejects paths that escape the export directory", () => {
  assert.throws(() => outputPathForUrl("dist", "/../../secret.txt"));
});

test("rewrites only same-origin references", () => {
  const origin = "http://127.0.0.1:9401";
  assert.equal(
    staticReference("http://127.0.0.1:9401/catalog/?type=cut", origin),
    "/catalog/?type=cut",
  );
  assert.equal(
    staticReference("https://example.com/spec.pdf", origin),
    "https://example.com/spec.pdf",
  );
});

test("does not crawl administrative or API routes", () => {
  const origin = "http://127.0.0.1:9401";
  assert.equal(pagePathFromUrl("/wp-admin/", origin), null);
  assert.equal(pagePathFromUrl("/wp-json/form27/v1/products", origin), null);
  assert.equal(pagePathFromUrl("/catalog/", origin), "/catalog/");
});
