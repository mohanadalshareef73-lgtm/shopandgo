<style>
    .retailer-settings .settings-reply-image-preview { display: block; max-width: 240px; max-height: 180px; object-fit: contain; border: 1px solid #e5e7eb; border-radius: 8px; }
    .retailer-settings .settings-reply-image-preview[hidden] { display: none; }
    .retailer-settings .settings-image-picker { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .retailer-settings .settings-image-picker input:focus-visible + label { outline: 2px solid #4f6ef7; outline-offset: 3px; }
    .retailer-settings {
        --settings-ink: var(--bs-heading-color, #243247);
        --settings-muted: var(--bs-secondary-color, #687585);
        --settings-line: var(--bs-border-color, #e6e9ee);
        --settings-surface: #fff;
        --settings-soft: var(--bs-tertiary-bg, #f6f8fb);
        --settings-accent: var(--bs-primary, #2455aa);
        color: var(--settings-ink);
    }
    [data-pc-theme="dark"] .retailer-settings {
        --settings-ink: var(--pc-heading-color);
        --settings-muted: var(--pc-sidebar-color);
        --settings-surface: #1b232d;
        --settings-soft: var(--bs-body-bg);
    }
    .settings-container { max-width: 72rem; margin-inline: auto; }
    .retailer-settings .page-header { margin-bottom: 1.75rem; }
    .settings-heading, .settings-panel-heading { display: flex; justify-content: space-between; align-items: start; gap: 1.5rem; }
    .settings-heading h2 { margin: 0 0 .65rem; font-size: 1.65rem; font-weight: 700; line-height: 1.5; }
    .settings-heading p, .settings-exchange p, .settings-panel-heading p, .settings-row-description p { margin: 0; color: var(--settings-muted); font-size: .85rem; line-height: 1.9; }
    .settings-readonly { display: inline-flex; align-items: center; gap: .5rem; padding: .5rem .8rem; border-radius: .4rem; background: var(--settings-soft); color: var(--settings-muted); white-space: nowrap; font-size: .8rem; }
    .settings-surface { background: var(--settings-surface); border: 1px solid var(--settings-line); border-radius: .85rem; }
    .settings-exchange { display: grid; grid-template-columns: minmax(0, 1fr) minmax(16rem, 22rem); align-items: center; gap: 2rem; padding: 1.75rem 2rem; margin-bottom: 1.5rem; }
    .settings-kicker { display: inline-block; margin-bottom: .6rem; color: var(--settings-muted); font-size: .7rem; font-weight: 600; }
    .settings-exchange h3, .settings-wallet h3, .settings-panel-heading h3 { font-size: 1.1rem; font-weight: 700; line-height: 1.6; margin: 0 0 .5rem; }
    .settings-saved-rate { display: inline-block; margin-top: .85rem; color: var(--settings-accent); font-size: .85rem; font-weight: 600; font-variant-numeric: tabular-nums; }
    .settings-muted { color: var(--settings-muted); font-size: .78rem; line-height: 1.9; }
    .settings-exchange .settings-muted { display: block; margin-top: .75rem; }
    .settings-tabs { display: flex; gap: 1rem; padding: 0 2rem; border-bottom: 1px solid var(--settings-line); overflow-x: auto; }
    .retailer-settings [hidden] { display: none !important; }
    .settings-tab { display: inline-flex; justify-content: center; align-items: center; gap: .65rem; padding: 1.2rem .75rem; border: 0; border-bottom: 3px solid transparent; background: none; color: var(--settings-muted); font-size: .85rem; font-weight: 600; white-space: nowrap; transition: color 160ms, background-color 160ms; }
    .settings-tab i { font-size: 1.15rem; }
    .settings-tab:hover { color: var(--settings-accent); background: var(--settings-soft); }
    .settings-tab[aria-selected="true"] { color: var(--settings-accent); border-bottom-color: var(--settings-accent); }
    .settings-tab:focus-visible, .settings-details summary:focus-visible, .settings-link:focus-visible { outline: 2px solid var(--settings-accent); outline-offset: -3px; border-radius: .25rem; }
    .settings-panel { padding: 2rem; scroll-margin-top: 6rem; }
    .settings-panel + .settings-panel { border-top: 1px solid var(--settings-line); }
    .settings-workspace.is-enhanced .settings-panel { border-top: 0; }
    .settings-panel-heading { margin-bottom: 2rem; }
    .settings-link { display: inline-flex; align-items: center; gap: .4rem; margin-top: 1.6rem; font-size: .78rem; white-space: nowrap; }
    .settings-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(16rem, 22rem); gap: 2rem; align-items: center; }
    .settings-row-description h4 { font-size: .9rem; font-weight: 600; margin-bottom: .6rem; }
    .settings-field { min-width: 0; }
    .retailer-settings .form-label { display: block; color: var(--settings-ink); font-size: .82rem; font-weight: 600; margin-bottom: .65rem; line-height: 1.8; }
    .retailer-settings .form-control { border-radius: .4rem; min-height: 3rem; font-size: .9rem; }
    .settings-number-control { position: relative; }
    .settings-number-control input { padding-left: 1rem; padding-right: 4.25rem; font-variant-numeric: tabular-nums; }
    .settings-number-control > span { position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: var(--settings-muted); font-size: .75rem; pointer-events: none; }
    .settings-number-control input.is-invalid { background-image: none; }
    .settings-help { display: block; margin-top: .6rem; color: var(--settings-muted); font-size: .73rem; line-height: 1.9; }
    .settings-field p.settings-help { margin: -.2rem 0 .85rem; }
    .settings-field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.75rem 2.25rem; }
    .settings-details { margin-top: 1.2rem; color: var(--settings-muted); font-size: .78rem; line-height: 1.9; }
    .settings-details summary { width: fit-content; max-width: 100%; padding: .4rem 0; cursor: pointer; color: var(--settings-ink); font-weight: 600; }
    .settings-details summary:hover { color: var(--settings-accent); }
    .settings-details-body { padding-top: 1rem; }
    .settings-details-body p:last-child { margin-bottom: 0; }
    .settings-details-body .alert { margin-bottom: 0; padding: 1.25rem; background: var(--settings-soft); border: 0 !important; color: var(--settings-ink); }
    .settings-wallet { padding: 1.75rem 2rem; margin-bottom: 1.5rem; }
    .settings-wallet .form-check { display: flex; align-items: start; gap: 1rem; padding: 0; margin: 0; }
    .settings-wallet .form-check-input { float: none; flex: 0 0 auto; margin: .3rem 0 0; cursor: pointer; width: 2.5rem; height: 1.35rem; }
    .settings-wallet .form-check-label { font-size: .85rem; line-height: 1.9; font-weight: 600; cursor: pointer; }
    .settings-wallet > p { margin: .65rem 0 0; font-size: .78rem; line-height: 1.9; color: var(--settings-muted); }
    .settings-wallet .settings-details { margin-top: .5rem; }
    .settings-pdf { margin-top: 1.5rem; }
    .settings-pdf summary { width: 100%; }
    .settings-pdf summary small { display: block; margin-top: .25rem; color: var(--settings-muted); font-size: .73rem; font-weight: 400; }
    .settings-note { padding: 1rem 1.2rem; margin: 1.75rem 0 0; border-radius: .5rem; background: var(--settings-soft); color: var(--settings-muted); font-size: .78rem; line-height: 2; }
    .settings-note i { margin-inline-end: .35rem; }
    #cart-reply-help { margin-top: 0; margin-bottom: 1.75rem; }
    .settings-message-row + .settings-message-row { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--settings-line); }
    .settings-message-row:last-of-type { margin-bottom: 1.25rem; }
    .retailer-settings textarea.form-control { resize: vertical; line-height: 1.9; padding: .8rem 1rem; }
    .settings-character-count { display: block; margin-top: .5rem; text-align: end; color: var(--settings-muted); font-size: .7rem; font-variant-numeric: tabular-nums; }
    .settings-savebar { position: sticky; bottom: 1rem; z-index: 10; display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1rem 1.5rem; margin-top: 1.25rem; background: var(--settings-surface); border: 1px solid var(--settings-line); border-radius: .75rem; box-shadow: 0 4px 20px #2432470d; }
    .settings-savebar p { margin: 0; font-size: .78rem; color: var(--settings-muted); line-height: 1.8; }
    .settings-savebar.is-dirty p { color: var(--settings-accent); }
    .settings-savebar .btn { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; padding: .75rem 1.2rem; border-radius: .45rem; white-space: nowrap; font-size: .85rem; }
    .retailer-settings input, .retailer-settings textarea { scroll-margin-block: 7rem; }
    @media (max-width: 1199px) {
        .settings-exchange, .settings-row { grid-template-columns: minmax(0, 1fr) minmax(14rem, 20rem); gap: 1.5rem; }
        .settings-exchange, .settings-panel { padding: 1.5rem; }
        .settings-tabs { padding-inline: 1.5rem; gap: .5rem; }
    }
    @media (max-width: 767px) {
        .settings-heading h2 { font-size: 1.35rem; }
        .settings-heading, .settings-panel-heading { flex-wrap: wrap; gap: .5rem; }
        .settings-exchange, .settings-row, .settings-field-grid { grid-template-columns: minmax(0, 1fr); gap: 1.5rem; }
        .settings-exchange, .settings-wallet, .settings-panel { padding: 1.25rem; }
        .settings-tabs { padding-inline: .75rem; gap: .25rem; }
        .settings-tab { padding: 1rem .7rem; font-size: .78rem; gap: .4rem; }
        .settings-panel-heading { margin-bottom: 1.5rem; }
        .settings-link { margin-top: .5rem; }
        .settings-savebar { bottom: .5rem; padding: .85rem 1rem; flex-wrap: wrap; }
        .settings-savebar .btn { width: 100%; }
    }
    @media (prefers-reduced-motion: reduce) { .settings-tab { transition: none; } }
</style>
