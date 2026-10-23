<?php
// siswa/ujian-hasil.php
// Nilai dan pembahasan setelah ujian selesai. Siswa hanya bisa membuka hasil miliknya sendiri.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['siswa']);
$siswa = siswa_login();
if (!$siswa) redirect(url('auth/logout.php'));

$hasil_id = (int) ($_GET['id'] ?? 0);
finalisasi_kadaluarsa(null, (int) $siswa['id']);
$hasil = ambil_hasil_siswa($hasil_id, (int) $siswa['id']);
if (!$hasil) {
    flash('danger', 'Data tidak ditemukan.');
    redirect(url('siswa/nilai.php'));
}
if ($hasil['status'] !== 'selesai') redirect(url('siswa/ujian-kerjakan.php?id=' . (int) $hasil['ujian_id']));

$st = db()->prepare('SELECT b.id, b.pertanyaan, b.gambar, b.kunci_jawaban, b.pembahasan, us.urutan, j.jawaban
                     FROM ujian_soal us JOIN bank_soal b ON b.id = us.soal_id
                     LEFT JOIN jawaban_siswa j ON j.soal_id = b.id AND j.hasil_id = :h
                     WHERE us.ujian_id = :u ORDER BY us.urutan');
$st->execute(['h' => $hasil_id, 'u' => $hasil['ujian_id']]);
$soal = $st->fetchAll();
$opsi = ambil_opsi(array_column($soal, 'id'));

$judul = 'Hasil: ' . $hasil['nama_ujian'];
$aksi_header = '<a class="btn btn-light" href="' . e(url('siswa/nilai.php')) . '">&larr; History Nilai</a>';
require __DIR__ . '/../includes/header.php';
$nilai = (float) $hasil['nilai'];
?>
<section class="card teks-tengah">
    <div class="stat-label"><?= e($hasil['nama_mapel']) ?> &middot; selesai <?= e(waktu_indo($hasil['waktu_selesai'])) ?></div>
    <div class="nilai-besar" style="color:var(--<?= $nilai >= 75 ? 'success' : ($nilai >= 60 ? 'warning' : 'danger') ?>)"><?= angka($nilai) ?></div>
    <p><i class="bi bi-check-circle-fill" style="color:var(--success)"></i> Benar: <strong><?= (int) $hasil['jumlah_benar'] ?></strong> &nbsp; <i class="bi bi-x-circle-fill" style="color:var(--danger)"></i> Salah: <strong><?= (int) $hasil['jumlah_salah'] ?></strong>
        &nbsp; dari <?= count($soal) ?> soal</p>
    <p><?= $nilai >= 75 ? 'Hebat, pertahankan ya! <i class="bi bi-stars"></i>' : ($nilai >= 60 ? 'Bagus, ayo belajar lagi supaya lebih baik! <i class="bi bi-emoji-smile-fill"></i>' : 'Jangan menyerah, pelajari pembahasan di bawah ya! <i class="bi bi-journal-text"></i>') ?></p>
</section>

<h2>Pembahasan</h2>
<?php foreach ($soal as $i => $s):
    $benar = $s['jawaban'] === $s['kunci_jawaban']; ?>
    <section class="card hasil-soal <?= $benar ? 'benar' : 'salah' ?>">
        <strong>Soal <?= $i + 1 ?> <?= $benar ? pill('Benar', 'hijau') : pill($s['jawaban'] ? 'Salah' : 'Tidak dijawab', 'merah') ?></strong>
        <div class="soal-teks"><?= e($s['pertanyaan']) ?></div>
        <?php if ($s['gambar']): ?><img class="soal-gambar" src="<?= e(url('uploads/soal/' . $s['gambar'])) ?>" alt="Gambar soal"><?php endif; ?>
        <?php foreach ($opsi[$s['id']] ?? [] as $label => $teks):
            $kelas = $label === $s['kunci_jawaban'] ? 'kunci' : ($label === $s['jawaban'] ? 'salah' : ''); ?>
            <div class="opsi-hasil <?= $kelas ?>"><strong><?= e($label) ?>.</strong> <?= e($teks) ?>
                <?= $label === $s['kunci_jawaban'] ? ' <i class="bi bi-check-lg"></i> (kunci)' : '' ?><?= $label === $s['jawaban'] && !$benar ? ' (jawabanmu)' : '' ?></div>
        <?php endforeach; ?>
        <?php if ($s['pembahasan']): ?><div class="pembahasan"><i class="bi bi-lightbulb-fill"></i> <?= e($s['pembahasan']) ?></div><?php endif; ?>
    </section>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
