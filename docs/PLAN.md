# Gimnazija „Jovan Dučić" Doboj — CMS plan

Custom school CMS replacing the WordPress site at https://gimnazija.net.
One Laravel app serves both the public website and the admin panel.

## 1. Stack and conventions

| Area | Decision |
|---|---|
| Framework | Laravel 13, Vue starter kit (Inertia 3, Vue 3, Tailwind 4, Fortify, Wayfinder) |
| Admin panel | Inertia + Vue, under `/admin` |
| Public site | **Blade** (server-rendered, for SEO). CSS/JS ported from `resources/design/home.html`, built with Vite as a separate entry (`resources/css/site.css`, `resources/js/site.js`), no Tailwind on the public side |
| Code language | English: models, controllers, columns, variables, routes names |
| UI language | Serbian Latin (admin and public). App locale `sr_Latn`, validation messages translated in `lang/sr_Latn/` |
| URLs | Serbian, identical to the WP site (see §4) |
| Dates | `dd.mm.yyyy.` (e.g. `09.11.2026.`); written-out form `06 nov 2026` |
| Slugs | Transliterated: č/ć→c, š→s, ž→z, đ→dj |
| Database | MySQL everywhere (local, tests, production). WP import reads a second MySQL connection |
| Images | `intervention/image` v3 (GD or Imagick) → WebP variants |
| Rich text | Tiptap editor in admin; HTML sanitized on save (`symfony/html-sanitizer`) |
| Drag & drop | `vue-draggable-plus` (menu tree, gallery ordering) |

## 2. Roles and permissions

`users.role` column backed by a PHP enum `Role { Admin, Editor, Author }`. Enforced with Laravel Policies + a `role` middleware for admin-only areas.

| Section | Admin | Editor | Author |
|---|---|---|---|
| Posts | all | all (edit, delete, publish) | own only |
| Pages | all | all | all |
| Media | all | all | all (delete blocked if in use) |
| Menu | ✓ | ✓ | ✓ |
| Galleries | ✓ | ✓ | ✓ |
| Events | ✓ | ✓ | ✓ |
| Categories | ✓ | — | — |
| Users (incl. roles) | ✓ | — | — |
| Alumni testimonials | ✓ | — | — |

- Public registration is **disabled** (Fortify). Admin creates users and assigns roles.
- Admin cannot remove the admin role from themself (guards against lockout).
- Existing starter-kit features stay: login, password reset, 2FA, passkeys, profile settings.

## 3. Data model

```
users            id, name, email, password, role, …(Fortify columns)
categories       id, name, slug (unique), description?, parent_id?
category_post    category_id, post_id      (several categories per post; first = breadcrumb)
posts            id, user_id, title, slug, excerpt?, content (html),
                 featured_media_id?, status (draft|published), published_at?,
                 meta_title?, meta_description?, wp_id? (import), timestamps, soft deletes
pages            id, user_id, title, slug, content (html), featured_media_id?,
                 status, published_at?, meta_title?, meta_description?, timestamps, soft deletes
media            id, user_id, disk, path, original_name, mime, width, height, size,
                 alt?, caption?, variants (json), timestamps
mediables        media_id, mediable_type, mediable_id, role (gallery|inline), sort_order
                 → the "other images" of a post/page + images used inline in content
galleries        id, name, slug, type (slider|standard), is_system
                 system: home-slider (type slider), home-top, home-bottom — can't be deleted
gallery_items    id, gallery_id, media_id, caption?, sort_order,
                 focal_x, focal_y (0–100, default 50/50 → CSS object-position),
                 slider-only: badge?, title?, subtitle?, link_label?, link_url?
tags             id, name, slug
gallery_item_tag gallery_item_id, tag_id
menu_items       id, parent_id?, sort_order, type (page|category|custom|posts-index),
                 page_id?, category_id?, url?, label?   (label falls back to page/category title)
                 → one main menu only; footer links are hardcoded
events           id, title, short_description, date, time?, location?, label?, is_published
alumni_testimonials id, name, generation (year), profession?, quote, photo_media_id?,
                 sort_order, is_published
redirects        id, from_path (unique), to_path, status_code (301)
```

Notes:
- **Scheduled publishing**: `status = published` and `published_at` in the future. Public queries use `published_at <= now()`, so no cron job is needed.
- **Slug uniqueness across posts and pages**: both live at `/{slug}/`, so a custom validation rule checks both tables plus reserved words (`category`, `vijesti`, `admin`, `login`, `page`, `feed`, `wp-content`, `sitemap.xml`, …).
- **Slug changes** create a 301 row in `redirects` automatically, to preserve SEO.
- **Media in use**: deletion is blocked when the media is a featured image, in `mediables`, a gallery item, or an alumni photo. Inline images in content HTML are synced into `mediables` (role `inline`) on save, so they are covered too. The admin UI shows *where* it is used.

