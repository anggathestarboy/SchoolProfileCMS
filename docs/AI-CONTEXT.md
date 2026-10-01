# AI Context — DraftCMS

Ringkasan kondisi kode saat ini untuk AI agent, supaya tidak perlu
menebak-nebak arsitektur. Baca ini + `docs/PRD.md` sebelum mengerjakan task
apa pun di luar bugfix kecil.

## Struktur direktori nyata (bukan asumsi Statamic)

```
app/
  Actions/Fortify/     — action registrasi/update profil (CreateNewUser, dll)
  Concerns/
  Console/Commands/
  Http/Controllers/
  Livewire/            — komponen Livewire untuk Settings/Auth (BUKAN modul CMS)
  Livewire/Settings/
  Models/              — HANYA User.php (identity/auth, di luar sistem modul)
  Modules/             — SEMUA fitur CMS hidup di sini, 1 folder per modul.
                          Lihat bagian "Sistem modul CMS" di bawah.
  Providers/
database/migrations/    — SEMUA migration app ini hidup di sini, termasuk
                           tabel milik modul (mis. `entries`, `taxonomy_terms`,
                           `roles`). Modul TIDAK punya folder
                           `database/migrations/` sendiri lagi — lihat aturan
                           di bawah.
routes/
  web.php               — route `/` dan (kalau cms.enabled) `dashboard`.
                           Route per-modul TIDAK didaftarkan di sini, lihat
                           di bawah.
  settings.php
resources/views/        — Blade + Livewire views level aplikasi (Flux UI,
                           layout, halaman settings/auth). Modul CMS punya
                           resources/views sendiri, lihat di bawah.
```

`app/Models/User.php` sengaja tetap di `app/Models/` (bukan modul) karena
dia infrastruktur identity/auth yang dipakai semua modul, bukan fitur
konten. Jangan pindahkan `User` ke dalam sebuah modul.

## Sistem modul CMS

Setiap fitur CMS (collection, blueprint, media, dst.) adalah **modul
mandiri** di `app/Modules/{Nama}/`, supaya kerja paralel banyak dev/AI tidak
saling tabrakan file. Ini keputusan arsitektur yang sudah dibuat dan
di-scaffold — ikuti pola ini untuk semua kerja CMS berikutnya, jangan
menciptakan pola baru.

### Wireframe folder per modul

```
app/Modules/{Nama}/
  Models/                  — Eloquent model milik modul ini
  Http/Controllers/        — controller (kalau modul butuh, di luar Livewire)
  Livewire/                — komponen Livewire (CP UI) milik modul ini
  Actions/                 — action class satu-tugas (pola app/Actions/Fortify)
  Policies/                — authorization policy milik modul ini
  routes.php               — route modul ini SAJA (lihat isi provider di bawah)
  resources/views/         — Blade view modul ini, di-load lewat view
                              namespace sendiri (mis. `media::index`)
  {Nama}ServiceProvider.php
```

`{Nama}ServiceProvider` sudah di-generate untuk semua modul dan didaftarkan
di `bootstrap/providers.php`. Isinya (jangan diubah polanya tanpa alasan
kuat):

```php
public function boot(): void
{
    $this->loadViewsFrom(__DIR__.'/resources/views', '{slug}');

    Route::middleware(['web', 'auth', 'verified'])
        ->prefix(config('cms.route'))
        ->name('{slug}.')
        ->group(__DIR__.'/routes.php');
}
```

Artinya: `routes.php` di dalam modul **tidak boleh** panggil
`->prefix()`/`->middleware()`/`->name()` sendiri lagi — itu sudah dipasang
provider. Semua route modul otomatis ikut prefix CMS (`config('cms.route')`,
lihat bagian "Konfigurasi route CMS") dan otomatis punya nama route
`{slug}.{nama-route}` (mis. route `index` di modul Media → `media.index`).

### Daftar modul & tabel yang dimiliki

