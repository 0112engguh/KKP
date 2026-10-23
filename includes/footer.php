<?php
// includes/footer.php
// Penutup halaman + pemuatan JavaScript.
// $js_tambahan -> array nama file JS di assets/js/ (opsional), mis. ['ujian.js']
$js_tambahan = $js_tambahan ?? [];
?>
    </main>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<script src="<?= e(url('assets/js/grafik.js')) ?>"></script>
<?php foreach ($js_tambahan as $berkas): ?>
<script src="<?= e(url('assets/js/' . $berkas)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
