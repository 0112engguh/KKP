<?php
// orangtua/dashboard.php
// Dashboard orang tua: informasi anak, kehadiran, nilai TKA, progress kebiasaan, dan grafik perkembangan.
// Orang tua hanya dapat melihat anak yang terhubung dengan akunnya (lihat pilih_anak()).

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
[$anak, $semua_anak] = pilih_anak();

$judul = 'Dashboard Orang Tua';
require __DIR__ . '/../includes/header.php';

if (!$anak) {
    echo '<div class="card kosong">Belum ada anak yang terhubung dengan akun Anda. Hubungi wali kelas.</div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}
$sid = (int) $anak['id'];
$sem = semester_aktif();
$absen = rekap_absensi_siswa($sid, $sem['dari'], $sem['sampai']);
$riwayat = riwayat_nilai($sid);
$poin = total_poin($sid);
$badge = daftar_badge($poin);
$diraih = array_values(array_filter($badge['semua'], fn($b) => $b['diraih']));
$progress = progress_kebiasaan($sid, (int) $anak['kelas_id'], $sem['dari'], $sem['sampai']);
$harian = kebiasaan_per_hari($sid, 14);
$urut = array_reverse($riwayat);

echo html_pilih_anak($semua_anak, $anak);
?>
<section class="card">
    <h2><?= e($anak['nama']) ?></h2>
    <p>Kelas <strong><?= e($anak['nama_kelas']) ?></strong> &middot; NISN <?= e($anak['nisn']) ?> &middot; <?= $anak['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></p>
</section>

<div class="grid-stat">
    <div class="stat hijau"><div class="stat-label">Kehadiran</div><div class="stat-nilai"><?= angka($absen['persen']) ?>%</div><div class="stat-catatan"><?= e($sem['label']) ?></div></div>
    <div class="stat"><div class="stat-label">Rata-rata Nilai TKA</div><div class="stat-nilai"><?= $riwayat ? angka(rata_rata_nilai($riwayat)) : '-' ?></div><div class="stat-catatan"><?= count($riwayat) ?> ujian</div></div>
    <div class="stat hijau"><div class="stat-label">Progress Kebiasaan</div><div class="stat-nilai"><?= angka($progress['progress']) ?>%</div><div class="stat-catatan">semester ini</div></div>
    <div class="stat kuning"><div class="stat-label">Total Poin</div><div class="stat-nilai"><i class="bi bi-star-fill"></i> <?= (int) $poin ?></div><div class="stat-catatan"><?= $diraih ? ikon_badge(end($diraih)['ikon']) . ' ' . e(end($diraih)['nama_badge']) : 'Belum ada badge' ?></div></div>
</div>

<div class="grid-2">
    <section class="card">
        <h2>Perkembangan Nilai TKA</h2>
        <div class="grafik" data-grafik="garis" data-maks="100"
             data-labels='<?= e(json_encode(array_map(fn($r) => date('d/m', strtotime($r['waktu_selesai'])), $urut))) ?>'
             data-values='<?= e(json_encode(array_map(fn($r) => (float) $r['nilai'], $urut))) ?>'></div>
    </section>
    <section class="card">
        <h2>Kebiasaan 14 Hari Terakhir</h2>
        <div class="grafik" data-grafik="bar" data-maks="7" data-warna='["#16a34a"]'
             data-labels='<?= e(json_encode(array_map(fn($r) => date('d/m', strtotime($r['tanggal'])), $harian))) ?>'
             data-values='<?= e(json_encode(array_map(fn($r) => (int) $r['jumlah'], $harian))) ?>'></div>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
