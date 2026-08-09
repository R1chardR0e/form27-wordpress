# FORM 27 architecture

## System boundary

FORM 27 has one source of WordPress content and two presentation runtimes:

1. The full runtime is WordPress 7.0 with PHP 8.2 or 8.3. The `form27` block
   theme owns templates and visual tokens. `form27-core` owns content models,
   validation, dynamic blocks, REST endpoints, seeding and request retention.
2. The static runtime is a crawl of that seeded WordPress state. It is prepared
   for Cloudflare Pages and keeps browser-only behavior. It has no PHP, shared
   database, mail transport or persistent requests.

The static exporter injects this immutable boot contract before frontend scripts:

```js
window.FORM27_RUNTIME = Object.freeze({
  mode: "static",
  demo: true,
  productsUrl: "/data/products.v1.json",
  requestEndpoint: null,
  requestsEnabled: false,
});
```

In WordPress, the plugin exposes the equivalent settings through an enqueued
script before its interactive bundle. Components must use the runtime contract
instead of guessing from the hostname.

## WordPress ownership

- `f27_product` stores luminaires; `f27_case` stores interior cases;
  `f27_request` is private and excluded from REST/search.
- Collections, mounting type and application are taxonomies. Technical option
  lists and their product facts are registered post meta.
- Header, footer, navigation and logo remain editable template parts. The home
  page is normal database `post_content`; it is not a hidden file pattern.
- Dynamic blocks render useful HTML in PHP first. JavaScript enhances filters,
  saved projects, visual configuration, transitions and PDF export.
- Seed command: `wp form27 seed`. It creates missing records and updates only
  seed-owned technical fields. It must not overwrite editor content. Destructive
  refresh requires the explicit `wp form27 seed --force` command.

## Request lifecycle

The WordPress endpoint validates the schema version, a REST nonce, consent,
honeypot, minimum fill time, rate limit and every configured option. It stores a
private request only after all checks pass. Mail transport failure is recorded
and reported as `mailSent: false`; the response copy never claims successful
email delivery.

Requests use `new`, `in_progress` and `closed` states, private admin notes and a
30-day default retention period. WordPress export/erasure hooks cover email and
phone data. The static runtime intercepts the form, explains that no data was
sent, and never calls a substitute third-party endpoint.

## Static build

`scripts/export-static.mjs` starts a clean local Playground unless `--source` is
provided, discovers WordPress pages and product/case permalinks, copies
same-origin assets, rewrites local origins and serializes the product endpoint to
`/data/products.v1.json`. Route discovery, every crawled page and every
same-origin referenced asset fail the build on non-2xx responses. A final scan
also rejects missing local `href`, `src`, `srcset`, inline-style and CSS `url()`
targets. The crawler is bounded to 100 pages and 1,500 assets.

The export removes WordPress generator/canonical, feed, REST discovery,
speculation and emoji-loader metadata, injects `noindex`, copies Cloudflare
headers/redirects and rewrites request forms to the inert `/demo-request/`
target. Frontend JavaScript must still intercept that form and show the explicit
demo result.

## Compatibility and graceful failure

- WordPress release line: 7.0; PHP: 8.2 and 8.3; Node: 20.18 or newer.
- Without JavaScript, content, product facts, navigation and links remain
  readable. The configurator exposes its default specification and explains that
  interaction requires JavaScript.
- Without local storage, an in-memory project still supports add, quantity and
  PDF actions for the current page, but is not retained across navigation.
- With reduced motion, state changes are immediate and no content is hidden
  behind animation.
- PDF export renders paginated A4 sheets to canvas only after the user requests
  it, embeds the JPEG pages into a local PDF and downloads the file. Cyrillic is
  drawn with the loaded site fonts; project data never leaves the browser.
