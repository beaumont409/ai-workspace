# O.E.M. Unlocks7T cinematic redesign preview

Preview URL: <https://preview.triangle250r.com/oemunlocks-3d/cinematic/>

The existing preview host protects the /oemunlocks-3d/ path with its Website Builder Dashboard HTTP Basic Auth gate. Open the URL with the existing preview credentials.

The checkout styling page is available at:

- <https://preview.triangle250r.com/oemunlocks-3d/cinematic/checkout.html?service=bootloader>
- <https://preview.triangle250r.com/oemunlocks-3d/cinematic/checkout.html?service=frp>

## Scope and behavior

This is an isolated static redesign. The homepage uses the live site's current copy, four process steps, compatibility and ownership notes, service names, $45.00 and $30.00 USD prices, crypto methods, and Cloudflare footer attribution. Cryptocurrency estimates use the existing read-only /oemunlocks-3d/prices.php preview proxy and fall back to “Rate temporarily unavailable.”

The service buttons keep their existing destinations:

- https://oemunlocks.com/payment.php?service=bootloader
- https://oemunlocks.com/payment.php?service=frp

The separate checkout design page offers BTC, LTC, and XMR selection and updates its continue link to the corresponding existing live checkout URL. Its customer fields are disabled, it displays no receiving address or payment quote, and it has no form submission action. Existing quote, wallet, checkout, API, and Telegram notification behavior remains in the production checkout files; this preview does not change them.

## Art and responsive design

The hero object is original SVG artwork in assets/oem-glass-cube.svg, with a dark-chrome housing, smoked-glass face, beveled surfaces, layered reflections, localized green illumination, an engraved unlock emblem, contact shadow, and floor reflection. The asset directory occupies about 100 KiB; the complete working preview directory occupies about 132 KiB on disk.

No image-generation or 3D-rendering tool, renderer, or browser automation was available in this environment. The hero is a carefully layered vector illustration rather than a photorealistic ray-traced render. It stays sharp at all sizes and requires no image framework or external asset service.

## Local preview

From the repository root, run:

    python3 -m http.server 8877 --bind 127.0.0.1 --directory previews/oemunlocks-cinematic

Then open http://127.0.0.1:8877/. Live price estimates are unavailable from this local static server; the original USD prices and fallback message remain visible. The preview checkout's continue links still open the existing public checkout.

## Validation notes

The HTML, CSS breakpoints, local asset references, route destinations, reduced-motion rule, and preview-only disabled fields were checked in source. The intended responsive layouts are:

- 320 px: compact navigation, one-column service cards, one-column process cards
- 390 px: compact navigation, one-column service cards, two-column process cards
- Desktop: two-column hero and services, four-column process cards

No browser executable or browser automation library is installed here, so those widths were not visually inspected in a rendered browser. The existing public preview route may return HTTP 401 until the preview credentials are supplied.

No production site files, live services, DNS, checkout configuration, order data, or shared Nginx configuration were changed.
