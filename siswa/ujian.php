<?php
// siswa/ujian.php
// Daftar ujian TKA untuk kelas siswa beserta statusnya.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['siswa']);
$siswa = siswa_login();
if (!$siswa) redirect(url('auth/logout.php'));

$daftar = ujian_untuk_siswa($siswa);
$label = [
    'belum_mulai' => ['Belum dimulai', 'biru'], 'tersedia' => ['Aktif', 'hijau'], 'berlangsung' => ['Sedang dikerjakan', 'kuning'],
    'selesai' => ['Sudah dikerjakan', 'abu'], 'berakhir' => ['Berakhir', 'merah'],
];

$judul = 'Ujian TKA';
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>Ujian</th><th>Mapel</th><th class="angka">Soal</th><th class="angka">Durasi</th><th>Jadwal</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $u): [$teks, $warna] = $label[$u['kondisi']]; ?>
                <tr>
                    <td><strong><?= e($u['nama_ujian']) ?></strong></td>
                    <td><?= e($u['nama_mapel']) ?></td>
                    <td class="angka"><?= (int) $u['jml_soal'] ?></td>
                    <td class="angka"><?= (int) $u['durasi_menit'] ?> mnt</td>
                    <td><small><?= e(waktu_indo($u['waktu_mulai'])) ?><br>s/d <?= e(waktu_indo($u['waktu_selesai'])) ?></small></td>
                    <td><?= pill($teks, $warna) ?><?= $u['kondisi'] === 'selesai' ? ' <strong>' . angka($u['nilai']) . '</strong>' : '' ?></td>
                    <td>
                        <?php if ($u['kondisi'] === 'selesai'): ?>
                            <a class="btn btn-light btn-sm" href="<?= e(url('siswa/ujian-hasil.php?id=' . (int) $u['hasil_id'])) ?>">Lihat Nilai</a>
                        <?php else: ?>
                            <a class="btn btn-primary btn-sm" href="<?= e(url('siswa/ujian-info.php?id=' . (int) $u['id'])) ?>">
                                <?= $u['kondisi'] === 'berlangsung' ? 'Lanjutkan' : 'Detail' ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$daftar): ?><tr><td colspan="7" class="kosong">Belum ada ujian untuk kelasmu.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
