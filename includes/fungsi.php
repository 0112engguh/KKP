<?php
// includes/fungsi.php
// Kumpulan fungsi bantu yang dipakai banyak halaman: format tanggal, rekap absensi,
// poin & badge, nilai TKA, penilaian otomatis ujian, periode laporan, export CSV.

const NAMA_BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
                    'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const NAMA_HARI  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const STATUS_ABSEN = ['H' => 'Hadir', 'I' => 'Izin', 'S' => 'Sakit', 'A' => 'Alpa'];

/* ---------- Format ---------- */

function tgl_indo(?string $tanggal): string
{
    if (!$tanggal) return '-';
    $t = strtotime($tanggal);
    return date('j', $t) . ' ' . NAMA_BULAN[(int) date('n', $t)] . ' ' . date('Y', $t);
}

function hari_tgl_indo(?string $tanggal): string
{
    if (!$tanggal) return '-';
    return NAMA_HARI[(int) date('w', strtotime($tanggal))] . ', ' . tgl_indo($tanggal);
}

function waktu_indo(?string $waktu): string
{
    if (!$waktu) return '-';
    return tgl_indo($waktu) . ', ' . date('H:i', strtotime($waktu));
}

function angka($nilai, int $desimal = 1): string
{
    return number_format((float) $nilai, $desimal, ',', '.');
}

function persen($bagian, $total): float
{
    return $total > 0 ? round($bagian / $total * 100, 1) : 0.0;
}

// Label berwarna kecil. $warna: hijau | kuning | merah | biru | abu
function pill(string $teks, string $warna = 'abu'): string
{
    return '<span class="pill pill-' . e($warna) . '">' . e($teks) . '</span>';
}

function pill_status_jurnal(string $status): string
{
    $warna = ['menunggu' => 'kuning', 'disetujui' => 'hijau', 'ditolak' => 'merah'];
    return pill(ucfirst($status), $warna[$status] ?? 'abu');
}

function pill_absen(string $kode): string
{
    $warna = ['H' => 'hijau', 'I' => 'biru', 'S' => 'kuning', 'A' => 'merah'];
    return pill(STATUS_ABSEN[$kode] ?? $kode, $warna[$kode] ?? 'abu');
}

function warna_nilai(float $nilai): string
{
    return $nilai >= 75 ? 'hijau' : ($nilai >= 60 ? 'kuning' : 'merah');
}

/* ---------- Data master ringkas ---------- */

function semua_kelas(): array
{
    return db()->query('SELECT id, nama_kelas, tingkat FROM kelas ORDER BY tingkat, nama_kelas')->fetchAll();
}

/* ---------- Penugasan kelas guru ---------- */

