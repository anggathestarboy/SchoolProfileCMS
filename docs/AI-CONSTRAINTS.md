# Batasan AI — DraftCMS

Aturan keras untuk AI agent (Claude Code atau lainnya) yang bekerja di
project ini. Tujuannya satu: **jangan menghayal fitur, arsitektur, atau
keputusan produk yang belum ada.**

## Larangan keras

0. **Semua fitur CMS WAJIB masuk sistem modul di `app/Modules/{Nama}/`.**
   Lihat `docs/AI-CONTEXT.md` bagian "Sistem modul CMS" untuk wireframe
   folder, daftar 11 modul yang sudah di-scaffold, dan tabel yang dimiliki
   tiap modul. Jangan taruh model/controller/Livewire component fitur CMS di
   `app/Models/`, `app/Http/Controllers/`, atau `app/Livewire/` langsung —
   folder-folder itu sekarang khusus untuk hal di luar modul (auth/User,
   settings, layout aplikasi). Jangan bikin pola modul baru yang beda
   (mis. nwidart/laravel-modules atau skema folder lain) — sudah diputuskan
   hand-roll PSR-4 di bawah `App\Modules\`.
1. **Jangan membuat model, controller, migration, atau tabel baru untuk
   fitur CMS (collection, entry, blueprint, dsb.) tanpa diminta eksplisit.**
   Skema di `database/migrations/` sudah final untuk fitur-fitur itu —
   tugasnya membangun model/logic **di atas** skema itu, bukan mendesain
   ulang skemanya, kecuali user secara eksplisit minta ubah skema. Model baru
   ditaruh di modul pemilik tabelnya (lihat tabel modul di
   `docs/AI-CONTEXT.md`), bukan di `app/Models/`.
2. **Jangan menebak arsitektur ala Statamic asli** (flat-file, Antlers
   templating, Statamic CP routes, `statamic/cms` package, licensing/Pro
   tier). Project ini implementasi ulang berbasis DB — lihat
   `docs/PRD.md` §6 (Non-goals).
3. **Jangan menambah dependency** (composer require / npm install) tanpa
   persetujuan user, walau dependency itu "biasa dipakai CMS" (mis. search
   engine, image processing, markdown parser). Tanya dulu.
4. **Jangan bikin folder dasar baru di luar yang sudah ada** (`app/Models/`,
   `app/Http/Controllers/`, `app/Livewire/`, dan `app/Modules/{Nama}/` untuk
   fitur CMS) tanpa persetujuan. `app/Modules/` sendiri **sudah disetujui**
   dan berisi 11 modul yang sudah di-scaffold (lihat `docs/AI-CONTEXT.md`) —
   itu bukan folder baru yang perlu ditanya lagi, tapi menambah **modul ke-12
   dst.** (folder baru di dalam `app/Modules/`) tetap butuh konfirmasi user
   dulu (lihat larangan #0).
5. **Jangan ubah pola primary key yang sudah ditetapkan** (UUID untuk
   `users`/`entries`, auto-increment+handle untuk tabel referensi,
   `decimal(14,4)` untuk `form_submissions.id`) tanpa konfirmasi eksplisit —
   ini pernah jadi sumber bug nyata (lihat riwayat: `users.id` diubah ke UUID
   tapi `sessions.user_id` ketinggalan bigint, bikin login gagal).
6. **Jangan berasumsi ada admin panel / API / role system** yang belum
   dibangun. Kalau task menyinggung salah satu dari itu, konfirmasi dulu ke
   user apa yang sudah ada vs yang perlu dibangun dari nol.
7. **Jangan buat dokumentasi tambahan** (README, ADR, docs lain) di luar
   yang diminta — sesuai aturan umum `CLAUDE.md` root project.

## Kalau ada task yang scope-nya di luar dokumen ini

- Kalau task jelas merupakan bugfix/perbaikan kecil pada fitur yang sudah
  ada → langsung kerjakan, tidak perlu tanya.
- Kalau task adalah **fitur baru** yang belum tercakup di `docs/PRD.md`
  (mis. "buatkan search", "buatkan API publik") → **tanya dulu** ke user:
  scope-nya seperti apa, prioritasnya di mana, sebelum mulai coding. Jangan
  langsung berasumsi mengikuti pola Statamic asli.
- Kalau ragu apakah sesuatu "sudah ada" atau belum → cek
  `database/migrations/`, `app/Models/`, `routes/` dulu (grep/read), baru
  tanya user kalau masih ambigu.

## Update dokumen ini

`docs/PRD.md`, `docs/AI-CONTEXT.md`, dan file ini **harus di-update manual
oleh user atau atas persetujuan user** setiap kali scope produk berubah
signifikan (fitur baru masuk roadmap, keputusan arsitektur besar seperti
templating engine dipilih, dsb). AI tidak boleh mengubah scope di sini
sendirian hanya karena baru saja mengerjakan sebuah fitur.
