<?php
// includes/sidebar.php
// Menu samping sesuai role. Item: [label, file, ikon, [file lain yang ikut menandai menu aktif]]

function menu_sidebar(string $role): array
{
    if ($role === 'siswa') {
        return [
            ['Dashboard', 'siswa/dashboard.php', '<i class="bi bi-house-fill"></i>'],
            ['Rekap Kehadiran', 'siswa/kehadiran.php', '<i class="bi bi-calendar3"></i>'],
            ['Ujian TKA', null, null],
            ['Ujian Aktif', 'siswa/ujian.php', '<i class="bi bi-pencil-square"></i>', ['siswa/ujian-info.php', 'siswa/ujian-kerjakan.php']],
            ['History Nilai', 'siswa/nilai.php', '<i class="bi bi-bar-chart-fill"></i>', ['siswa/ujian-hasil.php']],
            ['7 Kebiasaan', null, null],
            ['Checklist Harian', 'siswa/kebiasaan.php', '<i class="bi bi-check2-square"></i>'],
            ['History', 'siswa/kebiasaan-history.php', '<i class="bi bi-journal-text"></i>'],
            ['Poin &amp; Badge', 'siswa/badge.php', '<i class="bi bi-award-fill"></i>'],
        ];
    }
    if ($role === 'orangtua') {
        return [
            ['Dashboard', 'orangtua/dashboard.php', '<i class="bi bi-house-fill"></i>'],
            ['Absensi Anak', 'orangtua/absensi.php', '<i class="bi bi-calendar3"></i>'],
            ['Nilai TKA', 'orangtua/nilai.php', '<i class="bi bi-bar-chart-fill"></i>'],
            ['Kebiasaan Anak', 'orangtua/kebiasaan.php', '<i class="bi bi-star-fill"></i>'],
        ];
    }
    // admin & guru
    $menu = [
        ['Dashboard', 'guru/dashboard.php', '<i class="bi bi-house-fill"></i>'],
        ['Data Master', null, null],
        ['Siswa', 'guru/siswa.php', '<i class="bi bi-person-fill"></i>', ['guru/siswa-form.php']],
    ];
    if ($role === 'admin') {
        $menu[] = ['Guru', 'guru/guru.php', '<i class="bi bi-person-workspace"></i>', ['guru/guru-form.php']];
    }
    return array_merge($menu, [
        ['Kelas', 'guru/kelas.php', '<i class="bi bi-building"></i>'],
        ['Mata Pelajaran', 'guru/mapel.php', '<i class="bi bi-book-fill"></i>'],
        ['Absensi Kelas', null, null],
        ['Input Absensi', 'guru/absensi.php', '<i class="bi bi-pen-fill"></i>'],
        ['Rekap Absensi', 'guru/absensi-rekap.php', '<i class="bi bi-calendar-week-fill"></i>'],
        ['TKA', null, null],
        ['Bank Soal', 'guru/bank-soal.php', '<i class="bi bi-puzzle-fill"></i>', ['guru/bank-soal-form.php', 'guru/kelompok-form.php', 'guru/bank-soal-detail.php']],
        ['Ujian', 'guru/ujian.php', '<i class="bi bi-pencil-square"></i>', ['guru/ujian-form.php']],
        ['Hasil Ujian', 'guru/hasil-ujian.php', '<i class="bi bi-bar-chart-fill"></i>'],
        ['Analisis Soal', 'guru/analisis-soal.php', '<i class="bi bi-search"></i>'],
        ['7 Kebiasaan', null, null],
        ['Jurnal', 'guru/jurnal.php?status=semua', '<i class="bi bi-journal-text"></i>'],
        ['Verifikasi', 'guru/jurnal.php?status=menunggu', '<i class="bi bi-check-circle-fill"></i>', ['guru/jurnal-detail.php']],
        ['Laporan', null, null],
        ['Laporan', 'laporan/index.php', '<i class="bi bi-printer-fill"></i>', ['laporan/absensi.php', 'laporan/tka.php', 'laporan/kebiasaan.php', 'laporan/terpadu.php']],
    ]);
}

function menu_aktif(array $item): bool
{
    $sekarang = ltrim(substr($_SERVER['SCRIPT_NAME'], strlen(BASE_URL)), '/');
    [$file, $query] = array_pad(explode('?', $item[1], 2), 2, '');

    if ($file === $sekarang) {
        // Item dengan query (mis. ?status=menunggu) hanya aktif bila query cocok
        parse_str($query, $syarat);
        foreach ($syarat as $k => $v) {
            if (($_GET[$k] ?? 'semua') !== $v) return false;
        }
        return true;
    }
    return isset($item[3]) && in_array($sekarang, $item[3], true);
}
?>
<aside class="sidebar" id="sidebar">
    <nav>
        <?php foreach (menu_sidebar($pengguna['role']) as $item): ?>
            <?php if ($item[1] === null): ?>
                <div class="menu-judul"><?= e($item[0]) ?></div>
            <?php else: ?>
                <a class="menu-item<?= menu_aktif($item) ? ' aktif' : '' ?>" href="<?= e(url($item[1])) ?>">
                    <span class="menu-ikon" aria-hidden="true"><?= $item[2] ?></span><?= e($item[0]) ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</aside>
<div class="sidebar-latar" id="sidebarLatar"></div>