## 4. Public URLs (match WordPress)

| URL | What |
|---|---|
| `/` | Home page |
| `/{slug}/` | Page or post (page checked first, then post) |
| `/vijesti/`, `/vijesti/page/{n}/` | All news (posts archive) |
| `/category/{slug}/`, `/category/{slug}/page/{n}/` | Category archive |
| `/?s=term` | Search (WP style) |
| `/feed/` | RSS (keeps existing subscribers/aggregators working) |
| `/sitemap.xml` | Sitemap |
| `/wp-content/uploads/{path}` | Media files, served directly by the web server (old and new) |

- Trailing slashes are kept (WP style); non-slash variants 301 to slash.
- Unknown paths: check `redirects` table, then 404.
- Every page outputs `<title>`, meta description, canonical, Open Graph / Twitter tags (featured image), and JSON-LD (`EducationalOrganization` on home, `NewsArticle` on posts).

## 5. Image handling

On upload (admin, images only: jpg, png, webp; max ~15 MB upload):
1. Auto-rotate by EXIF, strip metadata.
2. Store a capped "original" (max 2560px long side) — the full camera file is not kept.
3. Generate WebP variants: `thumb` 400w, `medium` 800w, `large` 1600w, `hero` 1920w (only where the source is large enough).
4. Store under `public/wp-content/uploads/{YYYY}/{MM}/` (the `uploads` disk). The WordPress uploads folder is copied there as-is, so old image URLs keep working; new uploads follow the same structure. The folder is not in git.
5. Public templates use `srcset`/`sizes` + `loading="lazy"` with explicit width/height.

Processing runs in a queued job if uploads get slow; synchronous to start.

**Recommended sizes** (shown as a hint in the upload/picker UI, with a warning when the photo is smaller):

| Use | Recommended | Minimum | Ratio |
|---|---|---|---|
| Hero slider | 1920 × 1080 | 1600 × 900 | 16:9 |
| Post/page featured image | 1600 × 900 | 1200 × 675 | 16:9 |
| Gallery / ribbon photos | 1600 on the long side | 800 | any |
| Alumni photo | 400 × 400 | 200 × 200 | 1:1 |

Photos that don't fit are **never cropped on the server**. The front end crops with `object-fit: cover`, so the layout always holds. The crop centres on the item's focal point, which editors set by clicking on the photo in the gallery editor (default: centre).

## 6. Admin panel (Inertia)

Sidebar (Serbian labels): Kontrolna tabla, Objave, Stranice, Mediji, Meni, Galerije, Najave (događaji), Bivši učenici*, Kategorije*, Korisnici*  (*admin only).

- **Objave / Stranice**: list with search, filter (status, category, author), pagination. Editor: title, slug (auto, editable), Tiptap content, featured image picker, "other images" picker with drag-ordering, category (posts), status + publish date, SEO fields, live preview link.
- **Mediji**: grid, multi-file drag & drop upload, alt/caption edit, "used in" info, delete (blocked if used). Same component works as a picker modal everywhere.
- **Meni**: WP-like. Left: add pages / categories / custom link / news index. Right: nested drag & drop tree (nesting = submenu).
- **Galerije**: list (Home slider / Home top / Home bottom are system galleries), add photos from media, reorder, caption, tags, focal point. The slider gallery also has badge, title, subtitle and button (label + link) per slide.
- **Najave**: date (dd.mm.yyyy.), title, short description, optional time, location and label. Home page shows upcoming ones only.
- **Bivši učenici** (admin): name, generation, profession, quote, photo, order, published.
- **Kategorije** (admin): CRUD, optional parent.
- **Korisnici** (admin): CRUD, role assignment.

## 7. Public site (Blade) — from `resources/design/home.html`

Layout: topbar, header with main menu (from Menu builder), footer (hardcoded links), lightbox, to-top.

Home page sections and data source:

| Section | Source |
|---|---|
| Hero slider | Home slider gallery (photo, focal point, badge, title, subtitle, button) |
| Hero cards, Upis 2026 block, O nama + stats, Izdvajamo, Sekcije pills | Hardcoded Blade for now |
| Photo ribbon | Home top gallery (also: first 3 photos = "O nama" collage, 4th = alumni photo) |
| Vijesti | 5 latest published posts |
| Naši učenici | Removed from the design |
| Galerija (masonry + tag filters + lightbox) | Home bottom gallery, filter buttons = tags used in it |
| Poruke bivših učenika | Alumni testimonials |
| Najave | Upcoming events |
| ~~Social strip~~ | Removed 2026-11-11 (photo row above the footer) |

