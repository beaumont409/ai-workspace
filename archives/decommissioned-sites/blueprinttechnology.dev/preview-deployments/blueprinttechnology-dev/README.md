# Blueprint Technology visual preview

This isolated, static preview redesigns the Blueprint Technology landing page around the supplied Larven AI reference: midnight blue lighting, an illuminated tilted glass panel, original Blueprint copy, and responsive service highlights.

## Preview

Open `index.html` directly, or run a static server from this directory:

```sh
python3 -m http.server 4178 --bind 127.0.0.1
```

Then visit <http://127.0.0.1:4178/>.

The preview does not load PHP, collect information, or submit payments or orders. The service cards retain the existing iPhone, Samsung, and Google Pixel names and listed prices. Their links point to the existing public `/redirect.html` route, which points to `/payment.php`; only GET navigation is involved. All production files and server routing are left untouched.
