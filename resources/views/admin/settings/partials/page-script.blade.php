<script>
    (() => {
        const form = document.getElementById('retailer-settings-form');
        if (!form) return;

        const tablist = form.querySelector('[role="tablist"]');
        const tabs = Array.from(tablist.querySelectorAll('[role="tab"]'));
        const panels = Array.from(form.querySelectorAll('[data-settings-panel]'));
        const storageKey = 'retailer-settings.active-panel';

        function activate(panelId, focusTab = false) {
            tabs.forEach(tab => {
                const selected = tab.getAttribute('aria-controls') === panelId;
                tab.setAttribute('aria-selected', String(selected));
                tab.tabIndex = selected ? 0 : -1;
                if (selected && focusTab) {
                    tab.focus();
                    tab.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                }
            });
            panels.forEach(panel => { panel.hidden = panel.id !== panelId; });
            try { sessionStorage.setItem(storageKey, panelId); } catch (_) { /* Tabs also work without storage. */ }
        }

        function reveal(field) {
            const panel = field.closest('[data-settings-panel]');
            if (panel) activate(panel.id);
            let ancestor = field.parentElement;
            while (ancestor && ancestor !== form) {
                if (ancestor.tagName === 'DETAILS') ancestor.open = true;
                ancestor = ancestor.parentElement;
            }
        }

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activate(tab.getAttribute('aria-controls')));
            tab.addEventListener('keydown', event => {
                const rtl = getComputedStyle(tablist).direction === 'rtl';
                let next;
                if (event.key === 'ArrowRight') next = index + (rtl ? -1 : 1);
                if (event.key === 'ArrowLeft') next = index + (rtl ? 1 : -1);
                if (event.key === 'Home') next = 0;
                if (event.key === 'End') next = tabs.length - 1;
                if (next === undefined) return;
                event.preventDefault();
                activate(tabs[(next + tabs.length) % tabs.length].getAttribute('aria-controls'), true);
            });
        });

        panels.forEach(panel => {
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', panel.id.replace('settings-panel-', 'settings-tab-'));
            panel.tabIndex = 0;
        });
        let initialPanel = panels[0].id;
        try {
            const savedPanel = sessionStorage.getItem(storageKey);
            if (panels.some(panel => panel.id === savedPanel)) initialPanel = savedPanel;
        } catch (_) { /* Use the first section when storage is unavailable. */ }
        const errorField = form.querySelector('[aria-invalid="true"]');
        const errorPanel = errorField?.closest('[data-settings-panel]');
        activate(errorPanel ? errorPanel.id : initialPanel);
        if (errorField) reveal(errorField);
        form.querySelector('.settings-workspace').classList.add('is-enhanced');
        tablist.hidden = false;

        form.querySelectorAll('textarea[maxlength]').forEach(field => {
            const counter = document.getElementById(field.id + '-count').querySelector('[data-character-count]');
            const updateCount = () => { counter.textContent = String(field.value.length); };
            field.addEventListener('input', updateCount);
            updateCount();
        });

        form.querySelectorAll('[data-reply-image]').forEach(container => {
            const input = container.querySelector('[data-reply-image-input]');
            const preview = container.querySelector('[data-reply-image-preview]');
            const remove = container.querySelector('[data-reply-image-remove]');
            const filename = container.querySelector('[data-reply-image-filename]');
            let objectUrl;
            const update = () => {
                if (objectUrl) URL.revokeObjectURL(objectUrl);
                const file = input.files[0];
                filename.textContent = file ? file.name : (preview.dataset.savedSrc ? 'صورة محفوظة' : 'لم يتم اختيار صورة');
                objectUrl = file ? URL.createObjectURL(file) : null;
                const src = objectUrl || preview.dataset.savedSrc;
                if (src) preview.src = src;
                else preview.removeAttribute('src');
                preview.hidden = !src || Boolean(remove?.checked);
            };
            input.addEventListener('change', () => {
                if (input.files.length && remove) remove.checked = false;
                update();
            });
            remove?.addEventListener('change', () => {
                if (remove.checked) input.value = '';
                update();
            });
            update();
        });

        const status = document.getElementById('settings-save-status');
        if (status) {
            const formState = () => JSON.stringify(Array.from(new FormData(form).entries(), ([key, value]) =>
                [key, value instanceof File ? (value.name ? [value.name, value.size, value.lastModified] : null) : value]));
            const baseline = formState();
            const updateStatus = () => {
                const dirty = baseline !== formState();
                status.textContent = dirty ? 'لديك تغييرات غير محفوظة.' : 'تطبق التغييرات في جميع الأقسام بعد الحفظ.';
                status.parentElement.classList.toggle('is-dirty', dirty);
            };
            form.addEventListener('input', updateStatus);
            form.addEventListener('change', updateStatus);
        }

        // Reveal the first invalid control before asking the browser to focus it.
        // Hidden tabs and collapsed PDF notes retain their values and validation.
        form.noValidate = true;
        form.addEventListener('submit', event => {
            const invalidField = form.querySelector(':invalid');
            if (!invalidField) return;
            event.preventDefault();
            reveal(invalidField);
            invalidField.focus();
            invalidField.reportValidity();
        });
    })();
</script>
