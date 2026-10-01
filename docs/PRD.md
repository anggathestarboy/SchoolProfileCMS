# PRD — DraftCMS

## 1. Ringkasan

DraftCMS adalah CMS flat-first ala **Statamic**, tapi datanya disimpan di
**MySQL dengan kolom JSON** (bukan flat-file `.yaml`/`.md` seperti Statamic
asli). Dibangun di atas Laravel 13 + Livewire 4 + Flux UI + Tailwind v4.

Status saat ini: **skema database sudah dirancang penuh** (lihat
`database/migrations/`), dan **kerangka modul CMS sudah di-scaffold** di
`app/Modules/{Nama}/` (satu folder per fitur — Collections, Taxonomies,
Blueprints, Media, Globals, Forms, Navigation, Structure, Sites, Sitemap,
Redirects — lihat `docs/AI-CONTEXT.md` § "Sistem modul CMS" untuk detail).
Tapi **isi tiap modul masih kosong** — belum ada model, controller, atau UI
nyata di dalamnya, cuma provider + folder wireframe. Yang benar-benar
berjalan baru fitur auth (register/login/2FA/passkey via Fortify). Dokumen
ini adalah acuan scope — jangan menambah fitur di luar sini tanpa
persetujuan user, dan semua fitur CMS baru wajib masuk ke modul yang sesuai
(lihat `docs/AI-CONSTRAINTS.md`).

## 2. Target pengguna

- Developer yang butuh CMS self-hosted, content-model fleksibel (blueprint/
  fieldset), tanpa vendor lock-in ke Statamic (yang berlisensi berbayar untuk
  fitur multi-site/tim).
- Tim kecil–menengah yang mengelola konten multi-bahasa/multi-site (lihat
  tabel `sites`, default site handle `indonesia`).

## 3. Konsep inti (dari skema yang sudah ada)

| Konsep                       | Tabel                                 | Deskripsi                                                                                                                                                                     |
| ---------------------------- | ------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Collection                   | `collections`                         | Jenis konten (mis. "blog", "pages"). Punya `handle`, `title`, `settings` JSON.                                                                                                |
| Entry                        | `entries`                             | Isi konten per collection. PK **UUID**. Punya `site`, `origin_id` (untuk localization), `published`, `slug`, `uri`, `order`, `blueprint`, `data` (JSON, isi field blueprint). |
| Taxonomy / Term              | `taxonomies`, `taxonomy_terms`        | Kategori/tag lintas collection.                                                                                                                                               |
| Blueprint                    | `blueprints`                          | Definisi struktur field untuk entry/term/dll (`namespace` + `handle`, `data` JSON = skema field).                                                                             |
| Fieldset                     | `fieldsets`                           | Kumpulan field yang bisa dipakai ulang lintas blueprint.                                                                                                                      |
| Global Set                   | `global_sets`, `global_set_variables` | Data global (mis. site settings), bisa per-locale.                                                                                                                            |
| Asset Container / Asset Meta | `asset_containers`, `assets_meta`     | Manajemen file/media, per disk storage.                                                                                                                                       |
| Form / Form Submission       | `forms`, `form_submissions`           | Form builder + penyimpanan submission.                                                                                                                                        |
| Navigation                   | `navigations`                         | Menu navigasi.                                                                                                                                                                |
| Tree                         | `trees`                               | Struktur hierarki/urutan untuk collection atau navigation (`type`: collection\|navigation).                                                                                   |
| Site                         | `sites`                               | Multi-site: handle, locale, lang, url, urutan.                                                                                                                                |
| Sitemap                      | `sitemaps`                            | Baris sitemap per entry/collection/site, untuk generate `sitemap.xml`.                                                                                                        |
| Redirect                     | `redirects`                           | Redirect manager (exact/prefix/regex, internal/eksternal).                                                                                                                    |
| User                         | `users`                               | Akun admin/editor. PK **UUID**. Auth via Fortify (login, register, 2FA, passkey).                                                                                             |

## 4. Yang SUDAH berjalan

- Autentikasi lengkap: register, login, verifikasi email, 2FA (TOTP + recovery
  code), passkey (WebAuthn), password confirmation — semua via Laravel
  Fortify + Livewire.
- `users.id` dan `entries.id`/`sitemaps.origin_id` bertipe **UUID**.
  Tabel lain (`collections`, `blueprints`, `fieldsets`, `taxonomies`,
  `forms`, `navigations`, `sites`, dst.) masih pakai **auto-increment id**
  dan direferensikan lintas tabel lewat **`handle` (string)**, bukan lewat
  id. Ini pola yang disengaja di skema — jangan diseragamkan ke UUID tanpa
  diminta.
- `form_submissions.id` unik: `decimal(14,4)` sebagai primary key (bukan
  bigint/uuid). Ini juga disengaja di skema asal — jangan diubah tanpa
  konfirmasi ke user, karena mempengaruhi cara ID submission dibuat.

## 5. Yang BELUM ada (jangan diasumsikan sudah ada)

- Model Eloquent untuk semua tabel CMS di atas (baru ada `User`; folder
  `Models/` tiap modul di `app/Modules/` masih kosong).
- Admin panel / control panel UI (CRUD collection, entry editor, blueprint
  builder drag-drop, dsb).
- Front-end rendering / templating engine untuk menampilkan entry ke publik
  (Statamic pakai Antlers/Blade — di project ini **belum diputuskan**).
- REST/GraphQL API untuk headless consumption.
- Sistem role & permission (baru ada 1 tipe user, tanpa role/policy).
- Search engine (Statamic pakai Algolia/local index — belum ada di sini).
- Live preview, revisions/versioning, workflow approval.
- CLI/artisan commands khusus CMS (generate blueprint, publish, dsb).

Semua poin di atas adalah **kemungkinan roadmap**, bukan komitmen. Jangan
implementasikan salah satunya sebelum diminta eksplisit oleh user.

## 6. Non-goals (secara eksplisit tidak ditiru dari Statamic)

- **Bukan flat-file storage.** Statamic asli menyimpan content sebagai
  `.yaml`/`.md` di git. Project ini sengaja pakai DB (MySQL + JSON column).
  Jangan usulkan/pindah ke flat-file kecuali diminta.
- **Bukan produk komersial/berlisensi.** Tidak perlu logic licensing,
  paywall fitur, atau Statamic Pro-style gating.
- Tidak mencoba 100% API-compatible dengan Statamic (`statamic/cms` package
  tidak dipakai) — ini implementasi ulang, bukan fork.

## 7. Tech stack (terkonfirmasi dari `composer.json` / `package.json`)

- PHP 8.4, Laravel 13.26.
- Livewire 4 + Livewire Flux (UI component) + Tailwind CSS v4 + Vite.
- Laravel Fortify (auth backend), termasuk 2FA & passkeys (`@laravel/passkeys`).
- Pest 5 untuk testing, Pint untuk formatting, Larastan untuk static analysis.
- MySQL sebagai database utama.

Jangan tambah dependency baru (composer/npm) tanpa persetujuan user — sesuai
aturan di `CLAUDE.md` project ini.
