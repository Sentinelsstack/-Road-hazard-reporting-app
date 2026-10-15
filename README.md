# RoadHaz — Road Hazard Reporter

A full-stack web application for reporting and tracking road hazards. Citizens drop a pin on the map, attach a photo, and describe the hazard; admins triage reports in real time through a dashboard with live chat.

Built with **Laravel 11** (Sanctum token auth) and a vanilla-JS single-page frontend using **Leaflet**, **Chart.js**, and **Pusher**. Installable as a PWA and buildable into an Android APK via GitHub Actions.

## Features

**Citizens**
- Report hazards with GPS capture, photo upload, and 8 hazard types (pothole, flooding, landslide, …)
- Interactive Leaflet map with color-coded markers and hazard list
- Live location tracking with a pulsing "you are here" marker
- Real-time chat with admins on every report (Pusher, with polling fallback)
- Light/dark theme, offline service worker

**Admins**
- Stats dashboard: totals, reports over time, breakdowns by type and status
- Update report status (pending → confirmed → resolved / dismissed)
- Promote/demote users, suspend abusive accounts

## Tech stack

| Layer | Choice |
|---|---|
| Backend | Laravel 11, PHP 8.2+, Sanctum bearer tokens |
| Database | MySQL / MariaDB |
| Real-time | Pusher Channels (private channels per hazard) |
| Frontend | Vanilla JS SPA, Leaflet 1.9, Chart.js 4 |
| PWA | Web manifest + service worker |
| Android | Bubblewrap (Trusted Web Activity) via GitHub Actions |

## Local setup

```bash
git clone <your-repo-url>
cd Ollama

composer install
cp .env.example .env
php artisan key:generate
```

Configure `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306        # XAMPP MariaDB often uses 3307
DB_DATABASE=road_hazards
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_DRIVER=pusher   # optional: falls back to polling without Pusher keys
SESSION_DRIVER=database
```

Then migrate and seed:

```bash
php artisan migrate --seed
php artisan serve
```

Visit `http://127.0.0.1:8000`.

**Seeded accounts** (change these in production):

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `Admin@12345678` |
| User | `user@example.com` | `User@12345678` |

## Testing

```bash
php artisan test
```

## Security

- Strict **Content-Security-Policy** (no `unsafe-inline` scripts), plus nosniff, `X-Frame-Options: DENY`, referrer and permissions policies
- All user-generated content HTML-escaped on render (stored-XSS defence)
- Hazard chat access enforced server-side (owner-or-admin only)
- Rate limiting on login (5/min), register (3/min), and API (120/min)
- Mass-assignment hardened (`role` / `suspended` not fillable)
- SRI integrity hashes on all CDN assets
- Passwords hashed with bcrypt; 12-character minimum

## Accessibility

- WCAG 2.2 AA oriented: full keyboard support, visible focus indicators, ARIA tabs/labels/live regions, screen-reader-only labels, `prefers-reduced-motion` support, zoom never disabled

## PWA & Android APK

The app is a PWA (`public/manifest.json` + service worker). The Android APK is a Trusted Web Activity wrapper around your **hosted** site — it requires the app to be live over HTTPS.

GitHub Actions (`.github/workflows/build-apk.yml`) builds the APK when you **publish a release** and attaches it to the release page.

One-time setup:

1. Deploy the app to an HTTPS host
2. Push this repo to GitHub, add a repository variable **`APP_URL`** (Settings → Secrets and variables → Variables) pointing at the base URL, e.g. `https://roadhaz.example.com`
3. *Recommended* — create a signing key so future updates install in place:
   ```bash
   keytool -genkeypair -v -keystore android.keystore -alias android \
     -keyalg RSA -keysize 2048 -validity 10000
   base64 -w0 android.keystore   # → secret KEYSTORE_BASE64
   # your chosen password         → secret KEYSTORE_PASSWORD
   ```
4. Publish a release (e.g. tag `v1.0.0`) — the workflow attaches `RoadHaz.apk`

Manual builds: *Actions → Build Android APK → Run workflow* and enter the site URL.

> The APK never contains PHP or your database — it's a window onto the hosted site.

## Project layout

```
app/Http/Controllers/Api/   REST API (auth, hazards, messages, admin)
app/Http/Middleware/        SecurityHeaders (CSP etc.)
app/Models/                 User, Hazard, Message
public/js/app.js            SPA (map, chat, dashboard)
public/css/app.css          Theme + dark mode
resources/views/app.blade.php
.github/workflows/          APK build pipeline
```
