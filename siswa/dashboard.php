<?php
// siswa/dashboard.php
// Dashboard siswa: kehadiran, ujian aktif, nilai TKA terbaru, progress 7 kebiasaan, poin, dan badge.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['siswa']);
$siswa = siswa_login();
if (!$siswa) { flash('danger', 'Data siswa tidak ditemukan.'); redirect(url('auth/logout.php')); }
$sid = (int) $siswa['id'];

$sem = semester_aktif();
$absen = rekap_absensi_siswa($sid, $sem['dari'], $sem['sampai']);
$ujian = ujian_untuk_siswa($siswa);
$ujian_aktif = array_filter($ujian, fn($u) => in_array($u['kondisi'], ['tersedia', 'berlangsung'], true));
$riwayat = riwayat_nilai($sid);
$poin = total_poin($sid);
$badge = daftar_badge($poin);
$diraih = array_values(array_filter($badge['semua'], fn($b) => $b['diraih']));

$st = db()->prepare("SELECT j.status, (SELECT COALESCE(SUM(selesai), 0) FROM detail_jurnal_kebiasaan WHERE jurnal_id = j.id) AS jumlah
                     FROM jurnal_kebiasaan j WHERE j.siswa_id = :s AND j.tanggal = :t");
$st->execute(['s' => $sid, 't' => date('Y-m-d')]);
$jurnal_hari_ini = $st->fetch();
$kebiasaan_hari = kebiasaan_per_hari($sid, 14);
$progress = progress_kebiasaan($sid, (int) $siswa['kelas_id'], $sem['dari'], $sem['sampai']);

$judul = 'Dashboard Siswa';
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <h2>Halo, <?= e($siswa['nama']) ?>! <i class="bi bi-emoji-smile"></i></h2>
    <p>Kelas <strong><?= e($siswa['nama_kelas']) ?></strong> &middot; NISN <?= e($siswa['nisn']) ?></p>
</section>

<div class="grid-stat">
    <div class="stat hijau"><div class="stat-label">Kehadiran</div><div class="stat-nilai"><?= angka($absen['persen']) ?>%</div><div class="stat-catatan"><?= e($sem['label']) ?></div></div>
    <div class="stat kuning"><div class="stat-label">Ujian Aktif</div><div class="stat-nilai"><?= count($ujian_aktif) ?></div><div class="stat-catatan"><a href="<?= e(url('siswa/ujian.php')) ?>">Lihat ujian</a></div></div>
    <div class="stat"><div class="stat-label">Nilai TKA Terbaru</div><div class="stat-nilai"><?= $riwayat ? angka($riwayat[0]['nilai']) : '-' ?></div><div class="stat-catatan"><?= $riwayat ? e($riwayat[0]['nama_ujian']) : 'Belum ada nilai' ?></div></div>
    <div class="stat hijau"><div class="stat-label">Total Poin</div><div class="stat-nilai"><i class="bi bi-star-fill"></i> <?= $poin ?></div></div>
    <div class="stat kuning"><div class="stat-label">Badge Terbaru</div><div class="stat-nilai" style="font-size:1.2rem"><?= $diraih ? ikon_badge(end($diraih)['ikon']) . ' ' . e(end($diraih)['nama_badge']) : 'Belum ada' ?></div></div>
</div>

<div class="grid-2">
    <section class="card">
        <h2>7 Kebiasaan Hari Ini</h2>
        <?php if ($jurnal_hari_ini): ?>
            <p>Kamu sudah mengisi <strong><?= (int) $jurnal_hari_ini['jumlah'] ?> dari 7</strong> kebiasaan. Status: <?= pill_status_jurnal($jurnal_hari_ini['status']) ?></p>
            <div class="progress"><span style="width:<?= round($jurnal_hari_ini['jumlah'] / 7 * 100) ?>%"></span></div>
        <?php else: ?>
            <p>Kamu belum mengisi checklist hari ini.</p>
        <?php endif; ?>
        <p class="mt"><a class="btn btn-success" href="<?= e(url('siswa/kebiasaan.php')) ?>"><i class="bi bi-check2-circle"></i> Isi Checklist</a></p>
        <?php if ($badge['berikut']): ?>
            <p class="mt"><small>Tinggal <?= (int) $badge['berikut']['minimal_poin'] - $poin ?> poin lagi untuk badge
                <?= ikon_badge($badge['berikut']['ikon']) ?> <?= e($badge['berikut']['nama_badge']) ?>.</small></p>
            <div class="progress biru"><span style="width:<?= min(100, round($poin / $badge['berikut']['minimal_poin'] * 100)) ?>%"></span></div>
        <?php endif; ?>
        <p class="mt"><small>Progress kebiasaan <?= e($sem['label']) ?>: <strong><?= angka($progress['progress']) ?>%</strong></small></p>
    </section>

    <section class="card">
        <h2>Perkembangan Nilai TKA</h2>
        <div class="grafik" data-grafik="garis" data-maks="100"
             data-labels='<?= e(json_encode(array_map(fn($r) => date('d/m', strtotime($r['waktu_selesai'])), array_reverse($riwayat)))) ?>'
             data-values='<?= e(json_encode(array_map(fn($r) => (float) $r['nilai'], array_reverse($riwayat)))) ?>'></div>
    </section>
</div>

<section class="card">
    <h2>Kebiasaan 14 Hari Terakhir</h2>
    <div class="grafik" data-grafik="bar" data-maks="7"
         data-labels='<?= e(json_encode(array_map(fn($r) => date('d/m', strtotime($r['tanggal'])), $kebiasaan_hari))) ?>'
         data-values='<?= e(json_encode(array_map(fn($r) => (int) $r['jumlah'], $kebiasaan_hari))) ?>' data-warna='["#16a34a"]'></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
