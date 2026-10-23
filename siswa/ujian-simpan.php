<?php
// siswa/ujian-simpan.php
// Endpoint AJAX: menyimpan satu jawaban. Mengembalikan JSON.
// Jika waktu ujian sudah habis, ujian langsung diproses dan JSON berisi alamat halaman hasil.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';

$u = user();
if (!$u || $u['role'] !== 'siswa') kirim_json(['ok' => false, 'pesan' => 'Akses ditolak.'], 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') kirim_json(['ok' => false, 'pesan' => 'Metode tidak valid.'], 405);
if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) kirim_json(['ok' => false, 'pesan' => 'Sesi tidak valid.'], 403);

$hasil_id = (int) ($_POST['hasil_id'] ?? 0);
$hasil = ambil_hasil_siswa($hasil_id, (int) $u['profil_id']);   // hanya milik siswa yang login
if (!$hasil) kirim_json(['ok' => false, 'pesan' => 'Data tidak ditemukan.'], 404);

if ($hasil['status'] === 'selesai' || time() >= batas_waktu_hasil($hasil['waktu_mulai'], (int) $hasil['durasi_menit'], $hasil['jadwal_selesai'])) {
    selesaikan_ujian($hasil_id);
    kirim_json(['ok' => false, 'kadaluarsa' => true, 'redirect' => url('siswa/ujian-hasil.php?id=' . $hasil_id)]);
}

$jawaban = (string) ($_POST['jawaban'] ?? '');
$ok = simpan_jawaban($hasil, (int) ($_POST['soal_id'] ?? 0), $jawaban === '' ? null : $jawaban, (int) ($_POST['ragu'] ?? 0));
kirim_json(['ok' => $ok]);