| Modul | Slug | Tabel yang dimiliki | Catatan |
|---|---|---|---|
| Collections | `collections` | `collections`, `entries` | Entry milik collection lewat kolom `collection` (handle), bukan FK id. |
| Taxonomies | `taxonomies` | `taxonomies`, `taxonomy_terms` | |
| Blueprints | `blueprints` | `blueprints`, `fieldsets` | Modul ini "dipakai" hampir semua modul lain (setiap konten punya blueprint) — modul lain boleh depend ke sini. **Pengecualian yang disetujui:** Blueprints juga boleh depend ke `App\Modules\Collections\Models\Collection` (read-only, hanya untuk populate dropdown "Namespace" di form Blueprint dari daftar collection yang ada — mis. `collections.blog`). Selain pengecualian ini, Blueprints tetap tidak boleh depend ke modul konten manapun. |
| Media | `media` | `asset_containers`, `assets_meta` | "Media library" yang diminta user. |
| Globals | `globals` | `global_sets`, `global_set_variables` | |
| Forms | `forms` | `forms`, `form_submissions` | |
| Navigation | `navigation` | `navigations` | Struktur pohon menu didelegasikan ke modul Structure. |
| Structure | `structure` | `trees` | Modul kecil khusus untuk hierarki/urutan (`trees.type`: `collection`\|`navigation`), dipakai Collections & Navigation. Jangan duplikasi logic tree di modul lain. |
| Sites | `sites` | `sites` | Multi-site config. |
| Sitemap | `sitemap` | `sitemaps` | |
| Redirects | `redirects` | `redirects` | |

`User`/auth tetap di luar sistem modul (lihat bagian struktur direktori di
atas).

### Aturan wajib kalau menambah kode ke sebuah modul

1. **Satu tabel = satu modul pemilik**, sesuai tabel di atas. Model Eloquent
   untuk tabel itu HANYA boleh didefinisikan di modul pemiliknya.
2. **Jangan `use` model/class dari modul lain langsung ke internal-nya**
   kalau bisa dihindari — modul lain boleh depend ke model publik modul lain
   (mis. Collections boleh query `App\Modules\Blueprints\Models\Blueprint`),
   tapi jangan menaruh business logic milik modul B di dalam modul A. Kalau
   dua modul perlu berbagi logic, taruh logic itu di modul yang paling
   relevan (contoh: Structure untuk tree) — jangan membuat modul baru tanpa
   didiskusikan dulu ke user, dan jangan taruh di `app/Support` atau
   sejenisnya tanpa persetujuan (belum ada folder itu).
3. **Semua migration — punya modul manapun — ditaruh di `database/migrations/`
   root**, bukan di folder modul. Tidak ada `app/Modules/{Nama}/database/`
   lagi; setiap modul provider TIDAK memanggil `loadMigrationsFrom()`.
   Sebelum app ini live di production (belum ada data nyata yang harus
   dijaga), kalau perlu ubah skema sebuah tabel, **edit langsung migration
   `create_{tabel}_table` yang sudah ada** (lalu `migrate:fresh`) — jangan
   bikin migration baru bertipe `add_x_to_y_table`/`change_x_on_y_table`,
   supaya riwayat migration tetap bersih (hanya migration `create_*`).
   Begitu sudah ada instalasi production dengan data nyata, konvensi ini
   berubah: migration baru untuk kolom baru ditaruh sebagai file baru di
   `database/migrations/` (bukan edit migration lama), karena `migrate:fresh`
   sudah tidak aman lagi saat itu.
4. **Route baru untuk modul ditaruh di `app/Modules/{Nama}/routes.php`**,
   bukan di `routes/web.php`. `routes/web.php` hanya untuk route level
   aplikasi (home, dashboard shell) yang bukan milik modul manapun.
5. **View Blade modul ditaruh di `app/Modules/{Nama}/resources/views/`**,
   diakses lewat namespace `{slug}::nama-view` (mis. `media::index`), bukan
   ditaruh di `resources/views/` root.
6. Kalau task minta fitur yang **belum ada modulnya** di tabel di atas
   (fitur benar-benar baru, bukan salah satu dari 11 modul ini) → **tanya
   user dulu** sebelum membuat modul baru. Jangan menebak nama/struktur
   modul baru sendirian.

