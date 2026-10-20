<style>
    .shein-page { --shein-ink: #252d38; --shein-muted: #78818e; --shein-line: #e8ebef; --shein-surface: #f5f7f9; color: var(--shein-ink); }
    .shein-page .pc-content { max-width: 1600px; margin-inline: auto; }
    .shein-page .page-header { margin-bottom: 1.6rem; }
    .shein-heading, .shein-heading-actions { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
    .shein-eyebrow { display: flex; align-items: center; gap: .7rem; color: var(--shein-muted); font-size: .75rem; margin-bottom: .65rem; }
    .shein-wordmark { font: 800 .9rem/1 system-ui, sans-serif; letter-spacing: .13em; color: var(--shein-ink); border-inline-end: 1px solid #ccd2d9; padding-inline-end: .7rem; }
    .shein-heading h2 { font-size: clamp(1.65rem, 2.4vw, 2.25rem); line-height: 1.45; font-weight: 700; margin: 0; }
    .shein-heading p { margin: .45rem 0 0; color: var(--shein-muted); font-size: .85rem; }
    .shein-page .card { border: 1px solid var(--shein-line); border-radius: 14px; box-shadow: 0 3px 18px #23304704; overflow: hidden; margin-bottom: 1.25rem; }
    .shein-page .card-header { background: #fff; border-bottom: 1px solid var(--shein-line); padding: 1.2rem 1.4rem; }
    .shein-page .card-header h5 { font-size: 1rem; font-weight: 700; margin-bottom: 0; }
    .shein-page .card-body { padding: 1.4rem; }
    .shein-page .btn { border-radius: 7px; font-weight: 600; font-size: .8rem; min-height: 38px; display: inline-flex; align-items: center; justify-content: center; gap: .4rem; transition: background-color .16s ease, border-color .16s ease, transform .16s ease; }
    .shein-page .btn:active { transform: translateY(1px); }
    .shein-page :is(a, button, input, select, textarea, summary):focus-visible { outline: 3px solid #a2bcf5; outline-offset: 3px; }
    .shein-page .form-control, .shein-page .form-select { border-color: #dfe4eb; border-radius: 7px; min-height: 42px; font-size: .85rem; }
    .shein-page .form-label { color: #596575; font-size: .78rem; margin-bottom: .5rem; }
    .shein-page .badge { border-radius: 5px; font-size: .73rem; line-height: 1.6; font-weight: 600; }
    .shein-page :is(.shein-number, bdi, [dir="ltr"]) { font-variant-numeric: tabular-nums; }
    .shein-overview { display: flex; flex-wrap: wrap; gap: 1.5rem 3rem; margin: 0 0 1.5rem; padding: 1rem 0 1.3rem; border-bottom: 1px solid var(--shein-line); }
    .shein-metric { display: flex; align-items: baseline; gap: .8rem; }
    .shein-metric span { color: var(--shein-muted); font-size: .8rem; }
    .shein-metric strong { font-size: 1.45rem; font-weight: 700; }
    .shein-status-nav { display: flex; gap: .25rem; overflow-x: auto; padding-bottom: .75rem; margin-bottom: .25rem; }
    .shein-status-tab { flex-shrink: 0; display: inline-flex; align-items: center; gap: .55rem; padding: .6rem .85rem; border-radius: 7px; color: #697586; font-size: .8rem; border: 1px solid transparent; }
    .shein-status-tab:hover { color: var(--shein-ink); background: #edf0f4; }
    .shein-status-tab.is-active { color: #fff; background: #35465f; font-weight: 700; }
    .shein-count { font: 600 .75rem/1.5 system-ui, sans-serif; min-width: 1.4rem; padding: .1rem .3rem; border-radius: 4px; text-align: center; background: #78818e12; }
    .is-active .shein-count { background: #ffffff21; }
    .shein-toolbar { display: flex; flex-wrap: wrap; gap: .85rem; align-items: end; }
    .shein-search { flex: 1 1 17rem; }
    .shein-page-size { flex: 0 0 10rem; }
    .shein-session-filter { flex: 1 1 13rem; min-width: 0; }
    .shein-receiving-source { border-top: 1px solid var(--shein-line); padding-top: .85rem; margin-top: 1rem; font-size: .78rem; overflow-wrap: anywhere; }
    .shein-source-label { display: block; color: var(--shein-muted); margin-bottom: .3rem; font-size: .7rem; }
    .shein-source-phone { display: block; font-weight: 600; }
    .shein-source-session { display: block; color: #596575; margin-top: .25rem; font-size: .72rem; }
    .shein-source-details { margin-top: .7rem; color: var(--shein-muted); font-size: .68rem; }
    .shein-source-details bdi { display: block; margin-top: .4rem; }
    .shein-receiving-source.is-compact { border: 0; padding: 0; margin-top: .5rem; }
    .is-compact .shein-source-label { display: inline; margin-inline-end: .3rem; font-size: .65rem; }
    .is-compact .shein-source-phone { display: inline; font-size: .7rem; }
    .is-compact .shein-source-session { font-size: .65rem; }
    .shein-list-labels, .shein-cart-row { display: grid; grid-template-columns: minmax(12rem, 1.4fr) minmax(10rem, 1.1fr) minmax(6rem, .65fr) minmax(7rem, .8fr) auto; gap: 1.25rem; align-items: center; }
    .shein-list-labels { padding: .8rem 1.4rem; color: var(--shein-muted); font-size: .73rem; background: #f8f9fb; border-block: 1px solid var(--shein-line); }
    .shein-cart-row { padding: 1.2rem 1.4rem; border-bottom: 1px solid var(--shein-line); transition: background-color .16s ease; }
    .shein-cart-row:last-child { border-bottom: 0; }
    .shein-cart-row:hover { background: #fbfcfe; }
    .shein-cart-preview { min-width: 0; color: var(--shein-ink); }
    .shein-cart-thumbnails { display: flex; gap: .35rem; margin-bottom: .65rem; }
    .shein-cart-thumbnails .shein-product-image, .shein-preview-empty { width: 3.65rem; height: 4.3rem; border-radius: 5px; }
    .shein-cart-thumbnails .shein-image-placeholder small { display: none; }
    .shein-cart-caption { display: flex; gap: .6rem; align-items: baseline; flex-wrap: wrap; }
    .shein-cart-id { font-size: .85rem; font-weight: 700; }
    .shein-secondary { color: var(--shein-muted); font-size: .72rem; margin-top: .3rem; }
    .shein-customer { display: flex; align-items: center; gap: .65rem; font-size: .83rem; min-width: 0; overflow-wrap: anywhere; }
    .shein-avatar { display: grid; place-items: center; width: 2.2rem; height: 2.2rem; flex-shrink: 0; background: #edf1f6; color: #63718b; border-radius: 50%; font-weight: 700; }
    .shein-total { font-size: 1.1rem; font-weight: 700; white-space: nowrap; }
    .shein-total small { font-size: .7rem; color: var(--shein-muted); font-weight: 500; }
    .shein-actions { display: flex; align-items: center; justify-content: end; gap: .4rem; flex-wrap: wrap; }
    .shein-pagination { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; border-top: 1px solid var(--shein-line); padding: 1rem 1.4rem; color: var(--shein-muted); font-size: .8rem; }
    .shein-pagination .pagination { margin: 0; }
    .shein-empty { padding: 3rem 1.25rem; text-align: center; }
    .shein-empty h5 { color: var(--shein-ink); font-size: 1rem; margin: 1rem 0 .6rem; }
    .shein-empty-icon { font-size: 2rem; color: #a0a9b6; }
    .shein-wallet-grid { display: grid; grid-template-columns: repeat(3, 1fr); }
    .shein-wallet-value { padding: .2rem 1.25rem; border-inline-end: 1px solid var(--shein-line); }
    .shein-wallet-value:first-child { padding-inline-start: 0; }
    .shein-wallet-value:last-child { border: 0; }
    .shein-wallet-value small { color: var(--shein-muted); font-size: .75rem; }
    .shein-wallet-value bdi { display: block; font-size: 1.2rem; font-weight: 700; margin-top: .4rem; }
    .shein-wallet-available bdi { color: #2455aa; }
    .shein-detail-layout { display: grid; grid-template-columns: minmax(0, 1fr) 20rem; gap: 1.5rem; align-items: start; }
    .shein-detail-main, .shein-detail-sidebar { min-width: 0; }
    .shein-detail-sidebar, .shein-page #pdf-editor { scroll-margin-top: 6rem; }
    .shein-quote-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
    .shein-summary-label { color: var(--shein-muted); font-size: .8rem; }
    .shein-quote-total { font: 650 2.5rem/1.5 system-ui, sans-serif; text-align: start; margin-top: .2rem; }
    .shein-quote-total small { font-size: .95rem; font-weight: 500; color: var(--shein-muted); }
    .shein-quote-state { text-align: end; }
    .shein-quote-facts { display: flex; flex-wrap: wrap; gap: 1.4rem 2.5rem; padding-top: 1.25rem; margin-top: 1.1rem; border-top: 1px solid var(--shein-line); }
    .shein-quote-facts span { display: block; color: var(--shein-muted); font-size: .72rem; margin-bottom: .4rem; }
    .shein-quote-facts strong { font-size: .9rem; font-weight: 600; display: block; }
    .shein-quote-links { display: flex; gap: 1.5rem; flex-wrap: wrap; margin-top: 1.25rem; font-size: .78rem; }
    .shein-section-heading { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
    .shein-product-row { display: grid; grid-template-columns: 7.5rem minmax(0, 1fr) auto; gap: 1.25rem; padding: 1.4rem; border-bottom: 1px solid var(--shein-line); align-items: center; }
    .shein-product-row:last-child { border-bottom: 0; }
    .shein-product-photo { width: 7.5rem; height: 9.5rem; background: none; border: 0; padding: 0; border-radius: 8px; cursor: zoom-in; }
    .shein-product-photo:disabled { cursor: default; }
    .shein-product-image { display: block; position: relative; width: 100%; height: 100%; overflow: hidden; border-radius: inherit; background: #f1f3f5; }
    .shein-product-image img { position: relative; width: 100%; height: 100%; object-fit: contain; background: #f6f7f8; }
    .shein-product-image img[hidden] { display: none; }
    .shein-image-placeholder { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5rem; color: #9da6b3; }
    .shein-image-placeholder i, .shein-preview-empty { font-size: 1.5rem; }
    .shein-image-placeholder small { font-size: .65rem; }
    .shein-preview-empty { display: grid; place-items: center; background: var(--shein-surface); color: #9da6b3; }
    .shein-product-position { display: block; font-size: .65rem; color: var(--shein-muted); margin-bottom: .5rem; }
    .shein-product-info h6 { font-size: .88rem; line-height: 1.8; font-weight: 600; margin: 0 0 .35rem; overflow-wrap: anywhere; }
    .shein-product-variant { color: var(--shein-muted); font-size: .75rem; margin-bottom: .8rem; overflow-wrap: anywhere; }
    .shein-product-meta { display: flex; gap: .8rem; align-items: center; flex-wrap: wrap; font-size: .75rem; margin-bottom: .6rem; }
    .shein-product-meta strong { display: inline-block; padding: .1rem .5rem; background: #edf0f4; border-radius: 4px; margin-inline-start: .25rem; }
    .shein-availability { color: #2c7b60; font-size: .7rem; }
    .shein-availability::before { content: ''; display: inline-block; width: .35rem; height: .35rem; border-radius: 50%; background: currentColor; margin-inline-end: .35rem; vertical-align: middle; }
    .shein-availability.is-unavailable { color: #a66755; }
    .shein-product-price { text-align: end; }
    .shein-product-price > span, .shein-product-price small { display: block; color: var(--shein-muted); font-size: .65rem; }
    .shein-product-price strong { display: block; font: 600 1rem/1.5 system-ui, sans-serif; margin-block: .4rem; white-space: nowrap; }
    .shein-product-row.is-unavailable { background: #fafafa; }
    .shein-page .item-unavailable > td { background: #fafafa; }
    .shein-detail-sidebar .card-body { font-size: .83rem; }
    .shein-page #status-history .card-body { max-height: 36rem; overflow-y: auto; }
    .shein-image-viewer { width: min(34rem, calc(100% - 2rem)); padding: 2.5rem 1.5rem 1.5rem; border: 0; border-radius: 12px; background: #fff; color: var(--shein-ink); }
    .shein-image-viewer::backdrop { background: #15223599; }
    .shein-image-viewer figure { margin: 0; text-align: center; }
    .shein-image-viewer img { width: 100%; max-height: 65vh; object-fit: contain; }
    .shein-image-viewer figcaption { margin-top: 1rem; font-size: .8rem; line-height: 1.8; }
    .shein-viewer-close { position: absolute; top: .6rem; inset-inline-end: .6rem; border: 0; background: #f0f2f5; width: 2rem; height: 2rem; border-radius: 50%; }
    html.pdf-preview-open { overflow: hidden; }
    .shein-pdf-preview { width: min(72rem, calc(100% - 2rem)); height: 88vh; height: 88dvh; max-height: calc(100% - 2rem); padding: 0; border: 0; border-radius: 12px; background: #fff; color: var(--shein-ink); overflow: hidden; }
    .shein-pdf-preview[open] { display: flex; flex-direction: column; }
    .shein-pdf-preview::backdrop { background: #15223599; }
    .shein-pdf-toolbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem; padding: 1rem; border-bottom: 1px solid var(--shein-line); }
    .shein-pdf-toolbar h5 { font-size: 1rem; }
    .shein-pdf-actions { display: flex; align-items: center; gap: .5rem; }
    .shein-pdf-frame { flex: 1; min-height: 0; width: 100%; border: 0; background: var(--shein-surface); }
    .shein-pdf-footer { padding: .75rem 1rem; border-top: 1px solid var(--shein-line); font-size: .75rem; }
    @media (max-width: 1199px) {
        .shein-list-labels { display: none; }
        .shein-cart-row { grid-template-columns: minmax(10rem, 1.2fr) minmax(8rem, 1fr) auto; gap: 1rem; }
        .shein-row-status { grid-column: 1 / 3; display: flex; gap: .7rem; align-items: center; flex-wrap: wrap; }
        .shein-row-status .mt-2 { margin-top: 0 !important; }
        .shein-detail-layout { grid-template-columns: minmax(0, 1fr) 18rem; gap: 1rem; }
        .shein-product-row { grid-template-columns: 6rem minmax(0, 1fr); gap: 1rem; }
        .shein-product-photo { width: 6rem; height: 8rem; grid-row: span 2; }
        .shein-product-price { grid-column: 2; text-align: start; }
        .shein-product-price > span, .shein-product-price small { display: none; }
        .shein-product-price strong { margin: 0; }
    }
    @media (max-width: 991px) { .shein-detail-layout { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 575px) {
        .shein-overview { display: grid; grid-template-columns: repeat(2, 1fr); gap: .8rem; }
        .shein-metric { flex-direction: column; gap: .25rem; }
        .shein-page .card-body, .shein-page .card-header, .shein-cart-row, .shein-product-row { padding: 1rem; }
        .shein-cart-row { grid-template-columns: minmax(0, 1fr) auto; }
        .shein-cart-preview { grid-column: 1 / -1; display: flex; gap: .85rem; align-items: center; flex-wrap: wrap; }
        .shein-cart-preview .shein-receiving-source { flex-basis: 100%; margin-top: 0; }
        .shein-cart-thumbnails { margin: 0; gap: .25rem; }
        .shein-cart-thumbnails .shein-product-image { width: 2.7rem; height: 3.4rem; }
        .shein-cart-caption { flex-direction: column; gap: .15rem; }
        .shein-row-status { grid-column: 1 / -1; }
        .shein-actions { grid-column: 1 / -1; justify-content: start; }
        .shein-customer .shein-avatar { display: none; }
        .shein-wallet-value { padding: .2rem .7rem; }
        .shein-wallet-value bdi { font-size: .9rem; }
        .shein-page-size { flex-basis: 9rem; }
        .shein-quote-total { font-size: 2rem; }
        .shein-quote-facts { gap: 1.25rem; }
        .shein-pagination { padding: 1rem; }
    }
    @media (prefers-reduced-motion: reduce) { .shein-page :is(.btn, .shein-cart-row) { transition: none; } }
</style>
