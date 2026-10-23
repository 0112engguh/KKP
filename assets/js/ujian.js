// assets/js/ujian.js
// Mesin ujian di sisi browser: timer hitung mundur, navigasi soal, tandai ragu-ragu, simpan otomatis.
// Waktu tetap divalidasi ulang oleh server (PHP), jadi timer ini hanya tampilan + pengirim otomatis.

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('formUjian');
    if (!form) return;

    var panelSoal = Array.prototype.slice.call(form.querySelectorAll('.soal-panel'));
    var tombolNav = Array.prototype.slice.call(form.querySelectorAll('.nav-soal button'));
    var tombolSebelumnya = document.getElementById('tombolSebelumnya');
    var tombolBerikutnya = document.getElementById('tombolBerikutnya');
    var statusSimpan = document.getElementById('statusSimpan');
    var elTimer = document.getElementById('timer');
    var urlSimpan = form.getAttribute('data-url-simpan');
    var idHasil = form.getAttribute('data-hasil');
    var tokenCsrf = form.getAttribute('data-csrf');
    var indeks = 0;
    var sudahKirim = false;

    // ---------- Navigasi ----------
    function tampilkan(i) {
        indeks = Math.max(0, Math.min(i, panelSoal.length - 1));
        panelSoal.forEach(function (p, n) { p.classList.toggle('tampil', n === indeks); });
        tombolSebelumnya.disabled = indeks === 0;
        tombolBerikutnya.disabled = indeks === panelSoal.length - 1;
        perbaruiNav();
    }

    function perbaruiNav() {
        panelSoal.forEach(function (p, n) {
            var dijawab = p.querySelector('input[type=radio]:checked') !== null;
            var ragu = p.querySelector('.cek-ragu').checked;
            tombolNav[n].classList.toggle('dijawab', dijawab && !ragu);
            tombolNav[n].classList.toggle('ragu', ragu);
            tombolNav[n].classList.toggle('sekarang', n === indeks);
        });
    }

    tombolSebelumnya.addEventListener('click', function () { tampilkan(indeks - 1); });
    tombolBerikutnya.addEventListener('click', function () { tampilkan(indeks + 1); });
    tombolNav.forEach(function (t, n) { t.addEventListener('click', function () { tampilkan(n); }); });

    // ---------- Simpan otomatis (AJAX) ----------
    function simpan(panel) {
        var terpilih = panel.querySelector('input[type=radio]:checked');
        var data = new FormData();
        data.append('csrf', tokenCsrf);
        data.append('hasil_id', idHasil);
        data.append('soal_id', panel.getAttribute('data-soal'));
        data.append('jawaban', terpilih ? terpilih.value : '');
        data.append('ragu', panel.querySelector('.cek-ragu').checked ? '1' : '0');
        statusSimpan.textContent = 'Menyimpan...';

        fetch(urlSimpan, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.kadaluarsa) { sudahKirim = true; window.location.href = j.redirect; return; }
                statusSimpan.innerHTML = j.ok ? '<i class="bi bi-check-lg"></i> Tersimpan' : 'Gagal menyimpan, jawaban tetap dikirim saat submit.';
            })
            .catch(function () { statusSimpan.textContent = 'Koneksi bermasalah. Jawaban tetap dikirim saat submit.'; });
    }

    panelSoal.forEach(function (panel) {
        panel.addEventListener('change', function () { perbaruiNav(); simpan(panel); });
    });

    // ---------- Timer ----------
    var akhir = Date.now() + Number(form.getAttribute('data-sisa')) * 1000;

    function dua(n) { return n < 10 ? '0' + n : '' + n; }

    function jalankanTimer() {
        var sisa = Math.max(0, Math.round((akhir - Date.now()) / 1000));
        var jam = Math.floor(sisa / 3600), menit = Math.floor((sisa % 3600) / 60), detik = sisa % 60;
        elTimer.textContent = (jam > 0 ? jam + ':' : '') + dua(menit) + ':' + dua(detik);
        elTimer.classList.toggle('hampir', sisa <= 300 && sisa > 60);
        elTimer.classList.toggle('kritis', sisa <= 60);
        if (sisa <= 0 && !sudahKirim) {
            sudahKirim = true;
            elTimer.textContent = '00:00';
            statusSimpan.textContent = 'Waktu habis, jawaban dikirim...';
            form.submit();      // kirim otomatis
        }
    }
    jalankanTimer();
    var pewaktu = setInterval(jalankanTimer, 1000);

    // ---------- Kirim manual ----------
    form.addEventListener('submit', function (ev) {
        if (sudahKirim) return; // pengiriman otomatis saat waktu habis, tanpa konfirmasi
        var kosong = panelSoal.filter(function (p) { return p.querySelector('input[type=radio]:checked') === null; }).length;
        var ragu = panelSoal.filter(function (p) { return p.querySelector('.cek-ragu').checked; }).length;
        var pesan = 'Kirim jawaban sekarang?';
        if (kosong > 0 || ragu > 0) {
            pesan = 'Masih ada ' + kosong + ' soal belum dijawab dan ' + ragu + ' soal ditandai ragu-ragu. Kirim jawaban sekarang?';
        }
        if (!window.confirm(pesan)) { ev.preventDefault(); return; }
        sudahKirim = true;
        clearInterval(pewaktu);
    });

    tampilkan(0);
});
