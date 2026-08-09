# Audit of «Берег 61°»

Audit date: 9 August 2026. Source snapshot:
[`fe42b16e`](https://github.com/R1chardR0e/bereg61-wordpress/commit/fe42b16e529a067144cb3cc348e4398b3fcb69d8).
Public build checked at <https://wp.andrey-digital.ru/>.

This is a product, interface and source audit, not a penetration test. It uses
the canonical repository, desktop/mobile DOM inspection and browser performance
snapshots. It does not include production analytics, assisted-technology user
research or tests on a real booking back office.

## What is already strong

- It is a native Full Site Editing theme, not an Elementor export. Templates,
  patterns and the core plugin have clear responsibilities.
- Structured rooms and offers are represented by custom post types and editable
  Gutenberg metadata. Requests are private and excluded from public REST/search.
- The request form has REST and no-JavaScript transports, nonce validation,
  honeypot, minimum fill time and an IP-HMAC rate limit.
- Twelve source photographs have responsive 768 and 1280 pixel variants. The
  repository includes Playwright, axe and Lighthouse checks.
- The inspected live snapshot was fast on desktop: Lighthouse was approximately
  99 with 0.8 s LCP. Mobile was lower at approximately 89 with 3.4 s LCP. These
  are one-time measurements, not a performance guarantee.

These are useful engineering foundations. The biggest opportunity is not a new
framework; it is a more editable system, stronger interaction design and removal
of several broken or misleading paths.

## Findings

| Priority | Finding                                           | Evidence                                                                                                                                                | User impact                                                               |
| -------- | ------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| Critical | Mobile navigation is clipped                      | The live 390 px overlay remains inside the sticky, backdrop-filtered header containing block; only its top area and first item are usable               | Visitors cannot reliably reach most sections or booking                   |
| High     | Contact map disappears on the published page      | `scripts/seed.php` stores a raw inline SVG in a `core/html` block, while the live `/kontakty/` DOM retains adjacent text but not the SVG                | The page presents an incomplete layout and no location aid                |
| High     | A room's facts render twice                       | `templates/single-b61_room.html` includes `b61/room-facts`; the seed also prepends the same block to every room's `post_content`                        | Duplicate technical information looks like a template defect              |
| High     | A failed email can still produce success          | `B61_Booking::process()` stores the request, calls `send_notifications()` without acting on delivery result, then always returns HTTP 201               | The interface can promise contact that operations never received          |
| High     | The demo resembles an operating hotel             | Seed, header and mail code use a plausible domain, phone, coordinates and booking language; demo status is not persistent in the live shell             | Portfolio visitors can mistake fictional details for a real offer         |
| Medium   | Main page content is not normally editable        | `templates/front-page.html` expands eight PHP file patterns; the seeded front page has empty `post_content`                                             | Editing the WordPress page does not edit the visible home page            |
| Medium   | Header/footer business data is hardcoded          | `parts/header.html` contains logo, URLs and labels; similar contact data is repeated in seed/templates                                                  | Site Editor controls do not behave as expected and updates can drift      |
| Medium   | Product context is lost at conversion             | Every room/offer CTA points to the general `/bronirovanie/` URL; there is no room/offer deep link or guaranteed preselection                            | Users repeat a choice and request attribution is weaker                   |
| Medium   | Registered editorial fields do not control output | `_b61_featured` and offer validity dates are registered and seeded, while the room/offer queries sort by `menu_order` and do not filter on those values | Editors can change fields that appear to do nothing                       |
| Medium   | “Выбрать даты” is only a lead form                | No availability, price calculation, capacity matching, confirmation or payment is implemented                                                           | CTA wording creates a stronger expectation than the feature fulfils       |
| Medium   | Request management stops at storage               | Requests have no workflow status, assignee/notes, retention job or export/erasure integration                                                           | A demo can receive personal data but lacks a complete operating lifecycle |
| Medium   | Seed is destructive to later edits                | `b61_seed_post()` calls `wp_update_post()` for every existing slug; bootstrap therefore replaces editor-owned title/content/excerpt                     | Re-running setup can erase manual CMS changes                             |
| Low      | Search and collection navigation are unfinished   | A search template exists without a visible search entry point; custom lists use broad queries and no purposeful pagination UI                           | Content discovery will not scale beyond the small seeded set              |
| Low      | Structured discovery is generic                   | Source contains no project-owned Hotel, Offer, Event or breadcrumb JSON-LD and the demo sitemap is not deliberately bounded                             | Search semantics do not describe the business model                       |

## Design audit

### Identity is polished but familiar

Cormorant Garamond, Manrope, bone, pine and copper create an attractive hotel
surface, but they also reproduce a common “quiet northern luxury” formula. Large
italic serif phrases and atmospheric photography do most of the differentiation.
There is no distinctive interaction, data view or graphic system that would make
the project recognisable without its photographs.

### The page is long without becoming deeper

The home template contains eight successive patterns and measured roughly
12,000 px at desktop and 13,800 px on mobile. Many sections repeat the same
eyebrow, oversized heading, image/text split and equal card grid. The result is
visually consistent but low in information rhythm: scrolling reveals another
composition, not a new capability.

At 1280 × 720 the hero action began below the first viewport. A portfolio visitor
can see the art direction before understanding the next action. Several metadata
labels reach approximately `0.55rem`, which is too small for dependable reading.
Short inner pages inherit a large footer that can dominate their content.

### Interaction is almost absent

The inspected home page reported no active Web Animations API animations. Apart
from short transitions and image hover zoom, the interface does not react to
scroll position, room selection, season, capacity or price. This matters in a
portfolio case because the design demonstrates composition but little product
thinking.

### Hierarchy relies on repeated decoration

Frequent uppercase eyebrow labels and similarly expressive H2/H3 headings reduce
contrast between navigation, explanation and decision points. Card collections
are visually equal even when one room or offer is marked as featured in data.
The UI therefore has metadata for hierarchy but does not express it.

## Changes carried into FORM 27

FORM 27 treats every audit item as an acceptance condition:

- A top-layer mobile dialog, keyboard focus loop and viewport E2E test replace
  the clipped WordPress navigation overlay.
- The first viewport contains the primary “Собрать проект” action at all three
  target widths.
- Seven sections use at least four layout families. Eyebrows are limited to three
  meaningful uses; equal three-card rows are not the default composition.
- A light simulator, product configurator, saved project, deep links,
  before/after cases and downloadable PDF specification make state changes
  visible and useful.
- Product option lists are published by WordPress and every selected value is
  validated against the current product before a request is stored. Registered
  fields drive catalog filtering, product facts, SKU, PDF specification or
  request validation.
- The home page is editable database content; reusable dynamic blocks own only
  data-backed interfaces.
- Seed creates missing content and preserves editor changes unless `--force` is
  explicit.
- Full WordPress reports mail failure truthfully and manages request status,
  notes and retention. Static Pages never pretends to send a request.
- A persistent disclaimer, fictional contact data, `noindex` and disabled schema
  distinguish the portfolio demo from a real manufacturer.

## Verification priority

Before considering the old project remediated, reproduce the menu and map fixes
on its live host, test request mail failure, remove duplicated room facts and
change the seed ownership policy. Visual refinements alone would not address the
highest-risk findings.
