// assets/js/app.js
// JavaScript umum: menu mobile, konfirmasi hapus, filter periode, tombol cetak.

document.addEventListener('DOMContentLoaded', function () {
    // Buka/tutup sidebar di layar kecil
    var tombolMenu = document.getElementById('tombolMenu');
    var sidebar = document.getElementById('sidebar');
    var latar = document.getElementById('sidebarLatar');
    function toggleMenu() {
        if (!sidebar) return;
        sidebar.classList.toggle('buka');
        latar.classList.toggle('buka');
    }
    if (tombolMenu) tombolMenu.addEventListener('click', toggleMenu);
    if (latar) latar.addEventListener('click', toggleMenu);

    // Konfirmasi sebelum menghapus: <form data-konfirmasi="Pesan ...">
    document.querySelectorAll('[data-konfirmasi]').forEach(function (el) {
        el.addEventListener('submit', function (ev) {
            if (!window.confirm(el.getAttribute('data-konfirmasi'))) ev.preventDefault();
        });
    });

    // Tombol cetak: <button data-cetak>
    document.querySelectorAll('[data-cetak]').forEach(function (el) {
        el.addEventListener('click', function () { window.print(); });
    });

    // Filter periode: tampilkan kolom sesuai jenis rekap (harian / bulanan / semester)
    var jenis = document.querySelector('[data-periode-jenis]');
    if (jenis) {
        var atur = function () {
            document.querySelectorAll('[data-periode]').forEach(function (el) {
                el.style.display = el.getAttribute('data-periode') === jenis.value ? '' : 'none';
            });
        };
        jenis.addEventListener('change', atur);
        atur();
    }

    // Tombol "Tandai semua": <button data-set-semua="H"> memilih radio bernilai H pada form yang sama
    document.querySelectorAll('[data-set-semua]').forEach(function (tombol) {
        tombol.addEventListener('click', function () {
            var nilai = tombol.getAttribute('data-set-semua');
            tombol.closest('form').querySelectorAll('input[type=radio][value="' + nilai + '"]').forEach(function (r) { r.checked = true; });
        });
    });

    // Pesan flash hilang sendiri setelah beberapa detik (kecuali pesan bahaya)
    document.querySelectorAll('.flash-success, .flash-info').forEach(function (el) {
        setTimeout(function () { el.style.display = 'none'; }, 6000);
    });
});
