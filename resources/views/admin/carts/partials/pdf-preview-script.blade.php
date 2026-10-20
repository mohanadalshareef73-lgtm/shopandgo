<script>
(function () {
    const preview = document.getElementById('quotation-pdf-preview');
    if (!preview) return;

    const frame = preview.querySelector('iframe');
    document.querySelectorAll('[data-pdf-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            preview.showModal();
            document.documentElement.classList.add('pdf-preview-open');
            frame.src = frame.dataset.pdfSrc;
        });
    });
    preview.querySelector('[data-pdf-close]').addEventListener('click', function () { preview.close(); });
    preview.addEventListener('click', function (event) {
        if (event.target !== preview) return;
        const bounds = preview.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
            preview.close();
        }
    });
    preview.addEventListener('close', function () {
        frame.removeAttribute('src');
        document.documentElement.classList.remove('pdf-preview-open');
    });
})();
</script>
