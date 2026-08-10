# FORM 27

FORM 27 is a design-first WordPress demonstration for a fictional manufacturer
of architectural lighting. It combines a native block theme, a companion plugin,
an interactive product configurator, a saved specification and client-side PDF
generation.

The products, specifications, prices, projects and contact details are fictional.
The repository is a portfolio case, not technical documentation or a commercial
offer.

![FORM 27 home screen](docs/form27-preview.webp)

[Open the permanent static demo](https://form27.andrey-digital.ru/).
[Open the temporary WordPress demo in Playground](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2FR1chardR0e%2Fform27-wordpress%2Fmain%2Fplayground-blueprint.json).
The design and implementation decisions are grounded in the
[`БЕРЕГ 61°` source and live-site audit](docs/bereg-audit.md).

## Local development without Docker

Requirements: Node.js 20.18 or newer and npm.

```bash
npm ci
npm run dev
```

The command starts a fresh WordPress 7.0 / PHP 8.3 site at
`http://127.0.0.1:9400`, mounts the theme and plugin, runs the idempotent demo
seed and signs in to the temporary admin area. No system PHP, database or Docker
installation is needed. The Playground database is disposable.

The public browser runtime is opened from the checked-in
[`playground-blueprint.json`](playground-blueprint.json). It installs packaged
assets from the latest GitHub Release, so a release must exist before that link
works.

## Docker alternative

Docker is optional and was not available in the original development environment.
For a conventional MariaDB installation:

```bash
cp .env.example .env
# Replace every password placeholder in .env.
npm run bootstrap:docker
```

The site uses the configured `FORM27_HTTP_PORT`; Mailpit uses
`FORM27_MAILPIT_PORT`. The bootstrap script never includes an admin password in
the repository or command output.

## Quality and release commands

```bash
npm run check
npm run test:e2e
npm run export:static
npm run test:e2e:static
npm run package
```

- `check` validates JavaScript, formatting, project structure and utility tests.
- `test:e2e` starts an isolated Playground and tests 390, 768 and 1440 px layouts.
- `export:static` builds `dist/` from the same seeded WordPress state.
- `test:e2e:static` serves that artifact on an isolated test port and repeats
  the browser, accessibility, PDF and inert-request checks deterministically.
- `preview:static` runs the actual Cloudflare Pages emulator on port 8788 for a
  manual headers and redirects check.
- `test:lighthouse` gates the static home, catalog and specification at 0.95 on
  desktop and 0.90 on mobile for performance, accessibility and best practices.
  SEO scoring is intentionally excluded because every portfolio-demo page is
  explicitly `noindex`.
- `package` creates installable theme/plugin ZIPs and SHA-256 checksums in
  `release/`.

PHP syntax and WordPress Coding Standards run in GitHub Actions on PHP 8.2 and
8.3 because PHP is not installed on the original workstation.

## Free public delivery

The permanent static demo is published at
[`form27.andrey-digital.ru`](https://form27.andrey-digital.ru/), with
[`form27-wordpress.pages.dev`](https://form27-wordpress.pages.dev/) retained as
the Pages fallback. Cloudflare Pages is connected directly to
`R1chardR0e/form27-wordpress`; each push to `main` runs
`npm run export:static` and publishes `dist/`. The GitHub workflow still builds
and verifies the same artifact independently, and its optional
credential-scoped deploy job remains available as a fallback.

The static runtime retains the catalog, configurator, local project and PDF
export, but it deliberately cannot persist or email a request. A visible message
explains that behavior before submission. The WordPress Playground link remains
the full CMS demo: every visitor receives a separate temporary database and
requests disappear with that browser session.

Deployment and secret setup are documented in
[`docs/deployment.md`](docs/deployment.md). Architecture and API contracts are in
[`docs/architecture.md`](docs/architecture.md) and
[`docs/data-contract.md`](docs/data-contract.md).

## License

Theme, plugin and project code are licensed under GPL-2.0-or-later. Generated
portfolio imagery may only be reused where its generation terms permit.
