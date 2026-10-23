// assets/js/grafik.js
// Grafik sederhana memakai SVG murni (tanpa library).
// Cara pakai di HTML:
//   <div class="grafik" data-grafik="bar|garis|donat"
//        data-labels='["A","B"]' data-values='[10,20]' data-warna='["#16a34a","#2563eb"]'
//        data-maks="100" data-satuan="%"></div>

(function () {
    var NS = 'http://www.w3.org/2000/svg';
    var WARNA = ['#16a34a', '#2563eb', '#eab308', '#dc2626', '#8b5cf6', '#0891b2'];

    function el(nama, atribut, teks) {
        var e = document.createElementNS(NS, nama);
        for (var k in atribut) e.setAttribute(k, atribut[k]);
        if (teks !== undefined) e.textContent = teks;
        return e;
    }

    function svgDasar(lebar, tinggi) {
        return el('svg', { viewBox: '0 0 ' + lebar + ' ' + tinggi, role: 'img' });
    }

    function pendek(teks, n) {
        teks = String(teks);
        return teks.length > n ? teks.slice(0, n - 1) + '…' : teks;
    }

    // Sumbu Y + garis bantu. Mengembalikan fungsi konversi nilai -> posisi y
    function gambarSumbu(svg, maks, kiri, atas, bawah, lebar, satuan) {
        var langkah = 4;
        for (var i = 0; i <= langkah; i++) {
            var nilai = maks * i / langkah;
            var y = bawah - (bawah - atas) * i / langkah;
            svg.appendChild(el('line', { x1: kiri, x2: lebar - 10, y1: y, y2: y, stroke: '#e5e7eb' }));
            svg.appendChild(el('text', { x: kiri - 6, y: y + 4, 'text-anchor': 'end', 'font-size': 11 }, Math.round(nilai * 10) / 10 + (satuan || '')));
        }
        return function (v) { return bawah - (bawah - atas) * (v / maks); };
    }

    function grafikBar(wadah, labels, values, warna, maks, satuan) {
        var L = 560, T = 240, kiri = 42, atas = 18, bawah = 190;
        var svg = svgDasar(L, T);
        var y = gambarSumbu(svg, maks, kiri, atas, bawah, L, satuan);
        var lebarSlot = (L - kiri - 10) / labels.length;
        var lebarBar = Math.min(48, lebarSlot * 0.6);
        labels.forEach(function (lab, i) {
            var x = kiri + lebarSlot * i + (lebarSlot - lebarBar) / 2;
            var tinggi = bawah - y(values[i]);
            var warnaBar = Array.isArray(warna) ? (warna[i] || warna[0]) : WARNA[0];
            var batang = el('rect', { x: x, y: y(values[i]), width: lebarBar, height: Math.max(tinggi, 0), rx: 5, fill: warnaBar });
            batang.appendChild(el('title', {}, lab + ': ' + values[i] + (satuan || '')));
            svg.appendChild(batang);
            svg.appendChild(el('text', { x: x + lebarBar / 2, y: y(values[i]) - 5, 'text-anchor': 'middle', 'font-size': 11, 'font-weight': 700 }, values[i]));
            svg.appendChild(el('text', { x: x + lebarBar / 2, y: bawah + 16, 'text-anchor': 'middle', 'font-size': 11 }, pendek(lab, labels.length > 8 ? 5 : 10)));
        });
        wadah.appendChild(svg);
    }

    function grafikGaris(wadah, labels, values, warna, maks, satuan) {
        var L = 560, T = 240, kiri = 42, atas = 18, bawah = 190;
        var svg = svgDasar(L, T);
        var y = gambarSumbu(svg, maks, kiri, atas, bawah, L, satuan);
        var lebarSlot = (L - kiri - 50) / Math.max(labels.length - 1, 1);
        var titik = labels.map(function (lab, i) {
            return [labels.length === 1 ? (kiri + L) / 2 : kiri + 25 + lebarSlot * i, y(values[i])];
        });
        var warnaGaris = Array.isArray(warna) ? warna[0] : WARNA[1];
        if (titik.length > 1) {
            svg.appendChild(el('polyline', { points: titik.map(function (p) { return p.join(','); }).join(' '), fill: 'none', stroke: warnaGaris, 'stroke-width': 3, 'stroke-linejoin': 'round' }));
        }
        titik.forEach(function (p, i) {
            var c = el('circle', { cx: p[0], cy: p[1], r: 5, fill: '#fff', stroke: warnaGaris, 'stroke-width': 3 });
            c.appendChild(el('title', {}, labels[i] + ': ' + values[i] + (satuan || '')));
            svg.appendChild(c);
            svg.appendChild(el('text', { x: p[0], y: p[1] - 10, 'text-anchor': 'middle', 'font-size': 11, 'font-weight': 700 }, values[i]));
            svg.appendChild(el('text', { x: p[0], y: bawah + 16, 'text-anchor': 'middle', 'font-size': 11 }, pendek(labels[i], labels.length > 6 ? 6 : 12)));
        });
        wadah.appendChild(svg);
    }

    function grafikDonat(wadah, labels, values, warna) {
        var total = values.reduce(function (a, b) { return a + b; }, 0);
        var svg = svgDasar(220, 220);
        var cx = 110, cy = 110, r = 80, tebal = 32, keliling = 2 * Math.PI * r;
        svg.appendChild(el('circle', { cx: cx, cy: cy, r: r, fill: 'none', stroke: '#e5e7eb', 'stroke-width': tebal }));
        var mulai = 0;
        values.forEach(function (v, i) {
            if (total === 0 || v === 0) return;
            var panjang = keliling * v / total;
            var arc = el('circle', {
                cx: cx, cy: cy, r: r, fill: 'none', stroke: (warna && warna[i]) || WARNA[i % WARNA.length],
                'stroke-width': tebal, 'stroke-dasharray': panjang + ' ' + (keliling - panjang),
                'stroke-dashoffset': -mulai, transform: 'rotate(-90 ' + cx + ' ' + cy + ')'
            });
            arc.appendChild(el('title', {}, labels[i] + ': ' + v));
            svg.appendChild(arc);
            mulai += panjang;
        });
        svg.appendChild(el('text', { x: cx, y: cy + 6, 'text-anchor': 'middle', 'font-size': 22, 'font-weight': 800 }, total));
        svg.appendChild(el('text', { x: cx, y: cy + 24, 'text-anchor': 'middle', 'font-size': 11 }, 'total'));
        svg.style.maxWidth = '240px';
        svg.style.margin = '0 auto';
        wadah.appendChild(svg);

        var legenda = document.createElement('div');
        legenda.className = 'legenda';
        labels.forEach(function (lab, i) {
            var item = document.createElement('span');
            var kotak = document.createElement('i');
            kotak.style.background = (warna && warna[i]) || WARNA[i % WARNA.length];
            item.appendChild(kotak);
            item.appendChild(document.createTextNode(lab + ' (' + values[i] + ')'));
            legenda.appendChild(item);
        });
        wadah.appendChild(legenda);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-grafik]').forEach(function (wadah) {
            var labels, values, warna;
            try {
                labels = JSON.parse(wadah.getAttribute('data-labels') || '[]');
                values = JSON.parse(wadah.getAttribute('data-values') || '[]').map(Number);
                warna = JSON.parse(wadah.getAttribute('data-warna') || 'null');
            } catch (e) { return; }
            if (!labels.length) {
                wadah.innerHTML = '<div class="kosong">Belum ada data untuk ditampilkan.</div>';
                return;
            }
            var satuan = wadah.getAttribute('data-satuan') || '';
            var maks = Number(wadah.getAttribute('data-maks')) || Math.max.apply(null, values.concat([1]));
            var jenis = wadah.getAttribute('data-grafik');
            if (jenis === 'bar') grafikBar(wadah, labels, values, warna, maks, satuan);
            else if (jenis === 'garis') grafikGaris(wadah, labels, values, warna, maks, satuan);
            else if (jenis === 'donat') grafikDonat(wadah, labels, values, warna);
        });
    });
})();
