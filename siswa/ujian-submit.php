<?php
// siswa/ujian-submit.php
// Menerima pengiriman jawaban (manual atau otomatis saat waktu habis), menyimpan semua jawaban,
// lalu menjalankan auto-grading.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['siswa']);
$siswa = siswa_login();
if (!$siswa || $_SERVER['REQUEST_METHOD'] !== 'POST') redirect(url('siswa/ujian.php'));
csrf_cek();

$hasil_id = (int) ($_POST['hasil_id'] ?? 0);
$hasil = ambil_hasil_siswa($hasil_id, (int) $siswa['id']);
if (!$hasil) {
    flash('danger', 'Data tidak ditemukan.');
    redirect(url('siswa/ujian.php'));
}

if ($hasil['status'] === 'berlangsung') {
    $batas = batas_waktu_hasil($hasil['waktu_mulai'], (int) $hasil['durasi_menit'], $hasil['jadwal_selesai']);
    // Toleransi 30 detik untuk pengiriman otomatis saat timer habis (jaringan lambat)
    if (time() <= $batas + 30) {
        $jawab = is_array($_POST['jawab'] ?? null) ? $_POST['jawab'] : [];
        $ragu  = is_array($_POST['ragu'] ?? null) ? $_POST['ragu'] : [];
        $semua_soal = db()->prepare('SELECT soal_id FROM ujian_soal WHERE ujian_id = :u');
        $semua_soal->execute(['u' => $hasil['ujian_id']]);
        foreach ($semua_soal->fetchAll() as $s) {
            $sid = (int) $s['soal_id'];
            $j = isset($jawab[$sid]) && $jawab[$sid] !== '' ? (string) $jawab[$sid] : null;
            simpan_jawaban($hasil, $sid, $j, isset($ragu[$sid]) ? 1 : 0);
        }
    }
    selesaikan_ujian($hasil_id);
    flash('success', 'Jawaban berhasil dikirim dan sudah dinilai.');
}
redirect(url('siswa/ujian-hasil.php?id=' . $hasil_id));