// Kelas yang boleh dikelola pengguna saat ini: admin bebas, guru hanya kelas yang
// ditugaskan admin lewat menu Data Guru. Guru tanpa penugasan tidak dapat kelas.
function kelas_guru(): array
{
    $u = user();
    if ($u && $u['role'] === 'admin') return semua_kelas();
    if (!$u || $u['role'] !== 'guru') return [];
    $st = db()->prepare('SELECT k.id, k.nama_kelas, k.tingkat FROM guru_kelas gk JOIN kelas k ON k.id = gk.kelas_id
                         WHERE gk.guru_id = :g ORDER BY k.tingkat, k.nama_kelas');
    $st->execute(['g' => (int) $u['profil_id']]);
    return $st->fetchAll();
}

function punya_kelas(array $izin, int $kelas_id): bool
{
    return in_array($kelas_id, array_map('intval', array_column($izin, 'id')), true);
}

// Berhenti: kelas kosong / belum ditugaskan. Pengguna diarahkan sesuai role.
function akhir_kelas(): void
{
    $admin = user() && user()['role'] === 'admin';
    flash('warning', $admin ? 'Belum ada kelas. Tambahkan dulu di menu Kelas.' : 'Anda belum ditugaskan kelas. Hubungi admin sekolah.');
    redirect(url($admin ? 'guru/kelas.php' : 'guru/dashboard.php'));
}

function semua_mapel(): array
{
    return db()->query('SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel')->fetchAll();
}

// Ambil satu nilai (COUNT, AVG, dll). Contoh: skalar('SELECT COUNT(*) FROM siswa WHERE kelas_id = :k', ['k' => 1])
function skalar(string $sql, array $param = [])
{
    $st = db()->prepare($sql);
    $st->execute($param);
    return $st->fetchColumn();
}

function siswa_login(): ?array
{
    $u = user();
    if (!$u || $u['role'] !== 'siswa') return null;
    $st = db()->prepare('SELECT s.*, k.nama_kelas FROM siswa s JOIN kelas k ON k.id = s.kelas_id WHERE s.id = :id');
    $st->execute(['id' => $u['profil_id']]);
    return $st->fetch() ?: null;
}

/* ---------- Periode (harian / bulanan / semester) ---------- */

function semester_aktif(?string $tanggal = null): array
{
    $t = $tanggal ? strtotime($tanggal) : time();
    $tahun = (int) date('Y', $t);
    if ((int) date('n', $t) >= 7) {
        return ['dari' => "$tahun-07-01", 'sampai' => "$tahun-12-31", 'label' => "Semester Ganjil $tahun/" . ($tahun + 1)];
    }
    return ['dari' => "$tahun-01-01", 'sampai' => "$tahun-06-30", 'label' => 'Semester Genap ' . ($tahun - 1) . "/$tahun"];
}

function ambil_periode(array $in): array
{
    $jenis = $in['jenis'] ?? 'bulanan';
    if (!in_array($jenis, ['harian', 'bulanan', 'semester'], true)) $jenis = 'bulanan';

    $tanggal = date('Y-m-d');
    if (!empty($in['tanggal']) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $in['tanggal'], $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        $tanggal = $in['tanggal'];
    }

    $bulan = date('Y-m');
    if (!empty($in['bulan']) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $in['bulan'])) {
        $bulan = $in['bulan'];
    }

    $semester = (int) date('n') >= 7 ? 'ganjil' : 'genap';
    if (in_array($in['semester'] ?? '', ['ganjil', 'genap'], true)) $semester = $in['semester'];

    $tahun = (int) date('Y');
    if (isset($in['tahun']) && (int) $in['tahun'] >= 2000 && (int) $in['tahun'] <= 2100) $tahun = (int) $in['tahun'];

    if ($jenis === 'harian') {
        $dari = $sampai = $tanggal;
        $label = hari_tgl_indo($tanggal);
    } elseif ($jenis === 'bulanan') {
        $dari = $bulan . '-01';
        $sampai = date('Y-m-t', strtotime($dari));
        $label = NAMA_BULAN[(int) substr($bulan, 5, 2)] . ' ' . substr($bulan, 0, 4);
    } elseif ($semester === 'ganjil') {
        $dari = "$tahun-07-01";
        $sampai = "$tahun-12-31";
        $label = "Semester Ganjil $tahun/" . ($tahun + 1);
    } else {
        $dari = "$tahun-01-01";
        $sampai = "$tahun-06-30";
        $label = 'Semester Genap ' . ($tahun - 1) . "/$tahun";
    }

    return compact('jenis', 'tanggal', 'bulan', 'semester', 'tahun', 'dari', 'sampai', 'label');
}

// Kontrol filter periode (dipakai di halaman rekap & laporan). JavaScript app.js
// menyembunyikan kolom yang tidak relevan dengan jenis rekap yang dipilih.
function form_periode(array $p): string
{
    $pilih = fn($a, $b) => $a === $b ? ' selected' : '';
    ob_start(); ?>
    <div class="form-grup">
        <label for="jenis">Jenis Rekap</label>
        <select name="jenis" id="jenis" data-periode-jenis>
            <option value="harian"<?= $pilih($p['jenis'], 'harian') ?>>Harian</option>
            <option value="bulanan"<?= $pilih($p['jenis'], 'bulanan') ?>>Bulanan</option>
            <option value="semester"<?= $pilih($p['jenis'], 'semester') ?>>Semester</option>
        </select>
    </div>
    <div class="form-grup" data-periode="harian">
        <label for="tanggal">Tanggal</label>
        <input type="date" name="tanggal" id="tanggal" value="<?= e($p['tanggal']) ?>">
    </div>
    <div class="form-grup" data-periode="bulanan">
        <label for="bulan">Bulan</label>
        <input type="month" name="bulan" id="bulan" value="<?= e($p['bulan']) ?>" placeholder="YYYY-MM" pattern="\d{4}-\d{2}">
    </div>
    <div class="form-grup" data-periode="semester">
        <label for="semester">Semester</label>
        <select name="semester" id="semester">
            <option value="ganjil"<?= $pilih($p['semester'], 'ganjil') ?>>Ganjil (Jul-Des)</option>
            <option value="genap"<?= $pilih($p['semester'], 'genap') ?>>Genap (Jan-Jun)</option>
        </select>
    </div>
    <div class="form-grup" data-periode="semester">
        <label for="tahun">Tahun</label>
        <input type="number" name="tahun" id="tahun" min="2000" max="2100" value="<?= e($p['tahun']) ?>">
    </div>
    <?php
    return ob_get_clean();
}

