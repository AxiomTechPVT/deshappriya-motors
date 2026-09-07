        </main>
    </div>
</div>
<script src="assets/js/app.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<?php if (in_array(($section ?? ''), ['invoices', 'invoices-quick'], true)): ?><script src="assets/js/invoices.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/invoices.js') ?>"></script><?php endif; ?>
</body>
</html>
