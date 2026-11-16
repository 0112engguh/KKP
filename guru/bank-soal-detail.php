<?php
// guru/bank-soal-detail.php
// Child Bank Soal: daftar soal di dalam satu kelompok ujian.
// Tambah soal baru, ambil soal dari Bank Soal, atur urutan & bobot,
// ubah soal, dan lepas soal dari kelompok (soal tetap ada di Bank Soal).

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$kelas_semua = kelas_guru();
if (!$kelas_semua) akhir_kelas();

$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare('SELECT k.*, kl.nama_kelas FROM kelompok_ujian k JOIN kelas kl ON kl.id = k.kelas_id WHERE k.id = :id');
$st->execute(['id' => $id]);
$kelompok = $st->fetch();
if (!$kelompok) {
    flash('danger', 'Data tidak ditemukan.');
    redirect(url('guru/bank-soal.php'));
}
if (!punya_kelas($kelas_semua, (int) $kelompok['kelas_id'])) {
    flash('danger', 'Kelompok ujian tidak termasuk kelas yang ditugaskan kepada Anda.');
    redirect(url('guru/bank-soal.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $soal_id = (int) ($_POST['soal_id'] ?? 0);
    $aksi    = $_POST['aksi'] ?? '';

    $st = db()->prepare('SELECT id, soal_id, urutan FROM kelompok_ujian_soal WHERE kelompok_id = :k AND soal_id = :s');
    $st->execute(['k' => $id, 's' => $soal_id]);
    $baris = $st->fetch();

    switch ($aksi) {
        case 'tambah_soal':
            // Soal harus ada di Bank Soal dan belum ada di kelompok ini (tanpa duplikat)
            if ($soal_id > 0 && !$baris
                && skalar('SELECT COUNT(*) FROM bank_soal WHERE id = :s', ['s' => $soal_id])) {
                $urutan = (int) skalar('SELECT COALESCE(MAX(urutan), 0) FROM kelompok_ujian_soal WHERE kelompok_id = :k', ['k' => $id]) + 1;
                db()->prepare('INSERT INTO kelompok_ujian_soal (kelompok_id, soal_id, urutan) VALUES (:k, :s, :u)')
                    ->execute(['k' => $id, 's' => $soal_id, 'u' => $urutan]);
                flash('success', 'Soal berhasil ditambahkan ke kelompok.');
            } else {
                flash('warning', $baris ? 'Soal sudah ada di kelompok ini.' : 'Soal tidak ditemukan.');
            }
            break;

        case 'hapus_soal':
            if ($baris) {
                db()->prepare('DELETE FROM kelompok_ujian_soal WHERE id = :b')->execute(['b' => (int) $baris['id']]);
                flash('success', 'Soal dilepas dari kelompok. Soal tetap tersimpan di Bank Soal.');
            }
            break;

        case 'urutan':
            if ($baris) {
                $arah = ($_POST['arah'] ?? '') === 'atas' ? 'atas' : 'bawah';
                // Tukar posisi dengan baris tetangga sesuai arah
                $sql = $arah === 'atas'
                    ? 'SELECT soal_id, urutan FROM kelompok_ujian_soal WHERE kelompok_id = :k AND urutan < :u ORDER BY urutan DESC LIMIT 1'
                    : 'SELECT soal_id, urutan FROM kelompok_ujian_soal WHERE kelompok_id = :k AND urutan > :u ORDER BY urutan ASC LIMIT 1';
                $st = db()->prepare($sql);
                $st->execute(['k' => $id, 'u' => (int) $baris['urutan']]);
                $tetangga = $st->fetch();
                if ($tetangga) {
                    $pdo = db();
                    $pdo->beginTransaction();
                    $up = $pdo->prepare('UPDATE kelompok_ujian_soal SET urutan = :u WHERE kelompok_id = :k AND soal_id = :s');
                    $up->execute(['u' => (int) $tetangga['urutan'], 'k' => $id, 's' => (int) $baris['soal_id']]);
                    $up->execute(['u' => (int) $baris['urutan'], 'k' => $id, 's' => (int) $tetangga['soal_id']]);
                    $pdo->commit();
                    flash('success', 'Urutan soal diperbarui.');
                }
            }
            break;

        case 'bobot':
            if ($baris) {
                $bobot = (float) str_replace(',', '.', trim($_POST['bobot'] ?? ''));
                if ($bobot > 0 && $bobot <= 999.99) {
                    db()->prepare('UPDATE kelompok_ujian_soal SET bobot = :b WHERE id = :i')
                        ->execute(['b' => $bobot, 'i' => (int) $baris['id']]);
                    flash('success', 'Bobot soal diperbarui.');
                } else {
                    flash('danger', 'Bobot harus angka antara 0,25 sampai 999,99.');
                }
            }
            break;
    }
    redirect(url('guru/bank-soal-detail.php?id=' . $id));
}

// Daftar soal di dalam kelompok (urut urutan, dipaginasi)
[$soal_kelompok, $halaman, $hal_akhir] = paginasi(
    'SELECT ks.id AS rel_id, ks.urutan, ks.bobot, b.id, b.pertanyaan, b.tingkat, b.kunci_jawaban,
                            b.gambar, m.nama_mapel
                     FROM kelompok_ujian_soal ks
                     JOIN bank_soal b ON b.id = ks.soal_id
                     JOIN mata_pelajaran m ON m.id = b.mapel_id
                     WHERE ks.kelompok_id = :k ORDER BY ks.urutan, ks.id',
    'SELECT COUNT(*) FROM kelompok_ujian_soal WHERE kelompok_id = :k', ['k' => $id]);
$total_bobot = (float) skalar('SELECT COALESCE(SUM(bobot), 0) FROM kelompok_ujian_soal WHERE kelompok_id = :k', ['k' => $id]);

// Filter soal yang bisa diambil dari Bank Soal (belum ada di kelompok ini)
$mapel_id = (int) ($_GET['mapel'] ?? 0);
$tingkat  = $_GET['tingkat'] ?? '';
$cari     = trim($_GET['q'] ?? '');

$where = ' AND 1=1';
$param = ['k' => $id];
if ($mapel_id > 0) { $where .= ' AND b.mapel_id = :mapel'; $param['mapel'] = $mapel_id; }
if (in_array($tingkat, ['mudah', 'sedang', 'sulit'], true)) { $where .= ' AND b.tingkat = :tingkat'; $param['tingkat'] = $tingkat; }
if ($cari !== '') { $where .= ' AND b.pertanyaan LIKE :cari'; $param['cari'] = '%' . $cari . '%'; }

$st = db()->prepare('SELECT b.id, b.pertanyaan, b.tingkat, m.nama_mapel,
                            (SELECT COUNT(*) FROM kelompok_ujian_soal x WHERE x.soal_id = b.id) AS dipakai
                     FROM bank_soal b JOIN mata_pelajaran m ON m.id = b.mapel_id
                     WHERE NOT EXISTS (SELECT 1 FROM kelompok_ujian_soal ks WHERE ks.kelompok_id = :k AND ks.soal_id = b.id)'
                     . $where . ' ORDER BY m.nama_mapel, b.id');
[$soal_tersedia, $halaman2, $hal_akhir2] = paginasi($st->queryString,
    'SELECT COUNT(*) FROM bank_soal b JOIN mata_pelajaran m ON m.id = b.mapel_id
                     WHERE NOT EXISTS (SELECT 1 FROM kelompok_ujian_soal ks WHERE ks.kelompok_id = :k AND ks.soal_id = b.id)'
                     . $where, $param, PER_HALAMAN, 'hal2');

$judul = 'Kelompok: ' . $kelompok['nama_ujian'];
$aksi_header = '<a class="btn btn-light" href="' . e(url('guru/bank-soal.php')) . '">&larr; Kembali</a>'
    . ' <a class="btn btn-primary" href="' . e(url('guru/bank-soal-form.php?kelompok=' . $id)) . '">+ Soal Baru</a>';
require __DIR__ . '/../includes/header.php';
$warna_tingkat = ['mudah' => 'hijau', 'sedang' => 'kuning', 'sulit' => 'merah'];
?>
<section class="card">
    <div class="form-baris">
        <div class="form-grup"><small><strong>Nama Ujian</strong><br><?= e($kelompok['nama_ujian']) ?></small></div>
        <div class="form-grup"><small><strong>Tahun Ajaran</strong><br><?= e($kelompok['tahun_ajaran']) ?></small></div>
        <div class="form-grup"><small><strong>Semester</strong><br><?= e(ucfirst($kelompok['semester'])) ?></small></div>
        <div class="form-grup"><small><strong>Kelas</strong><br><?= e($kelompok['nama_kelas']) ?></small></div>
        <div class="form-grup"><small><strong>Status</strong><br><?= $kelompok['status'] === 'aktif' ? pill('Aktif', 'hijau') : pill('Draft', 'abu') ?></small></div>
        <div class="form-grup"><small><strong>Jumlah Soal</strong><br><?= count($soal_kelompok) ?></small></div>
        <div class="form-grup"><small><strong>Total Bobot</strong><br><?= angka($total_bobot, 2) ?></small></div>
    </div>
</section>

<section class="card">
    <h2>Daftar Soal dalam Kelompok</h2>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th class="angka">No</th><th>Pertanyaan</th><th>Mapel</th><th>Tingkat</th>
                <th class="angka">Kunci</th><th class="angka">Bobot</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($soal_kelompok as $i => $s): ?>
                <tr>
                    <td class="angka"><?= (int) $s['urutan'] ?></td>
                    <td><?= e(mb_strimwidth($s['pertanyaan'], 0, 90, '...')) ?><?= $s['gambar'] ? ' <i class="bi bi-image-fill"></i>' : '' ?></td>
                    <td><?= e($s['nama_mapel']) ?></td>
                    <td><?= pill(ucfirst($s['tingkat']), $warna_tingkat[$s['tingkat']]) ?></td>
                    <td class="angka"><strong><?= e($s['kunci_jawaban']) ?></strong></td>
                    <td>
                        <form method="post" class="inline" style="display:flex;gap:.25rem">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="bobot">
                            <input type="hidden" name="soal_id" value="<?= (int) $s['id'] ?>">
                            <input type="number" name="bobot" value="<?= number_format((float) $s['bobot'], 2, '.', '') ?>" min="0.25" max="999.99" step="0.25" style="width:5.5rem" required>
                            <button class="btn btn-light btn-sm" type="submit">Set</button>
                        </form>
                    </td>
                    <td><div class="aksi-baris">
                        <form method="post" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="urutan"><input type="hidden" name="arah" value="atas">
                            <input type="hidden" name="soal_id" value="<?= (int) $s['id'] ?>">
                            <button class="btn btn-light btn-sm" type="submit" title="Naik" <?= $i === 0 ? 'disabled' : '' ?>>&#9650;</button>
                        </form>
                        <form method="post" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="urutan"><input type="hidden" name="arah" value="bawah">
                            <input type="hidden" name="soal_id" value="<?= (int) $s['id'] ?>">
                            <button class="btn btn-light btn-sm" type="submit" title="Turun" <?= $i === count($soal_kelompok) - 1 ? 'disabled' : '' ?>>&#9660;</button>
                        </form>
                        <a class="btn btn-light btn-sm" href="<?= e(url('guru/bank-soal-form.php?id=' . (int) $s['id'] . '&kelompok=' . $id)) ?>">Ubah</a>
                        <form method="post" class="inline" data-konfirmasi="Lepas soal ini dari kelompok? Soal tetap tersimpan di Bank Soal.">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus_soal">
                            <input type="hidden" name="soal_id" value="<?= (int) $s['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Lepas</button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$soal_kelompok): ?><tr><td colspan="7" class="kosong">Belum ada soal dalam kelompok ini. Tambahkan soal baru atau ambil dari Bank Soal di bawah.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= paginasi_tautan($halaman, $hal_akhir, ['id' => $id]) ?>
    <small>Jumlah soal: <?= count($soal_kelompok) ?> pada halaman ini &middot; Total bobot: <?= angka($total_bobot, 2) ?></small>
</section>

<section class="card">
    <h2>Ambil Soal dari Bank Soal</h2>
    <form method="get" class="toolbar">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-grup"><label for="mapel">Mata Pelajaran</label>
            <select name="mapel" id="mapel"><option value="0">Semua</option>
                <?php foreach (semua_mapel() as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" <?= $mapel_id === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['nama_mapel']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-grup"><label for="tingkat">Tingkat</label>
            <select name="tingkat" id="tingkat"><option value="">Semua</option>
                <?php foreach (['mudah', 'sedang', 'sulit'] as $t): ?>
                    <option value="<?= e($t) ?>" <?= $tingkat === $t ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-grup"><label for="q">Cari pertanyaan</label>
            <input type="search" id="q" name="q" value="<?= e($cari) ?>"></div>
        <button class="btn btn-primary" type="submit">Cari</button>
    </form>

    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>No</th><th>Pertanyaan</th><th>Mapel</th><th>Tingkat</th><th class="angka">Dipakai</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($soal_tersedia as $i => $s): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e(mb_strimwidth($s['pertanyaan'], 0, 90, '...')) ?></td>
                    <td><?= e($s['nama_mapel']) ?></td>
                    <td><?= pill(ucfirst($s['tingkat']), $warna_tingkat[$s['tingkat']]) ?></td>
                    <td class="angka"><?= (int) $s['dipakai'] ?> kelompok</td>
                    <td>
                        <form method="post" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="tambah_soal">
                            <input type="hidden" name="soal_id" value="<?= (int) $s['id'] ?>">
                            <button class="btn btn-primary btn-sm" type="submit">Tambah</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$soal_tersedia): ?><tr><td colspan="6" class="kosong">Semua soal yang cocok sudah ada di kelompok ini, atau belum ada soal di Bank Soal.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= paginasi_tautan($halaman2, $hal_akhir2, ['id' => $id, 'mapel' => $mapel_id, 'tingkat' => $tingkat, 'q' => $cari], 'hal2') ?>
    <small>Soal yang sama dapat dipakai beberapa kelompok tanpa membuat duplikat.</small>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
