<?php
// siswa/nilai.php
// History nilai TKA milik siswa dan grafik perkembangannya.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['siswa']);
$siswa = siswa_login();
if (!$siswa) redirect(url('auth/logout.php'));

finalisasi_kadaluarsa(null, (int) $siswa['id']);
$riwayat = riwayat_nilai((int) $siswa['id']);
$urut = array_reverse($riwayat);

$judul = 'History Nilai TKA';
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <h2>Perkembangan Nilai</h2>
    <div class="grafik" data-grafik="garis" data-maks="100"
         data-labels='<?= e(json_encode(array_map(fn($r) => date('d/m', strtotime($r['waktu_selesai'])), $urut))) ?>'
         data-values='<?= e(json_encode(array_map(fn($r) => (float) $r['nilai'], $urut))) ?>'></div>
    <?php if ($riwayat): ?><p class="teks-tengah">Rata-rata nilai: <strong><?= angka(rata_rata_nilai($riwayat)) ?></strong></p><?php endif; ?>
</section>

<section class="card">
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>Tanggal</th><th>Ujian</th><th>Mapel</th><th class="angka">Benar</th><th class="angka">Salah</th><th class="angka">Nilai</th><th>Pembahasan</th></tr></thead>
            <tbody>
            <?php foreach ($riwayat as $r): ?>
                <tr>
                    <td><?= e(tgl_indo($r['waktu_selesai'])) ?></td>
                    <td><strong><?= e($r['nama_ujian']) ?></strong></td>
                    <td><?= e($r['nama_mapel']) ?></td>
                    <td class="angka"><?= (int) $r['jumlah_benar'] ?></td><td class="angka"><?= (int) $r['jumlah_salah'] ?></td>
                    <td class="angka"><?= pill(angka($r['nilai']), warna_nilai((float) $r['nilai'])) ?></td>
                    <td><a class="btn btn-light btn-sm" href="<?= e(url('siswa/ujian-hasil.php?id=' . (int) $r['id'])) ?>">Lihat</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$riwayat): ?><tr><td colspan="7" class="kosong">Belum ada nilai. Kerjakan ujian di menu Ujian Aktif.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
