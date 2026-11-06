# Citation Impact (Dimensions & OpenAlex) — plugin OJS 3.5

> **Unduh:** [`dist/citationImpact-2.5.0.tar.gz`](dist/citationImpact-2.5.0.tar.gz) — siap diunggah lewat *Upload A New Plugin*. Kode sumber ada di folder [`citationImpact/`](citationImpact/).

Plugin generik OJS 3.5 yang menambahkan halaman **Dampak & Sitasi** di dalam tema jurnal, berisi jumlah sitasi dari [Dimensions](https://www.dimensions.ai/) dan [OpenAlex](https://openalex.org/) untuk setiap artikel terbit yang memiliki DOI.

- Halaman publik di `/index.php/{jurnal}/impact`, tampil di dalam tema jurnal (header, menu, footer).
- Angka utama di bagian atas adalah **sitasi 3 tahun terakhir** (butir Dampak Ilmiah ARJUNA), lengkap dengan h-index dan i10-index 3 tahun serta persentase artikel 3 tahun yang tersitasi. Halaman juga menampilkan total sitasi sepanjang waktu, sitasi 2 tahun terakhir, artikel tersitasi, h-index, i10-index, grafik per tahun terbit (rentang 3 tahun ditandai), dan daftar artikel yang bisa dicari, difilter, dan diurutkan.
- Daftar artikel diambil langsung dari database OJS, jadi tidak perlu OAI maupun `config.inc.php`.
- Diperbarui otomatis setiap jam lewat penjadwal tugas OJS 3.5, sekitar 25 detik per putaran.
- Tombol **Perbarui sekarang** di pengaturan plugin untuk pengisian pertama.
- Jenis item menu **Citation Impact** di Navigation Menus.
- **Warna mengikuti tema** yang dipakai jurnal, termasuk tema gelap (lihat *Warna & tema*).
- Bahasa Indonesia dan Inggris mengikuti bahasa jurnal.
- **Dua sumber sitasi, ditampilkan terpisah**: semua angka utama memakai Dimensions (yang dipakai asesor ARJUNA). OpenAlex tampil di kotak dan kolom sendiri sebagai pembanding, dan tidak ikut dihitung.
- **Badge sitasi di halaman artikel**: jumlah sitasi, tautan ke Dimensions, dan daftar karya pengutip di OpenAlex.
- **Ekspor CSV** semua artikel untuk bukti akreditasi (butir Dampak Ilmiah ARJUNA), langsung bisa dibuka di Excel: `/index.php/{jurnal}/impact/csv`.
- **Daftar karya pengutip** per artikel (dari OpenAlex), bisa dibuka di daftar artikel dan diunduh sebagai CSV bukti: `/index.php/{jurnal}/impact/citing`.
- **Penanda sitasi dari jurnal sendiri (self-citation)**: karya pengutip dengan ISSN jurnal ini atau DOI jurnal ini diberi label, beserta jumlah dan persentasenya.
- **Rincian per terbitan** (volume/nomor): jumlah artikel, artikel tersitasi, dan sitasi per terbitan.
- **Grafik pertumbuhan sitasi**: total sitasi dicatat setiap bulan sejak plugin dipasang.
- **Blok sidebar** berisi sitasi 3 tahun terakhir, total sitasi, h-index, dan tautan ke halaman Impact.
- Data JSON tersedia di `/index.php/{jurnal}/impact/json`.

Versi 2.x ini khusus **OJS 3.5** (PHP 8.2+, MySQL/MariaDB atau PostgreSQL). Untuk OJS 3.3 gunakan versi 1.x.

## Perubahan dari versi OJS 3.3 (1.2.0)

- Kelas memakai namespace `APP\plugins\generic\citationImpact` dan berkas `.php` (bukan `.inc.php`); `index.php` dihapus.
- `HookRegistry` → `PKP\plugins\Hook`, `AppLocale` → `PKP\facades\Locale`, Capsule → `Illuminate\Support\Facades\DB`.
- Handler halaman dipasang lewat argumen `$handler` pada hook `LoadHandler` (OJS 3.5 tidak lagi menerima `HANDLER_CLASS`).
- DOI dibaca dari tabel `dois` (`publications.doi_id`), bukan dari `publication_settings` (`pub-id::doi`).
- Tugas terjadwal didaftarkan lewat `HasTaskScheduler::registerSchedules()` (penjadwal Laravel di OJS 3.5). `scheduledTasks.xml` dan hook Acron dihapus.
- Folder bahasa `locale/en` dan `locale/id` (bukan `en_US`, `id_ID`).
- Konstanta `ROUTE_PAGE`/`ROUTE_COMPONENT` diganti `Application::ROUTE_PAGE`/`ROUTE_COMPONENT`.
- Data sitasi yang sudah terkumpul di `{files_dir}/citationImpact/` tetap dipakai, jadi tidak perlu mengambil ulang setelah upgrade OJS.

## Pemasangan

1. Masuk sebagai Journal Manager atau Administrator.
2. Buka **Settings → Website → Plugins**, lalu klik **Upload A New Plugin**.
3. Pilih `citationImpact-2.5.0.tar.gz`, klik **Save**, dan tunggu sampai muncul "Successfully installed".
4. Di tab **Installed Plugins**, cari **Citation Impact (Dimensions)** dan centang untuk mengaktifkan.
5. Klik panah di sebelah nama plugin → **Settings**:
   - periksa **Email kontak** (diisi otomatis dari email kontak jurnal);
   - klik **Perbarui sekarang**, lalu biarkan jendela terbuka sampai muncul "Selesai". Untuk ±2.700 artikel butuh sekitar 25 menit.
6. Tambahkan ke menu: **Settings → Website → Setup → Navigation Menus → Add Item**, pilih tipe **Citation Impact**, isi judulnya, lalu seret item itu ke **Primary Navigation Menu**.

Kalau server tidak mengizinkan unggah plugin, ekstrak arsip itu dan unggah folder `citationImpact` ke `plugins/generic/` lewat File Manager, lalu jalankan dari root OJS:

```
php lib/pkp/tools/installPluginVersion.php plugins/generic/citationImpact/version.xml
```

## Pembaruan otomatis

Plugin mendaftarkan tugas terjadwal setiap jam. Di OJS 3.5 tugas ini dijalankan oleh penjadwal bawaan:

- **Penjadwal berbasis web** (bawaan, `task_runner = On` di bagian `[schedule]` `config.inc.php`): dijalankan di akhir permintaan halaman saat ada pengunjung. Tidak perlu pengaturan apa pun.
- **Cron server** (disarankan untuk jurnal besar): matikan `task_runner` dan tambahkan cron setiap menit:

```
* * * * * php /path/ke/ojs/lib/pkp/tools/scheduler.php run >> /dev/null 2>&1
```

Setiap artikel dicek ulang setelah jumlah hari di pengaturan (bawaan 7 hari). Kalau Dimensions membalas 429 (terlalu banyak permintaan), plugin berhenti dan jeda 15 menit; angka lama tetap ditampilkan.

## Sumber sitasi

| Sumber | Kelebihan | Catatan |
| --- | --- | --- |
| Dimensions | Ada FCR (Field Citation Ratio) | Satu permintaan per DOI; sering belum mengindeks jurnal yang masih muda |
| OpenAlex | Cakupan jurnal Crossref lokal lebih luas, data CC0, 50 DOI per permintaan, ada daftar karya pengutip | Tanpa API key hanya dapat jatah gratis harian kecil yang dipakai bersama semua situs di IP server yang sama |

Pilihan **Sumber sitasi** di pengaturan:

| Pilihan | Angka utama (ringkasan, indeks, grafik, daftar) | OpenAlex |
| --- | --- | --- |
| **Dimensions (utama) + OpenAlex sebagai pembanding** (bawaan, disarankan) | Dimensions saja, sama dengan yang dihitung asesor ARJUNA | Kotak "OpenAlex (pembanding)", kolom OpenAlex di daftar, dan chip di badge; tidak pernah dijumlahkan |
| Gabungan | Angka tertinggi dari Dimensions dan OpenAlex per artikel | Rincian per sumber di bawah tiap artikel |
| Hanya Dimensions / Hanya OpenAlex | Satu sumber | – |

Untuk keperluan akreditasi gunakan pilihan bawaan, karena angka utamanya sama persis dengan angka Dimensions.

**API key OpenAlex (disarankan):** daftar gratis di https://openalex.org/settings/api, lalu tempel key-nya di pengaturan plugin. Tanpa key, kalau jatah harian IP server habis, OpenAlex dijeda sampai jatahnya pulih. Dimensions tetap jalan dan angka lama tetap tampil.

## Badge di halaman artikel

Badge muncul di kolom detail artikel (tema Default dan tema lain yang memakai hook `Templates::Article::Details`), hanya untuk artikel yang punya minimal satu sitasi. Datanya diambil dari cache plugin, jadi tidak ada permintaan API tambahan saat halaman dibuka. Badge bisa dimatikan di pengaturan.

## Karya pengutip dan self-citation

Untuk setiap artikel yang punya sitasi di OpenAlex, plugin mengambil daftar karya pengutipnya. Satu permintaan bisa mencakup sampai 25 artikel, dan daftarnya diperbarui saat jumlah sitasinya berubah atau setelah interval cek ulang.
- Di daftar artikel, klik **"N karya pengutip (OpenAlex)"** untuk melihat judul, penulis, jurnal, dan tahun setiap pengutip.
- **Unduh karya pengutip (CSV)** menghasilkan satu baris per pasangan artikel–pengutip. Berguna kalau asesor meminta contoh artikel pengutip.
- Karya pengutip diberi label **Sitasi jurnal sendiri** kalau ISSN sumbernya sama dengan ISSN jurnal ini (diambil dari Settings → Journal), atau DOI-nya milik artikel jurnal ini. Jumlah dan persentasenya ditampilkan di bawah ringkasan.

Dimensions Metrics API tidak menyediakan daftar karya pengutip, jadi daftar dan penanda self-citation selalu berasal dari OpenAlex.

## Per terbitan dan pertumbuhan sitasi

Kotak grafik punya empat tab: per tahun terbit, **per terbitan**, **pertumbuhan sitasi**, dan distribusi.
- **Per terbitan:** memakai label terbitan OJS (mis. "Vol. 2 No. 1 (2025)").
- **Pertumbuhan sitasi:** dicatat sekali sebulan oleh tugas terjadwal (nilai bulan berjalan diperbarui setiap kali data disegarkan). Grafiknya muncul setelah ada data dua bulan, jadi pasang plugin sedini mungkin.

## Blok sidebar

Buka **Settings → Website → Appearance → Setup → Sidebar**, lalu seret **Blok Dampak Sitasi** ke daftar sidebar dan simpan. Blok membaca ringkasan yang disimpan saat penyegaran terakhir, jadi tidak memperlambat halaman.

## Ekspor CSV

Tombol **Unduh CSV** ada di bawah daftar artikel dan di pengaturan plugin. Isinya semua artikel ber-DOI (tidak hanya yang tersitasi):
- judul, penulis, tahun, tanggal terbit, DOI, URL artikel;
- jumlah sitasi (dan per sumber), sitasi 2 tahun terakhir, FCR;
- penanda apakah artikel terbit dalam rentang "3 tahun terakhir";
- tautan Dimensions dan daftar karya pengutip di OpenAlex.

Untuk bahasa Indonesia pemisahnya `;` dengan koma desimal (sesuai Excel berbahasa Indonesia), untuk bahasa lain `,`. Kalau perlu pemisah tertentu, tambahkan `?sep=comma` atau `?sep=semicolon` di akhir alamat.

## Pengaturan

| Pengaturan | Bawaan | Keterangan |
| --- | --- | --- |
| Sumber sitasi | Dimensions + OpenAlex | atau hanya salah satu |
| API key OpenAlex | kosong | opsional, gratis; memberi jurnal jatah harian sendiri |
| Badge di halaman artikel | aktif | tampil untuk artikel yang tersitasi |
| Alamat halaman | `impact` | halaman tampil di `/index.php/{jurnal}/{alamat}` |
| Daftar artikel | hanya yang tersitasi | atau semua artikel |
| Rentang "3 tahun terakhir" | tahun berjalan + 2 tahun sebelumnya | atau 3 tahun penuh sebelumnya; sesuaikan dengan asesor |
| Cek ulang tiap (hari) | 7 | 1–90 |
| Email kontak | email kontak jurnal | dikirim ke Dimensions sebagai identitas |

Data tersimpan di `{files_dir}/citationImpact/`, di luar folder web. Plugin tidak membuat tabel baru dan tidak mengubah data OJS.

## Warna & tema

Box, grafik, donut, tab, dan bar di halaman ini mengambil warnanya dari tema yang aktif, apa pun temanya. Urutan sumber warnanya:

1. **Warna aksen** di pengaturan plugin, kalau diisi (misalnya `#1e6292`).
2. **Opsi warna tema** di *Settings → Website → Appearance*. Contohnya *Colour* pada tema Default dan turunannya, atau opsi warna utama/brand di tema lain. Warna latar dan teks tidak dipakai sebagai aksen.
3. **Deteksi otomatis di browser** untuk tema yang tidak punya opsi warna. Warna dibaca dari tautan, header/menu, atau tombol tema seperti yang dilihat pengunjung.
4. Kalau semuanya tidak tersedia, dipakai biru `#2a78d6`.

Dari satu warna aksen itu plugin menurunkan latar lembut, warna teks aksen, dan gradasi grafik. Kontrasnya dijaga: warna yang terlalu terang, misalnya kuning, digelapkan secukupnya supaya tetap terbaca. Warna teks dan latar box juga mengikuti tema, jadi di tema berlatar gelap box ikut gelap.

## Izin Dimensions

Metrics API Dimensions gratis untuk penggunaan **non-komersial**. Institusi akademik wajib mendaftar, dan pengelola situs jurnal diminta menghubungi **publishing@digital-science.com** sebelum menampilkan data secara publik. Syarat lengkap: https://www.dimensions.ai/policies-terms-metrics/

## Masalah umum

- **Halaman 404.** Pastikan plugin aktif untuk jurnal itu, dan alamat halaman tidak bentrok dengan Static Page yang memakai path sama.
- **"Data sitasi belum tersedia".** Klik Perbarui sekarang di pengaturan plugin.
- **0 artikel.** Artikel belum memiliki DOI. Di OJS 3.5 aktifkan DOI di **Settings → Distribution → DOIs**, lalu tetapkan/daftarkan DOI artikel di menu **DOIs**.
- **Tombol Perbarui berhenti dengan pesan error.** Server mungkin tidak bisa menghubungi `metrics-api.dimensions.ai`, misalnya karena diblok firewall. Kalau server memakai proxy, isi bagian `[proxy]` di `config.inc.php`, karena plugin memakai klien HTTP bawaan OJS.
- **Tidak diperbarui otomatis.** Pastikan `task_runner = On` di `config.inc.php` atau cron `lib/pkp/tools/scheduler.php run` berjalan.

## Lisensi

GNU GPL v3, sama seperti OJS.
