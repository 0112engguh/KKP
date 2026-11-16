<?php
// orangtua/nilai.php
// Nilai TKA anak (tanpa pembahasan soal) dan grafik perkembangannya.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
[$anak, $semua_anak] = pilih_anak();

$judul = 'Nilai TKA Anak';
require __DIR__ . '/../includes/header.php';

if (!$anak) {
    echo '<div class="card kosong">Belum ada anak yang terhubung dengan akun Anda.</div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}
finalisasi_kadaluarsa(null, (int) $anak['id']);
$riwayat = riwayat_nilai((int) $anak['id']);
$urut = array_reverse($riwayat);
// Grafik memakai data lengkap, tabel dipaginasi
[$riwayat_tampil, $halaman, $hal_akhir] = paginasi_array($riwayat);
echo html_pilih_anak($semua_anak, $anak);
?>
<section class="card">
    <h2>Perkembangan Nilai <?= e($anak['nama']) ?></h2>
    <div class="grafik" data-grafik="garis" data-maks="100"
         data-labels='<?= e(json_encode(array_map(fn($r) => date('d/m', strtotime($r['waktu_selesai'])), $urut))) ?>'
         data-values='<?= e(json_encode(array_map(fn($r) => (float) $r['nilai'], $urut))) ?>'></div>
    <?php if ($riwayat): ?><p class="teks-tengah">Rata-rata nilai: <strong><?= angka(rata_rata_nilai($riwayat)) ?></strong></p><?php endif; ?>
</section>
<section class="card">
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>Tanggal</th><th>Ujian</th><th>Mapel</th><th class="angka">Benar</th><th class="angka">Salah</th><th class="angka">Nilai</th></tr></thead>
            <tbody>
            <?php foreach ($riwayat_tampil as $r): ?>
                <tr><td><?= e(tgl_indo($r['waktu_selesai'])) ?></td><td><strong><?= e($r['nama_ujian']) ?></strong></td><td><?= e($r['nama_mapel']) ?></td>
                    <td class="angka"><?= (int) $r['jumlah_benar'] ?></td><td class="angka"><?= (int) $r['jumlah_salah'] ?></td>
                    <td class="angka"><?= pill(angka($r['nilai']), warna_nilai((float) $r['nilai'])) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$riwayat): ?><tr><td colspan="6" class="kosong">Belum ada nilai.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= paginasi_tautan($halaman, $hal_akhir) ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
