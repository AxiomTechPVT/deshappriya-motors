// Print only the receipt's layout; hidden dashboard content must not add paper length.
(() => {
    const receipt = document.querySelector('.thermal-receipt, .thermal-invoice');
    if (!receipt) return;
    const pageStyle = document.createElement('style');
    pageStyle.id = 'receipt-page-size';
    document.head.appendChild(pageStyle);
    const prepare = () => {
        document.body.classList.add('receipt-print-mode');
        receipt.classList.add('receipt-print-target');
        for (let parent = receipt.parentElement; parent; parent = parent.parentElement) parent.classList.add('receipt-print-path');
        // CSS pixels use 96px per inch. Allow 2mm after the last printed line.
        const height = Math.ceil(Math.max(receipt.scrollHeight, receipt.getBoundingClientRect().height) * 25.4 / 96 + 2);
        pageStyle.textContent = `@media print { @page { size: 80mm ${Math.max(30, height)}mm; margin: 0; } }`;
    };
    const restore = () => {
        document.body.classList.remove('receipt-print-mode');
        document.querySelectorAll('.receipt-print-path, .receipt-print-target').forEach(node => node.classList.remove('receipt-print-path', 'receipt-print-target'));
        pageStyle.textContent = '';
    };
    window.addEventListener('beforeprint', prepare);
    window.addEventListener('afterprint', restore);
})();
