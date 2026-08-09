# Delivery and deployment

## GitHub release

The public Blueprint downloads `form27-theme.zip` and `form27-core.zip` from the
latest GitHub Release. Create that release before sharing the Playground URL:

```bash
npm ci
npm run check
npm run package
git tag vX.Y.Z
git push origin main
git push origin vX.Y.Z
```

Replace `vX.Y.Z` with the new, unused semantic version for that release.

The release workflow publishes both ZIPs, the Blueprint, `manifest.json` and
`SHA256SUMS.txt`. The ZIP root folders are `form27/` and `form27-core/`, so both
packages can also be installed through a normal WordPress admin area.

Public Playground URL:

```text
https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2FR1chardR0e%2Fform27-wordpress%2Fmain%2Fplayground-blueprint.json
```

Every visitor gets an isolated browser database. This is a technical demo, not
persistent hosting.

## Cloudflare Pages

The production Pages project is connected directly to GitHub:

- project: `form27-wordpress`;
- repository: `R1chardR0e/form27-wordpress`;
- production branch: `main`;
- build command: `npm run export:static`;
- build output directory: `dist`;
- public URL: <https://form27-wordpress.pages.dev/>.

Every push to `main` triggers a Cloudflare build and production deployment. The
Pages build uses the checked-in `wrangler.toml`, while the exporter creates the
same strict static artifact exercised by the local and GitHub Actions checks.

Direct Upload remains available as a recovery path:

```bash
npx wrangler login
npm run export:static
npm run deploy:pages
```

If the name is unavailable, use `form27-lighting` in `wrangler.toml`, the npm
deploy script and GitHub workflow together. Do not silently publish under a
different account or project.

The separate GitHub Actions deploy job is optional. To enable it as a fallback,
add repository secrets:

- `CLOUDFLARE_API_TOKEN`: scoped to Pages edit/deploy for this account.
- `CLOUDFLARE_ACCOUNT_ID`: the target Cloudflare account ID.

The deployment workflow skips the external deploy when either secret is absent,
but still builds and verifies the artifact. Static assets are free to serve under
the Pages free tier; this repository does not provision a paid PHP host.

The verified artifact job has no Cloudflare credentials. A separate dependent
job downloads that exact artifact and exposes credentials only to its detection
and deploy steps. Lighthouse gates performance, accessibility and best practices
at 0.95 on desktop and 0.90 on mobile; its reports remain workflow artifacts.
The SEO category is not scored because `noindex` is a deliberate demo boundary,
and Playwright instead verifies both the meta directive and `robots.txt`.

## Release verification

After deployment:

1. Check every route in `dist/build.json` and the public Pages domain for HTTP
   200, responsive assets and the visible fictional-demo notice.
2. Run Playwright against Pages by setting `FORM27_E2E_BASE_URL` to the public
   origin and disabling its local web server in an ad hoc verification run.
3. Open a clean Playground URL, wait for seed completion, inspect the Site Editor
   and verify that a request remains inside that one browser session.
4. Confirm that Pages does not issue a request to `/wp-json/form27/v1/requests`
   and does not imply that a form was emailed.
5. Check `robots.txt`, `noindex`, headers, release checksums and the links used in
   the portfolio card.

No custom production domain is attached by this repository. The existing
`wp.andrey-digital.ru` installation and its document root are explicitly outside
the deployment scope.
