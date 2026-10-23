<?php
// siswa/ujian-info.php
// Informasi ujian sebelum mulai. Tombol "Mulai Ujian" membuat catatan pengerjaan lalu membuka halaman ujian.
// Aturan: ujian hanya bisa dikerjakan sekali dan hanya pada jadwalnya.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['siswa']);
$siswa = siswa_login();
if (!$siswa) redirect(url('auth/logout.php'));

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$ujian = null;
foreach (ujian_untuk_siswa($siswa) as $u) if ((int) $u['id'] === $id) $ujian = $u;   // hanya ujian milik kelasnya
if (!$ujian) {
    flash('danger', 'Data tidak ditemukan.');
    redirect(url('siswa/ujian.php'));
}

$pesan = [
    'belum_mulai' => 'Ujian belum dimulai.',
    'berakhir'    => 'Ujian sudah berakhir.',
    'selesai'     => 'Anda sudah mengerjakan ujian.',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    if ($ujian['kondisi'] === 'berlangsung') redirect(url('siswa/ujian-kerjakan.php?id=' . $id));
    if ($ujian['kondisi'] !== 'tersedia') {
        flash('danger', $pesan[$ujian['kondisi']] ?? 'Akses ditolak.');
        redirect(url('siswa/ujian-info.php?id=' . $id));
    }
    if ((int) $ujian['jml_soal'] === 0) {
        flash('danger', 'Ujian ini belum memiliki soal.');
        redirect(url('siswa/ujian.php'));
    }
    try {
        // UNIQUE (ujian_id, siswa_id) mencegah dua kali mulai
        db()->prepare('INSERT INTO hasil_ujian (ujian_id, siswa_id, waktu_mulai, status) VALUES (:u, :s, :w, \'berlangsung\')')
            ->execute(['u' => $id, 's' => $siswa['id'], 'w' => date('Y-m-d H:i:s')]);
    } catch (PDOException $e) {
        // sudah ada: lanjut saja ke halaman ujian, validasi di sana
    }
    redirect(url('siswa/ujian-kerjakan.php?id=' . $id));
}

$judul = 'Informasi Ujian';
$aksi_header = '<a class="btn btn-light" href="' . e(url('siswa/ujian.php')) . '">&larr; Kembali</a>';
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <h2><?= e($ujian['nama_ujian']) ?></h2>
    <div class="tabel-wrap"><table class="tabel">
        <tr><th>Mata Pelajaran</th><td><?= e($ujian['nama_mapel']) ?></td></tr>
        <tr><th>Jumlah Soal</th><td><?= (int) $ujian['jml_soal'] ?> soal pilihan ganda</td></tr>
        <tr><th>Durasi</th><td><?= (int) $ujian['durasi_menit'] ?> menit</td></tr>
        <tr><th>Jadwal</th><td><?= e(waktu_indo($ujian['waktu_mulai'])) ?> s/d <?= e(waktu_indo($ujian['waktu_selesai'])) ?></td></tr>
    </table></div>

    <h3 class="mt">Petunjuk</h3>
    <ul>
        <li>Ujian hanya dapat dikerjakan <strong>satu kali</strong>.</li>
        <li>Waktu berjalan setelah kamu menekan tombol Mulai dan tidak berhenti walau halaman ditutup.</li>
        <li>Jawabanmu tersimpan otomatis. Kamu boleh menandai soal ragu-ragu dan berpindah soal.</li>
        <li>Saat waktu habis, jawaban dikirim dan dinilai otomatis.</li>
    </ul>

    <?php if (isset($pesan[$ujian['kondisi']])): ?>
        <div class="flash flash-warning"><?= e($pesan[$ujian['kondisi']]) ?></div>
        <?php if ($ujian['kondisi'] === 'selesai'): ?>
            <a class="btn btn-primary" href="<?= e(url('siswa/ujian-hasil.php?id=' . (int) $ujian['hasil_id'])) ?>">Lihat Nilai &amp; Pembahasan</a>
        <?php endif; ?>
    <?php else: ?>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
            <button type="submit" class="btn btn-success"><?= $ujian['kondisi'] === 'berlangsung' ? '<i class="bi bi-play-fill"></i> Lanjutkan Ujian' : '<i class="bi bi-rocket-fill"></i> Mulai Ujian' ?></button>
        </form>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