/* ---------- Absensi ---------- */

// Rekap satu kelas: per siswa jumlah H/I/S/A, total hari, dan persentase hadir
function rekap_absensi_kelas(int $kelas_id, string $dari, string $sampai): array
{
    $sql = "SELECT s.id, s.nisn, s.nama,
                   COALESCE(SUM(d.status = 'H'), 0) AS hadir,
                   COALESCE(SUM(d.status = 'I'), 0) AS izin,
                   COALESCE(SUM(d.status = 'S'), 0) AS sakit,
                   COALESCE(SUM(d.status = 'A'), 0) AS alpa,
                   COUNT(d.id) AS total
            FROM siswa s
            LEFT JOIN absensi a ON a.kelas_id = s.kelas_id AND a.tanggal BETWEEN :dari AND :sampai
            LEFT JOIN absensi_detail d ON d.absensi_id = a.id AND d.siswa_id = s.id
            WHERE s.kelas_id = :kelas AND s.status = 'aktif'
            GROUP BY s.id, s.nisn, s.nama
            ORDER BY s.nama";
    $st = db()->prepare($sql);
    $st->execute(['dari' => $dari, 'sampai' => $sampai, 'kelas' => $kelas_id]);
    $baris = $st->fetchAll();
    foreach ($baris as &$b) {
        foreach (['hadir', 'izin', 'sakit', 'alpa', 'total'] as $k) $b[$k] = (int) $b[$k];
        $b['persen'] = persen($b['hadir'], $b['total']);
    }
    return $baris;
}

function rekap_absensi_siswa(int $siswa_id, string $dari, string $sampai): array
{
    $sql = "SELECT COALESCE(SUM(d.status = 'H'), 0) AS hadir, COALESCE(SUM(d.status = 'I'), 0) AS izin,
                   COALESCE(SUM(d.status = 'S'), 0) AS sakit, COALESCE(SUM(d.status = 'A'), 0) AS alpa,
                   COUNT(d.id) AS total
            FROM absensi_detail d JOIN absensi a ON a.id = d.absensi_id
            WHERE d.siswa_id = :siswa AND a.tanggal BETWEEN :dari AND :sampai";
    $st = db()->prepare($sql);
    $st->execute(['siswa' => $siswa_id, 'dari' => $dari, 'sampai' => $sampai]);
    $r = $st->fetch();
    foreach ($r as $k => $v) $r[$k] = (int) $v;
    $r['persen'] = persen($r['hadir'], $r['total']);
    return $r;
}