Other templates: post (title, date, featured image, content, gallery + lightbox, category link, breadcrumb, latest news sidebar), page (same, no category), news/category archive (paginated), search results, 404.

## 8. Phases

1. **Foundation** — MySQL, packages, `Role` enum + policies, disable registration, admin layout + Serbian nav, `sr_Latn` locale, date/slug helpers, seed admin user.
2. **Media library** — upload pipeline, variants, picker component, usage tracking, delete guard.
3. **Categories + Posts** — CRUD, Tiptap, featured/other images, status & scheduling, slug rule + redirects, policies & tests.
4. **Pages** — same editor, shared slug namespace.
5. **Menu builder** — tree editor, locations.
6. **Galleries + tags, Events, Alumni**.
7. **Public site (Blade)** — layout + home from the design, post/page/archive/search/404, SEO tags, sitemap, RSS, redirects.
8. **WordPress importer** (later) — second DB connection, artisan command: categories, posts (keep slugs, dates, `wp_id`), featured images and inline images from `wp-content/uploads` → media library, rewrite image URLs in content.
9. **Deploy** — server, storage link, queue worker (if used), backups, 301 checks against the old sitemap.

Each phase ends with feature tests (policies, routes, validation) passing.

## 9. Decisions log

- 2026-11-10: Separate **Home slider** gallery with recommended sizes; front end crops with focal point.
- 2026-11-10: "Naši učenici" section removed.
- 2026-11-10: Posts can have **several categories**.
- 2026-11-10: Events get optional time, location, label.
- 2026-11-10: Footer links hardcoded; Menu builder manages the main menu only.
- 2026-11-10: **MySQL** everywhere, including tests.
- 2026-11-10: Media lives in `public/wp-content/uploads` (WP folder copied in; new uploads go there too).
- 2026-11-10: Public site built (Blade). "Izdvajamo" cards use optional photos from `public/images/izdvajamo/{casopis,dramska,takmicenja}.jpg` (emoji placeholder until added). Stats numbers are placeholders.
- 2026-11-10: Laravel's "remove trailing slash" rule removed from `public/.htaccess`; the app 301s `/slug` → `/slug/` (EnsureTrailingSlash).
- 2026-11-10: WordPress import done (`php artisan wp:import`, re-runnable, matches by wp_id): 11 categories, 1,065 posts (1,062 published, 3 drafts), 3,018 photos. Image blocks moved from the text into each post's gallery (the featured image is not repeated); video/embeds removed; links to gimnazija.net made relative; primary category = most specific one. All 1,062 published URLs kept (4 URL-encoded slugs got clean slugs + 301). Photos are registered in place from `public/wp-content/uploads` (no conversion; WordPress's resized copies are the srcset variants).
- 2026-11-10: Pages imported (`php artisan wp:import-pages`, re-runnable): the 12 published pages linked from the old main menu and the "Linkovi" menu. Page-builder (WPBakery) layout dropped, text kept, its photos moved to the page gallery; videos and the contact form skipped ("Dramska sekcija" had only videos, so it is empty). The main menu was rebuilt from the old one (same structure; category links stay category links; Vijesti → news archive).

## 10. Still open

- Production hosting details: Imagick or GD? Can we run a queue worker / cron? (Not blocking until deploy.)
- 2026-11-11: Posts and pages have two optional YouTube link fields (`video_url_1`, `video_url_2`). Any normal YouTube link form is accepted (watch, youtu.be, shorts, live, embed); other links are rejected. Shown below the text and above the gallery as privacy-friendly youtube-nocookie embeds (16:9, lazy-loaded).
- 2026-11-11: Alumni testimonials imported (`php artisan wp:import-testimonials`, re-runnable, matched by name): all 12 from the old site with name, title ("designation"), text and photo, in the old home-page order (newest first). The home page shows the first 3 by order; the rest are in the admin (Bivši učenici) and can be reordered there.
- 2026-11-11: Home testimonials rotate 3 at a time (all published, in admin order; fade every 8 s). Small dots at the top right of the section heading show/choose the group. Pauses on hover/focus, while a message is opened, when off-screen or tab hidden; no auto-rotation with reduced motion. Long messages show 3 lines + "Pročitaj više". The box keeps the tallest group's height so nothing below jumps.
