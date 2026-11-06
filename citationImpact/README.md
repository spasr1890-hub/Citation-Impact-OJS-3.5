# Citation Impact (Dimensions) — plugin OJS 3.5

Plugin generik OJS 3.5 yang menambahkan halaman **Dampak & Sitasi** di dalam tema jurnal, berisi jumlah sitasi dari [Dimensions](https://www.dimensions.ai/) untuk setiap artikel terbit yang memiliki DOI.

- Halaman publik di `/index.php/{jurnal}/impact`, tampil di dalam tema jurnal (header, menu, footer).
- Menampilkan total sitasi, **sitasi 3 tahun terakhir** (butir Dampak Ilmiah ARJUNA), sitasi 2 tahun terakhir, artikel tersitasi, h-index, i10-index, grafik per tahun terbit (rentang 3 tahun ditandai), dan daftar artikel yang bisa dicari, difilter, dan diurutkan.
- Daftar artikel diambil langsung dari database OJS, jadi tidak perlu OAI maupun `config.inc.php`.
- Diperbarui otomatis setiap jam lewat penjadwal tugas OJS 3.5, sekitar 25 detik per putaran.
- Tombol **Perbarui sekarang** di pengaturan plugin untuk pengisian pertama.
- Jenis item menu **Citation Impact** di Navigation Menus.
- **Warna mengikuti tema** yang dipakai jurnal, termasuk tema gelap (lihat *Warna & tema*).
- Bahasa Indonesia dan Inggris mengikuti bahasa jurnal.
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
3. Pilih `citationImpact-2.1.0.tar.gz`, klik **Save**, dan tunggu sampai muncul "Successfully installed".
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

## Pengaturan

| Pengaturan | Bawaan | Keterangan |
| --- | --- | --- |
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
