<?php
$printMode = true;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Report') ?> | Deshappriya Motors</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
    <?php if (strpos((string) ($section ?? ''), 'reports-') === 0): ?>
        <link href="assets/css/reports.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/reports.css') ?>" rel="stylesheet">
        <link href="assets/css/reports-overrides.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/reports-overrides.css') ?>" rel="stylesheet">
    <?php endif; ?>
    <style>
        body { background:#f4f6f8; }
        .print-shell { max-width:1280px; margin:0 auto; padding:24px; }
        .print-header { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; margin-bottom:20px; }
        .print-header h1 { margin:0 0 6px; font-size:28px; }
        .print-header p { margin:0; color:#667085; }
        .print-meta { color:#667085; font-size:12px; text-align:right; }
        @media print {
            @page { size: landscape; margin: 10mm; }
            body { background:#fff; }
            .print-shell { padding:0; max-width:none; }
            .no-print { display:none !important; }
            .report-sales-visuals { break-inside:avoid; }
            .report-table-panel { break-inside:auto; }

            /* Override the narrow receipt print rules from the main stylesheet. */
            html.report-print-page,
            body.report-print-page {
                width:100% !important;
                min-width:0 !important;
                margin:0 !important;
                background:#fff !important;
            }

            body.report-print-page * {
                visibility:visible !important;
            }

            body.report-print-page .no-print,
            body.report-print-page .report-filter-panel,
            body.report-print-page .report-actions,
            body.report-print-page .report-table-search,
            body.report-print-page .report-pagination,
            body.report-print-page .report-chart-select,
            body.report-print-page .report-chart-legend {
                display:none !important;
            }

            body.report-print-page .print-shell {
                display:block !important;
                width:100vw !important;
                box-sizing:border-box !important;
                max-width:none !important;
                padding:0 !important;
                margin:0 !important;
            }

            body.report-print-page .report-page-heading,
            body.report-print-page .report-cards,
            body.report-print-page .report-sales-visuals,
            body.report-print-page .report-table-panel,
            body.report-print-page .report-chart-panel,
            body.report-print-page .report-breakdown-panel,
            body.report-print-page .report-mini-table {
                width:100% !important;
                max-width:none !important;
                box-sizing:border-box !important;
            }

            body.report-print-page .report-sales-visuals {
                display:grid !important;
                grid-template-columns:1.2fr .8fr !important;
                gap:12px !important;
            }

            body.report-print-page .report-table-panel,
            body.report-print-page .table-responsive {
                overflow:visible !important;
                width:100% !important;
            }

            body.report-print-page .report-table {
                width:100% !important;
                min-width:0 !important;
            }
        }
    </style>
</head>
<body class="report-print-page">
<main class="print-shell">
    <?php require __DIR__ . '/reports.php'; ?>
</main>
<?php if (($_GET['mode'] ?? '') === 'print'): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
<?php endif; ?>
</body>
</html>
