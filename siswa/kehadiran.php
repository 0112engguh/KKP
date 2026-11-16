<?php
// siswa/kehadiran.php
// Rekap kehadiran pribadi siswa (harian / bulanan / semester).

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['siswa']);
$siswa = siswa_login();
if (!$siswa) redirect(url('auth/logout.php'));

$periode = ambil_periode($_GET);
$rekap = rekap_absensi_siswa((int) $siswa['id'], $periode['dari'], $periode['sampai']);
$detail_semua = detail_absensi_siswa((int) $siswa['id'], $periode['dari'], $periode['sampai']);
[$detail, $halaman, $hal_akhir] = paginasi_array($detail_semua);
$get_periode = ['jenis' => $periode['jenis'], 'tanggal' => $periode['tanggal'], 'bulan' => $periode['bulan'],
                'semester' => $periode['semester'], 'tahun' => $periode['tahun']];

$judul = 'Rekap Kehadiran';
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <form method="get" class="toolbar">
        <?= form_periode($periode) ?>
        <button class="btn btn-primary" type="submit">Tampilkan</button>
    </form>
</section>

<div class="grid-2">
    <section class="card">
        <h2>Kehadiran <?= e($periode['label']) ?></h2>
        <?php if ($rekap['total'] > 0): ?>
            <div class="grafik" data-grafik="donat" data-labels='["Hadir","Izin","Sakit","Alpa"]'
                 data-values='[<?= (int) $rekap['hadir'] ?>,<?= (int) $rekap['izin'] ?>,<?= (int) $rekap['sakit'] ?>,<?= (int) $rekap['alpa'] ?>]'
                 data-warna='["#16a34a","#2563eb","#eab308","#dc2626"]'></div>
            <p class="teks-tengah">Persentase hadir: <strong><?= angka($rekap['persen']) ?>%</strong> (<?= (int) $rekap['hadir'] ?> dari <?= (int) $rekap['total'] ?> hari)</p>
        <?php else: ?>
            <div class="kosong">Belum ada data absensi pada periode ini.</div>
        <?php endif; ?>
    </section>
    <section class="card">
        <h2>Rincian Tanggal</h2>
        <div class="tabel-wrap" style="max-height:380px;overflow-y:auto">
            <table class="tabel">
                <thead><tr><th>Tanggal</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($detail as $d): ?>
                    <tr><td><?= e(hari_tgl_indo($d['tanggal'])) ?></td><td><?= pill_absen($d['status']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$detail): ?><tr><td colspan="2" class="kosong">Tidak ada data.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= paginasi_tautan($halaman, $hal_akhir, $get_periode) ?>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
