<script>
document.querySelectorAll('[data-product-image]').forEach(function (image) {
    function fallback() {
        image.hidden = true;
        const button = image.closest('[data-image-open]');
        if (button) button.disabled = true;
    }
    image.addEventListener('error', fallback);
    if (image.complete && image.currentSrc && image.naturalWidth === 0) fallback();
});
const viewer = document.getElementById('product-image-viewer');
if (viewer) {
    document.querySelectorAll('[data-image-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            const source = button.querySelector('img');
            if (!source || source.hidden) return;
            viewer.querySelector('img').src = source.src;
            viewer.querySelector('img').alt = source.alt;
            viewer.querySelector('figcaption').textContent = source.alt;
            viewer.showModal();
        });
    });
    viewer.querySelector('[data-image-close]').addEventListener('click', function () { viewer.close(); });
    viewer.addEventListener('click', function (event) { if (event.target === viewer) viewer.close(); });
}
</script>