## Konfigurasi route CMS

`config/cms.php` mengatur panel CMS (mirip `cp.enabled`/`cp.route` di
Statamic):

- `CMS_ENABLED` (default `true`) — kalau `false`, route `dashboard` di
  `routes/web.php` tidak didaftarkan. Route Fortify (login/register/dll)
  tetap terdaftar — CMS_ENABLED belum mematikan itu, cek `config/fortify.php`
  kalau butuh disable total.
- `CMS_ROUTE` (default `admin-panel`) — prefix URL untuk seluruh panel.
  Dipakai di dua tempat yang **harus tetap sinkron**: `routes/web.php`
  (`Route::prefix(config('cms.route'))` untuk `dashboard`) dan
  `config/fortify.php` (`'prefix'` dan `'home'`, dibaca langsung dari
  `env('CMS_ROUTE', ...)` bukan dari `config('cms.route')`, supaya tidak
  bergantung urutan load file config). Kalau menambah route CMS baru,
  taruh di dalam group `Route::prefix(config('cms.route'))` yang sama di
  `routes/web.php`, jangan hardcode `/dashboard` atau path lain di kode/JS —
  selalu pakai `route('dashboard')` dsb.

## Model & auth yang sudah ada

- `App\Models\User` — primary key **UUID** via trait `HasUuids`
  (`newUniqueId()` → `Str::uuid7()`). `$incrementing`/`$keyType` diatur
  otomatis oleh trait, jangan override manual.
- Auth stack: Laravel Fortify mengatur route & controller
  (register/login/2FA/passkey/password-confirm). Custom action ada di
  `app/Actions/Fortify/`. UI login/register pakai Livewire component,
  bukan Blade form biasa — cek `app/Livewire/` sebelum bikin form auth baru.
- `sessions.user_id` sudah bertipe `char(36)` (UUID), sudah disamakan dengan
  `users.id` — **jangan** balikin ke `bigint`/`foreignId`.

## Pola skema CMS yang harus diikuti kalau menambah tabel/model baru

1. Tabel referensi (`collections`, `taxonomies`, `blueprints`, `forms`,
   `sites`, dst.) pakai `id()` auto-increment biasa + `handle` (string,
   unique) sebagai kunci yang dipakai untuk foreign key lintas tabel — bukan
   `id` numerik. Contoh: `entries.collection` adalah string yang mereferensi
   `collections.handle`, bukan `collections.id`.
2. Tabel "isi konten" (`entries`) pakai **UUID** sebagai primary key.
3. Data fleksibel/schema-less (isi field blueprint, settings) disimpan di
   kolom `json` bernama `data` atau `settings` — jangan bikin kolom terpisah
   per field custom.
4. `created_at`/`updated_at` di migration CMS ini didefinisikan manual
   sebagai `timestamp(...)->nullable()`, **bukan** `$table->timestamps()`
   bawaan Laravel. Ikuti pola yang sama di migration baru pada domain yang
   sama supaya konsisten (kecuali user minta ubah).
5. Default site handle yang dipakai di skema adalah `"indonesia"` (lihat
   default `sitemaps.site`). Jangan hardcode locale/site lain sebagai
   default tanpa konfirmasi.

## Yang jangan diasumsikan tersedia

Lihat `docs/PRD.md` §5. Yang paling sering bikin AI ngarang: jangan
berasumsi ada admin panel, template engine, API layer, role/permission
system, atau search index — semua itu **belum dibangun**.

## Sumber kebenaran kalau ragu

1. `database/migrations/` — schema aktual (paling akurat, cek sebelum
   membuat model/migration baru).
2. `docs/PRD.md` — scope & niat produk.
3. `docs/AI-CONSTRAINTS.md` — batasan perilaku AI.
4. `CLAUDE.md` (root) — konvensi coding Laravel/Boost project ini.
5. Kalau masih ragu soal keputusan produk (nama fitur, prioritas, desain
   UI) → **tanya user**, jangan menebak.
