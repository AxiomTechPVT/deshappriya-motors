(() => {
    const dialog = document.querySelector('#collect-payment-dialog');
    if (!dialog) return;
    const form = dialog.querySelector('[data-collect-payment-form]');
    const amount = form.querySelector('[name="amount_received"]');
    const submit = form.querySelector('[type="submit"]');
    document.querySelectorAll('[data-collect-payment]').forEach(button => {
        button.addEventListener('click', () => {
            form.reset();
            submit.disabled = false;
            form.action = `index.php?page=admin&section=invoices-payment&id=${encodeURIComponent(button.dataset.invoiceId)}`;
            dialog.querySelector('[data-payment-description]').textContent = `${button.dataset.customer} / ${button.dataset.invoiceNo}`;
            dialog.querySelector('[data-payment-due]').textContent = `Rs. ${Number(button.dataset.due).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
            amount.max = button.dataset.due;
            dialog.showModal();
            amount.focus();
        });
    });
    dialog.querySelector('[data-close-payment]').addEventListener('click', () => dialog.close());
    form.addEventListener('submit', () => { submit.disabled = true; });
})();