function detail_absensi_siswa(int $siswa_id, string $dari, string $sampai): array
{
    $st = db()->prepare("SELECT a.tanggal, d.status FROM absensi_detail d JOIN absensi a ON a.id = d.absensi_id
                         WHERE d.siswa_id = :siswa AND a.tanggal BETWEEN :dari AND :sampai ORDER BY a.tanggal DESC");
    $st->execute(['siswa' => $siswa_id, 'dari' => $dari, 'sampai' => $sampai]);
    return $st->fetchAll();
}

/* ---------- Poin, badge, kebiasaan ---------- */

function total_poin(int $siswa_id): int
{
    $st = db()->prepare('SELECT COALESCE(SUM(jumlah), 0) FROM poin WHERE siswa_id = :s');
    $st->execute(['s' => $siswa_id]);
    return (int) $st->fetchColumn();
}

// Mengembalikan semua badge + penanda diraih, dan badge berikutnya yang belum diraih
function daftar_badge(int $poin): array
{
    $semua = db()->query('SELECT * FROM badge ORDER BY minimal_poin')->fetchAll();
    $berikut = null;
    $jumlah_diraih = 0;
    foreach ($semua as &$b) {
        $b['diraih'] = $poin >= (int) $b['minimal_poin'];
        if ($b['diraih']) $jumlah_diraih++;
        elseif ($berikut === null) $berikut = $b;
    }
    return ['semua' => $semua, 'berikut' => $berikut, 'jumlah_diraih' => $jumlah_diraih];
}

// Ikon badge tersimpan sebagai emoji di database; ubah menjadi ikon Bootstrap.
function ikon_badge(string $ikon): string
{
    $peta = [
        '🌱' => 'bi-tree-fill',
        '⭐' => 'bi-star-fill',
        '🏅' => 'bi-medal-fill',
        '🏆' => 'bi-trophy-fill',
        '🌟' => 'bi-stars',
    ];
    $kelas = $peta[$ikon] ?? 'bi-award-fill';
    return '<i class="bi ' . $kelas . '"></i>';
}

// Jumlah kebiasaan selesai per hari (jurnal yang tidak ditolak), urut dari lama ke baru
function kebiasaan_per_hari(int $siswa_id, int $batas = 14): array
{
    $st = db()->prepare("SELECT j.tanggal, j.status, SUM(d.selesai) AS jumlah
                         FROM jurnal_kebiasaan j JOIN detail_jurnal_kebiasaan d ON d.jurnal_id = j.id
                         WHERE j.siswa_id = :s AND j.status <> 'ditolak'
                         GROUP BY j.id, j.tanggal, j.status ORDER BY j.tanggal DESC LIMIT :batas");
    $st->bindValue('s', $siswa_id, PDO::PARAM_INT);
    $st->bindValue('batas', $batas, PDO::PARAM_INT);
    $st->execute();
    return array_reverse($st->fetchAll());
}

function riwayat_jurnal(int $siswa_id, int $batas = 30): array
{
    $st = db()->prepare("SELECT j.id, j.tanggal, j.status, j.catatan_siswa, v.catatan, v.apresiasi,
                                (SELECT COALESCE(SUM(d.selesai), 0) FROM detail_jurnal_kebiasaan d WHERE d.jurnal_id = j.id) AS jumlah
                         FROM jurnal_kebiasaan j LEFT JOIN verifikasi_kebiasaan v ON v.jurnal_id = j.id
                         WHERE j.siswa_id = :s ORDER BY j.tanggal DESC LIMIT :batas");
    $st->bindValue('s', $siswa_id, PDO::PARAM_INT);
    $st->bindValue('batas', $batas, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

// Progress kebiasaan siswa = jumlah kebiasaan yang disetujui / (7 x jumlah hari pengisian kelas) pada periode
function progress_kebiasaan(int $siswa_id, int $kelas_id, string $dari, string $sampai): array
{
    $st = db()->prepare("SELECT COALESCE(SUM(d.selesai), 0) AS checklist, COUNT(DISTINCT j.id) AS jumlah_jurnal,
                                COALESCE(SUM(j.status = 'disetujui' AND d.selesai = 1), 0) AS disetujui
                         FROM jurnal_kebiasaan j JOIN detail_jurnal_kebiasaan d ON d.jurnal_id = j.id
                         WHERE j.siswa_id = :s AND j.tanggal BETWEEN :dari AND :sampai");
    $st->execute(['s' => $siswa_id, 'dari' => $dari, 'sampai' => $sampai]);
    $r = $st->fetch();

    $st2 = db()->prepare("SELECT COUNT(DISTINCT j.tanggal) FROM jurnal_kebiasaan j JOIN siswa s ON s.id = j.siswa_id
                          WHERE s.kelas_id = :k AND j.tanggal BETWEEN :dari AND :sampai");
    $st2->execute(['k' => $kelas_id, 'dari' => $dari, 'sampai' => $sampai]);
    $hari = (int) $st2->fetchColumn();

    $poin = db()->prepare('SELECT COALESCE(SUM(jumlah), 0) FROM poin WHERE siswa_id = :s AND tanggal BETWEEN :dari AND :sampai');
    $poin->execute(['s' => $siswa_id, 'dari' => $dari, 'sampai' => $sampai]);

    $disetujui = (int) $r['disetujui'];
    return [
        'checklist'     => (int) $r['checklist'],
        'jumlah_jurnal' => (int) $r['jumlah_jurnal'],
        'poin'          => (int) $poin->fetchColumn(),
        'hari'          => $hari,
        'progress'      => $hari > 0 ? min(100, persen($disetujui, $hari * 7)) : 0.0,
    ];
}

/* ---------- Nilai TKA ---------- */

function riwayat_nilai(int $siswa_id): array
{
    $st = db()->prepare("SELECT h.id, h.nilai, h.jumlah_benar, h.jumlah_salah, h.waktu_selesai, u.nama_ujian, m.nama_mapel
                         FROM hasil_ujian h JOIN ujian u ON u.id = h.ujian_id JOIN mata_pelajaran m ON m.id = u.mapel_id
                         WHERE h.siswa_id = :s AND h.status = 'selesai' ORDER BY h.waktu_selesai DESC");
    $st->execute(['s' => $siswa_id]);
    return $st->fetchAll();
}

function rata_rata_nilai(array $riwayat): float
{
    if (!$riwayat) return 0.0;
    return round(array_sum(array_column($riwayat, 'nilai')) / count($riwayat), 1);
}

/* ---------- Engine ujian (validasi waktu & penilaian otomatis) ---------- */

// Batas waktu pengerjaan = yang lebih awal antara (mulai siswa + durasi) dan jadwal selesai ujian
function batas_waktu_hasil(string $waktu_mulai, int $durasi_menit, string $jadwal_selesai): int
{
    return min(strtotime($waktu_mulai) + $durasi_menit * 60, strtotime($jadwal_selesai));
}

// Auto-grading: bandingkan jawaban dengan kunci, hitung benar/salah, nilai, lalu simpan.
// Nilai = jumlah benar / jumlah soal x 100
function selesaikan_ujian(int $hasil_id): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT h.*, u.durasi_menit, u.waktu_selesai AS jadwal_selesai
                             FROM hasil_ujian h JOIN ujian u ON u.id = h.ujian_id WHERE h.id = :id FOR UPDATE');
        $st->execute(['id' => $hasil_id]);
        $h = $st->fetch();
        if (!$h || $h['status'] === 'selesai') {
            $pdo->commit();
            return;
        }

        $st = $pdo->prepare('SELECT COUNT(*) FROM ujian_soal WHERE ujian_id = :u');
        $st->execute(['u' => $h['ujian_id']]);
        $total = (int) $st->fetchColumn();

        $st = $pdo->prepare('SELECT COUNT(*) FROM jawaban_siswa j JOIN bank_soal b ON b.id = j.soal_id
                             WHERE j.hasil_id = :h AND j.jawaban = b.kunci_jawaban');
        $st->execute(['h' => $hasil_id]);
        $benar = (int) $st->fetchColumn();

        $salah = $total - $benar;
        $nilai = $total > 0 ? round($benar / $total * 100, 2) : 0;
        $akhir = min(time(), batas_waktu_hasil($h['waktu_mulai'], (int) $h['durasi_menit'], $h['jadwal_selesai']));

        $st = $pdo->prepare("UPDATE hasil_ujian SET status = 'selesai', waktu_selesai = :akhir,
                             jumlah_benar = :benar, jumlah_salah = :salah, nilai = :nilai WHERE id = :id");
        $st->execute(['akhir' => date('Y-m-d H:i:s', $akhir), 'benar' => $benar, 'salah' => $salah, 'nilai' => $nilai, 'id' => $hasil_id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// Ujian yang waktunya sudah habis tetapi belum dikirim siswa diproses otomatis.
function finalisasi_kadaluarsa(?int $ujian_id = null, ?int $siswa_id = null): void
{
    $sql = "SELECT h.id, h.waktu_mulai, u.durasi_menit, u.waktu_selesai AS jadwal_selesai
            FROM hasil_ujian h JOIN ujian u ON u.id = h.ujian_id WHERE h.status = 'berlangsung'";
    $param = [];
    if ($ujian_id !== null) { $sql .= ' AND h.ujian_id = :ujian'; $param['ujian'] = $ujian_id; }
    if ($siswa_id !== null) { $sql .= ' AND h.siswa_id = :siswa'; $param['siswa'] = $siswa_id; }
    $st = db()->prepare($sql);
    $st->execute($param);
    foreach ($st->fetchAll() as $h) {
        if (time() >= batas_waktu_hasil($h['waktu_mulai'], (int) $h['durasi_menit'], $h['jadwal_selesai'])) {
            selesaikan_ujian((int) $h['id']);
        }
    }
}

// Opsi jawaban untuk beberapa soal sekaligus: [soal_id => ['A' => teks, 'B' => teks, ...]]
function ambil_opsi(array $soal_ids): array
{
    if (!$soal_ids) return [];
    $in = implode(',', array_fill(0, count($soal_ids), '?'));
    $st = db()->prepare("SELECT soal_id, label, teks FROM opsi_jawaban WHERE soal_id IN ($in) ORDER BY label");
    $st->execute(array_values($soal_ids));
    $hasil = [];
    foreach ($st->fetchAll() as $o) $hasil[$o['soal_id']][$o['label']] = $o['teks'];
    return $hasil;
}

// Daftar ujian untuk seorang siswa beserta kondisinya:
// belum_mulai | tersedia | berlangsung | selesai | berakhir
function ujian_untuk_siswa(array $siswa): array
{
    finalisasi_kadaluarsa(null, (int) $siswa['id']);
    $st = db()->prepare("SELECT u.*, m.nama_mapel, h.id AS hasil_id, h.status AS hasil_status, h.nilai,
                                (SELECT COUNT(*) FROM ujian_soal us WHERE us.ujian_id = u.id) AS jml_soal
                         FROM ujian u JOIN mata_pelajaran m ON m.id = u.mapel_id
                         LEFT JOIN hasil_ujian h ON h.ujian_id = u.id AND h.siswa_id = :s
                         WHERE u.kelas_id = :k AND u.status = 'aktif' ORDER BY u.waktu_mulai DESC");
    $st->execute(['s' => $siswa['id'], 'k' => $siswa['kelas_id']]);
    $now = date('Y-m-d H:i:s');
    $daftar = $st->fetchAll();
    foreach ($daftar as &$u) {
        if ($u['hasil_status'] === 'selesai') $u['kondisi'] = 'selesai';
        elseif ($u['hasil_status'] === 'berlangsung') $u['kondisi'] = 'berlangsung';
        elseif ($now < $u['waktu_mulai']) $u['kondisi'] = 'belum_mulai';
        elseif ($now > $u['waktu_selesai']) $u['kondisi'] = 'berakhir';
        else $u['kondisi'] = 'tersedia';
    }
    return $daftar;
}

// Data pengerjaan ujian milik siswa (null bila bukan miliknya)
function ambil_hasil_siswa(int $hasil_id, int $siswa_id): ?array
{
    $st = db()->prepare('SELECT h.*, u.nama_ujian, u.durasi_menit, u.waktu_selesai AS jadwal_selesai, m.nama_mapel
                         FROM hasil_ujian h JOIN ujian u ON u.id = h.ujian_id JOIN mata_pelajaran m ON m.id = u.mapel_id
                         WHERE h.id = :h AND h.siswa_id = :s');
    $st->execute(['h' => $hasil_id, 's' => $siswa_id]);
    return $st->fetch() ?: null;
}

// Simpan / perbarui satu jawaban. Soal harus termasuk dalam ujian tersebut.
function simpan_jawaban(array $hasil, int $soal_id, ?string $jawaban, int $ragu): bool
{
    if ($jawaban !== null && !in_array($jawaban, ['A', 'B', 'C', 'D'], true)) return false;
    if (!skalar('SELECT COUNT(*) FROM ujian_soal WHERE ujian_id = :u AND soal_id = :s', ['u' => $hasil['ujian_id'], 's' => $soal_id])) return false;
    db()->prepare('INSERT INTO jawaban_siswa (hasil_id, soal_id, jawaban, ragu) VALUES (:h, :s, :j, :r)
                   ON DUPLICATE KEY UPDATE jawaban = VALUES(jawaban), ragu = VALUES(ragu)')
        ->execute(['h' => $hasil['id'], 's' => $soal_id, 'j' => $jawaban, 'r' => $ragu ? 1 : 0]);
    return true;
}

function ikon_kebiasaan(int $id): string
{
    return ['<i class="bi bi-sunrise-fill"></i>', '<i class="bi bi-heart-fill"></i>', '<i class="bi bi-person-running"></i>', '<i class="bi bi-egg-fried"></i>', '<i class="bi bi-book-fill"></i>', '<i class="bi bi-people-fill"></i>', '<i class="bi bi-moon-fill"></i>'][($id - 1) % 7];
}

/* ---------- Upload gambar ---------- */

// Validasi & simpan gambar unggahan ke folder tujuan, lalu kembalikan nama file acak.
// Melempar RuntimeException bila bukan gambar (JPG/PNG/WEBP/GIF) atau melebihi batas ukuran.
function simpan_gambar(string $dir, array $berkas): string
{
    if ($berkas['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload gambar gagal. Coba lagi.');
    if ($berkas['size'] > UPLOAD_MAKS_BYTE) throw new RuntimeException('Ukuran gambar maksimal 1 MB.');
    $info = @getimagesize($berkas['tmp_name']);
    $ekstensi = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!$info || !isset($ekstensi[$info['mime']])) throw new RuntimeException('Gambar harus berformat JPG, PNG, WEBP, atau GIF.');
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $nama = bin2hex(random_bytes(8)) . '.' . $ekstensi[$info['mime']];
    if (!move_uploaded_file($berkas['tmp_name'], $dir . $nama)) throw new RuntimeException('Gambar gagal disimpan di server.');
    return $nama;
}

/* ---------- Orang tua ---------- */

function daftar_anak(int $ortu_id): array
{
    $st = db()->prepare("SELECT s.id, s.nisn, s.nama, s.kelas_id, s.jenis_kelamin, k.nama_kelas
                         FROM siswa s JOIN kelas k ON k.id = s.kelas_id
                         WHERE s.orang_tua_id = :o AND s.status = 'aktif' ORDER BY s.nama");
    $st->execute(['o' => $ortu_id]);
    return $st->fetchAll();
}

// Anak yang sedang dipantau. Hanya anak milik orang tua yang login yang bisa dipilih.
function pilih_anak(): array
{
    $u = wajib_role(['orangtua']);
    $anak = daftar_anak((int) $u['profil_id']);
    $dipilih = null;
    $id = (int) ($_GET['anak'] ?? ($_SESSION['anak_id'] ?? 0));
    foreach ($anak as $a) {
        if ((int) $a['id'] === $id) $dipilih = $a;
    }
    if ($dipilih === null && $anak) $dipilih = $anak[0];
    if ($dipilih) $_SESSION['anak_id'] = (int) $dipilih['id'];
    return [$dipilih, $anak];
}

function html_pilih_anak(array $anak, ?array $dipilih): string
{
    if (count($anak) < 2) return '';
    $h = '<form method="get" class="toolbar"><div class="form-grup"><label for="anak">Pilih Anak</label><select name="anak" id="anak" onchange="this.form.submit()">';
    foreach ($anak as $a) {
        $sel = $dipilih && (int) $a['id'] === (int) $dipilih['id'] ? ' selected' : '';
        $h .= '<option value="' . (int) $a['id'] . '"' . $sel . '>' . e($a['nama']) . ' (' . e($a['nama_kelas']) . ')</option>';
    }
    return $h . '</select></div></form>';
}

/* ---------- Export CSV (bisa dibuka di Excel) ---------- */

function unduh_csv(string $nama_file, array $judul, array $baris): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nama_file . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fwrite($out, "sep=;\r\n");
    $aman = function ($v) {
        // Cegah rumus Excel berbahaya dari data yang diawali = + - @
        return is_string($v) && $v !== '' && strpbrk($v[0], '=+-@') !== false ? "'" . $v : $v;
    };
    fputcsv($out, $judul, ';');
    foreach ($baris as $b) fputcsv($out, array_map($aman, $b), ';');
    fclose($out);
    exit;
}
