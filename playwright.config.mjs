import { defineConfig, devices } from "@playwright/test";

const staticMode = process.env.FORM27_STATIC === "1";
const externalBaseURL = process.env.FORM27_E2E_BASE_URL;
const baseURL =
  externalBaseURL ||
  (staticMode ? "http://127.0.0.1:8799" : "http://127.0.0.1:9400");

export default defineConfig({
  testDir: "./scripts/e2e",
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 2 : undefined,
  reporter: process.env.CI
    ? [["line"], ["html", { open: "never" }]]
    : [["list"]],
  timeout: 45_000,
  expect: {
    timeout: 10_000,
  },
  use: {
    baseURL,
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
    video: "retain-on-failure",
  },
  webServer: externalBaseURL
    ? undefined
    : {
        command: staticMode
          ? "npm run serve:static:test"
          : "node scripts/playground.mjs --ci --port=9400",
        // Playground begins accepting HTTP before its Blueprint has activated
        // the project. Waiting for the project-owned endpoint avoids tests
        // racing the temporary default WordPress site.
        url: staticMode
          ? baseURL
          : `${baseURL}/wp-json/form27/v1/products?per_page=1`,
        reuseExistingServer: !process.env.CI,
        timeout: 240_000,
        stdout: "pipe",
        stderr: "pipe",
      },
  projects: [
    {
      name: "desktop-chromium",
      use: {
        ...devices["Desktop Chrome"],
        viewport: { width: 1440, height: 900 },
      },
    },
    {
      name: "tablet-chromium",
      use: {
        ...devices["Desktop Chrome"],
        viewport: { width: 768, height: 1024 },
      },
    },
    {
      name: "mobile-chromium",
      use: {
        ...devices["Pixel 7"],
        viewport: { width: 390, height: 844 },
      },
    },
  ],
});
