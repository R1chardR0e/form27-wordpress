import js from "@eslint/js";

export default [
  {
    ignores: ["dist/**", "node_modules/**", "release/**", "vendor/**"],
  },
  js.configs.recommended,
  {
    files: ["**/*.mjs"],
    languageOptions: {
      ecmaVersion: "latest",
      sourceType: "module",
      globals: {
        Buffer: "readonly",
        URL: "readonly",
        URLSearchParams: "readonly",
        clearTimeout: "readonly",
        console: "readonly",
        fetch: "readonly",
        process: "readonly",
        setTimeout: "readonly",
      },
    },
    rules: {
      "no-console": "off",
    },
  },
  {
    files: ["scripts/e2e/**/*.mjs"],
    languageOptions: {
      globals: {
        document: "readonly",
        window: "readonly",
      },
    },
  },
  {
    files: [
      "wp-content/themes/form27/assets/js/**/*.js",
      "wp-content/plugins/form27-core/assets/js/**/*.js",
    ],
    languageOptions: {
      ecmaVersion: "latest",
      sourceType: "script",
      globals: {
        Blob: "readonly",
        CustomEvent: "readonly",
        FormData: "readonly",
        IntersectionObserver: "readonly",
        Intl: "readonly",
        TextEncoder: "readonly",
        Uint8Array: "readonly",
        URL: "readonly",
        URLSearchParams: "readonly",
        console: "readonly",
        document: "readonly",
        fetch: "readonly",
        history: "readonly",
        localStorage: "readonly",
        setTimeout: "readonly",
        window: "readonly",
      },
    },
    rules: {
      "no-unused-vars": ["error", { caughtErrors: "none" }],
    },
  },
];
