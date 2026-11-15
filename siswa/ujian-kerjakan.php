<?php
// siswa/ujian-kerjakan.php
// Halaman pengerjaan ujian: timer hitung mundur (JavaScript), navigasi soal, tandai ragu-ragu, dan kirim jawaban.
// Backend tetap memvalidasi waktu: jika sudah lewat batas, ujian diproses otomatis.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['siswa']);
$siswa = siswa_login();
if (!$siswa) redirect(url('auth/logout.php'));

$ujian_id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare("SELECT id FROM hasil_ujian WHERE ujian_id = :u AND siswa_id = :s");
$st->execute(['u' => $ujian_id, 's' => $siswa['id']]);
$hasil_id = (int) $st->fetchColumn();
$hasil = $hasil_id ? ambil_hasil_siswa($hasil_id, (int) $siswa['id']) : null;

if (!$hasil) {
    flash('warning', 'Mulai ujian dari halaman informasi ujian.');
    redirect(url('siswa/ujian-info.php?id=' . $ujian_id));
}
if ($hasil['status'] === 'selesai') {
    flash('info', 'Anda sudah mengerjakan ujian.');
    redirect(url('siswa/ujian-hasil.php?id=' . $hasil_id));
}

// Validasi waktu di server
$batas = batas_waktu_hasil($hasil['waktu_mulai'], (int) $hasil['durasi_menit'], $hasil['jadwal_selesai']);
$sisa = $batas - time();
if ($sisa <= 0) {
    selesaikan_ujian($hasil_id);
    flash('warning', 'Waktu ujian sudah habis. Jawabanmu sudah dinilai otomatis.');
    redirect(url('siswa/ujian-hasil.php?id=' . $hasil_id));
}

$st = db()->prepare('SELECT b.id, b.pertanyaan, b.gambar, us.urutan FROM ujian_soal us JOIN bank_soal b ON b.id = us.soal_id
                     WHERE us.ujian_id = :u ORDER BY us.urutan');
$st->execute(['u' => $ujian_id]);
$soal = $st->fetchAll();
$opsi = ambil_opsi(array_column($soal, 'id'));

$st = db()->prepare('SELECT soal_id, jawaban, ragu FROM jawaban_siswa WHERE hasil_id = :h');
$st->execute(['h' => $hasil_id]);
$jawab = [];
foreach ($st->fetchAll() as $j) $jawab[$j['soal_id']] = $j;

$judul = $hasil['nama_ujian'];
$tanpa_sidebar = true;
$sembunyikan_judul = true;
$js_tambahan = ['ujian.js'];
require __DIR__ . '/../includes/header.php';
?>
<form method="post" action="<?= e(url('siswa/ujian-submit.php')) ?>" id="formUjian"
      data-sisa="<?= (int) $sisa ?>" data-url-simpan="<?= e(url('siswa/ujian-simpan.php')) ?>"
      data-hasil="<?= (int) $hasil_id ?>" data-csrf="<?= e(csrf_token()) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="hasil_id" value="<?= (int) $hasil_id ?>">

    <div class="ujian-bar">
        <div><strong><?= e($hasil['nama_ujian']) ?></strong><br><small><?= e($hasil['nama_mapel']) ?> &middot; <?= count($soal) ?> soal</small></div>
        <div class="timer" id="timer" aria-live="off">--:--</div>
    </div>

    <div class="ujian-layout">
        <section class="card">
            <?php foreach ($soal as $i => $s): $j = $jawab[$s['id']] ?? ['jawaban' => null, 'ragu' => 0]; ?>
                <div class="soal-panel<?= $i === 0 ? ' tampil' : '' ?>" data-index="<?= $i ?>" data-soal="<?= (int) $s['id'] ?>">
                    <strong>Soal <?= (int) ($i + 1) ?> dari <?= count($soal) ?></strong>
                    <div class="soal-teks"><?= e($s['pertanyaan']) ?></div>
                    <?php if ($s['gambar']): ?><img class="soal-gambar" src="<?= e(url('uploads/soal/' . $s['gambar'])) ?>" alt="Gambar soal"><?php endif; ?>
                    <?php foreach ($opsi[$s['id']] ?? [] as $label => $teks): ?>
                        <label class="opsi">
                            <input type="radio" name="jawab[<?= (int) $s['id'] ?>]" value="<?= e($label) ?>" <?= $j['jawaban'] === $label ? 'checked' : '' ?>>
                            <span class="huruf"><?= e($label) ?></span><span><?= e($teks) ?></span>
                        </label>
                    <?php endforeach; ?>
                    <label class="cek-baris mt">
                        <input type="checkbox" class="cek-ragu" name="ragu[<?= (int) $s['id'] ?>]" value="1" <?= $j['ragu'] ? 'checked' : '' ?>> Tandai ragu-ragu
                    </label>
                </div>
            <?php endforeach; ?>

            <div class="toolbar mt">
                <button type="button" class="btn btn-light" id="tombolSebelumnya">&larr; Sebelumnya</button>
                <button type="button" class="btn btn-primary" id="tombolBerikutnya">Berikutnya &rarr;</button>
                <span class="status-simpan" id="statusSimpan"></span>
            </div>
        </section>

        <aside class="card">
            <h3>Navigasi Soal</h3>
            <div class="nav-soal">
                <?php foreach ($soal as $i => $s): ?>
                    <button type="button" data-ke="<?= (int) $i ?>"><?= (int) ($i + 1) ?></button>
                <?php endforeach; ?>
            </div>
            <div class="nav-ket">
                <span><i style="border-color:var(--success);background:var(--success-muda)"></i>Dijawab</span>
                <span><i style="border-color:var(--warning);background:var(--warning-muda)"></i>Ragu-ragu</span>
                <span><i style="border-color:var(--abu-garis)"></i>Belum</span>
            </div>
            <p class="mt"><button type="submit" class="btn btn-success btn-blok" id="tombolKirim"><i class="bi bi-send-fill"></i> Kirim Jawaban</button></p>
            <p><small>Setelah dikirim, ujian tidak dapat diulang.</small></p>
        </aside>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
