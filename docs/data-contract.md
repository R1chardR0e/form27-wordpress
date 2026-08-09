# FORM 27 data contract

The browser, REST API and static export share a versioned catalog envelope.
Unknown fields are ignored. The static exporter refuses an incompatible
integer `schemaVersion`; interactive consumers should do the same before a
future schema is introduced.

## `CatalogEnvelopeV1`

`GET /wp-json/form27/v1/products` and the static
`/data/products.v1.json` file return:

```json
{
  "schemaVersion": 1,
  "demo": true,
  "generatedAt": "2026-08-09T12:00:00Z",
  "products": [],
  "pagination": {
    "page": 1,
    "perPage": 24,
    "total": 6,
    "totalPages": 1
  }
}
```

`pagination` is present in the REST response and may be absent in another
serialized consumer. The static exporter currently preserves it.

The REST endpoint accepts `page`, `per_page` (maximum 50), `s`, `collection`,
`mounting`, `application` and `featured` query parameters. A `ProductV1` has the
following shape:

```json
{
  "id": 101,
  "slug": "spot-s48",
  "name": "SPOT S48",
  "excerpt": "Поворотный акцентный модуль для экспозиций и архитектурных деталей.",
  "url": "/product/spot-s48/",
  "image": "/wp-content/uploads/spot-s48.webp",
  "collections": [{ "slug": "system-48", "name": "SYSTEM 48" }],
  "mounting": [{ "slug": "track", "name": "Трековый" }],
  "application": [{ "slug": "gallery", "name": "Галереи" }],
  "code": "S48-SPOT",
  "dimensions": "Ø45 × 135 мм",
  "wattages": [12, 18],
  "lumens": [980, 1500],
  "cct": [2700, 3000, 4000],
  "cri": [90, 95],
  "beams": ["24°", "36°"],
  "finishes": ["Чёрный RAL 9005", "Белый RAL 9003"],
  "controls": ["On/Off", "DALI-2"],
  "ip": "IP20",
  "price": 24500,
  "featured": true,
  "image_url": "/wp-content/uploads/spot-s48.webp"
}
```

The configuration fields are allowed value lists, not precomputed variants.
Each submitted option must be a member of its product's current list. The
position of a value in `wattages` maps to the same position in `lumens`. Prices
are integer rubles and the interface always labels them as fictional demo
prices.

## Browser project state

The saved project uses the `localStorage` key `form27.project.v1`:

```json
{
  "version": 1,
  "updatedAt": "2026-08-09T12:00:00Z",
  "items": [
    {
      "productId": 101,
      "slug": "spot-s48",
      "name": "SPOT S48",
      "quantity": 4,
      "options": {
        "power": "18",
        "cct": "3000",
        "cri": "95",
        "beam": "24°",
        "finish": "Чёрный RAL 9005",
        "control": "DALI-2"
      },
      "sku": "F27-S48-SPOT-18-30-95-24-BK-DALI",
      "price": 24500
    }
  ]
}
```

The browser limits a project to 30 positions and a position to 99 units. Name,
SKU and price make the local interface useful offline, but the server does not
trust them: it resolves the product, validates all six selected options and
rebuilds those values before storage or email.

Configurator deep links use `product`, `power`, `cct`, `cri`, `beam`, `finish`
and `control` query parameters. Unknown products fall back to the block's
default product. An unknown option falls back to the first current value.

## Request API

`POST /wp-json/form27/v1/requests` accepts JSON:

```json
{
  "schemaVersion": 1,
  "contact": {
    "name": "Имя",
    "phone": "+7 900 000-00-00",
    "email": "name@example.test",
    "company": "Студия"
  },
  "project": {
    "items": [
      {
        "productId": 101,
        "slug": "spot-s48",
        "quantity": 4,
        "options": {
          "power": "18",
          "cct": "3000",
          "cri": "95",
          "beam": "24°",
          "finish": "Чёрный RAL 9005",
          "control": "DALI-2"
        }
      }
    ]
  },
  "message": "Комментарий",
  "consent": true,
  "startedAt": 1786276800000,
  "website": ""
}
```

The browser sends `X-WP-Nonce`. `schemaVersion` must be integer `1`. The contact
must include a 2–100 character name and either a valid email or a phone. The
honeypot must remain blank; the form must take at least three seconds and no more
than two hours. One to 30 configured items are required.

Success is HTTP 201:

```json
{
  "schemaVersion": 1,
  "request": {
    "id": 501,
    "status": "new",
    "stored": true,
    "mailSent": true
  },
  "message": "Заявка сохранена и отправлена. Мы свяжемся с вами."
}
```

WordPress may persist a request while mail delivery fails. In that case the
response still reports HTTP 201, `mailSent` is `false`, and the message says the
request is visible in the admin panel but email was not sent. Validation and
nonce failures use WordPress REST error objects with HTTP 400 or 403; rate
limiting is HTTP 429 and persistence failure is HTTP 500. The static runtime
never calls this endpoint and explicitly says that no data was sent or saved.
