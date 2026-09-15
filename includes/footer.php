        </main>
    </div>
</div>
<script src="assets/js/app.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<?php if (in_array(($section ?? ''), ['invoices', 'invoices-quick'], true)): ?><script src="assets/js/invoices.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/invoices.js') ?>"></script><?php endif; ?>
<link rel="stylesheet" href="assets/css/receipt-print.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/receipt-print.css') ?>">
<script src="assets/js/receipt-print.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/receipt-print.js') ?>"></script>
</body>
</html>
