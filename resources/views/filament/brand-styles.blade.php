{{--
    The admin panel dressed as the Shopify admin, like the customer app: a black sidebar with the logo, search and
    menu, and the page on a rounded white sheet beside it. 13px type on a 20px line, grey table headers, soft pill
    badges and near-black pill buttons. Filament's own CSS sits in cascade layers, so these plain rules win.
--}}
<style>
    :root {
        --flitops-ink: #0a0a0a;
        --flitops-sidebar-text: #e3e3e3;
        --flitops-sidebar-muted: #a6a6a6;
        --flitops-sidebar-hover: #1f1f1f;
        --flitops-sidebar-active: #2b2b2b;
        --flitops-border: #e3e3e3;
        --flitops-card-shadow: 0 1px 0 0 rgba(26, 26, 26, .07), inset 0 -1px 0 0 rgba(0, 0, 0, .05);
    }

    html { font-size: 15px; }

    .fi-body {
        background-color: var(--flitops-ink);
        color: #303030;
        font-size: 13px;
        line-height: 20px;
    }

    /* The black sidebar. */
    .fi-sidebar { background-color: var(--flitops-ink); }
    .fi-sidebar-header { background: transparent; box-shadow: none; height: auto; padding: 16px 20px 8px; justify-content: flex-start; }
    .fi-sidebar-header-controls { padding: 4px 12px 8px; }
    .fi-sidebar-nav { padding: 4px 12px 12px; row-gap: 12px; }
    .fi-sidebar-nav-groups { row-gap: 12px; }
    .fi-sidebar-group-btn { padding: 4px 8px; }
    .fi-sidebar-group-label { color: var(--flitops-sidebar-muted); font-size: 13px; font-weight: 500; line-height: 20px; }
    .fi-sidebar-group-items { row-gap: 0; }
    .fi-sidebar-item-btn { background: transparent; border-radius: 10px; padding: 5px 8px; column-gap: 10px; }
    .fi-sidebar-item-btn:hover, .fi-sidebar-item-btn:focus-visible { background-color: var(--flitops-sidebar-hover); }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn { background-color: var(--flitops-sidebar-active); }
    .fi-sidebar-item-label { color: var(--flitops-sidebar-text); font-size: 13px; font-weight: 450; line-height: 20px; }
    .fi-sidebar-item.fi-active .fi-sidebar-item-label { color: #fcfcfc; font-weight: 550; }
    .fi-sidebar-item-icon { color: var(--flitops-sidebar-muted); width: 18px; height: 18px; }
    .fi-sidebar-item.fi-active .fi-sidebar-item-icon { color: #fcfcfc; }
    .fi-sidebar-footer { margin: 8px 12px 12px; }
    .fi-sidebar-footer .fi-user-menu-trigger, .fi-sidebar-footer .fi-dropdown-trigger { color: var(--flitops-sidebar-text); }

    /* The search box in the sidebar. */
    .fi-sidebar .fi-global-search-field .fi-input-wrp {
        background-color: #303030;
        border-radius: 10px;
        box-shadow: none;
    }
    .fi-sidebar .fi-global-search-field .fi-input { color: #fcfcfc; font-size: 13px; padding-block: 6px; }
    .fi-sidebar .fi-global-search-field .fi-input::placeholder { color: var(--flitops-sidebar-muted); }
    .fi-sidebar .fi-global-search-field .fi-icon { color: var(--flitops-sidebar-muted); }

    /* The page sits on a rounded white sheet beside the sidebar, and scrolls inside it. */
    @media (min-width: 64rem) {
        .fi-main-ctn {
            background-color: #ffffff;
            border-radius: 16px;
            margin: 4px 4px 4px 0;
            height: calc(100dvh - 8px);
            min-height: 0;
            overflow-y: auto;
        }
    }
    @media (max-width: 63.99rem) {
        .fi-main-ctn { background-color: #ffffff; min-height: 100dvh; }
    }
    .fi-main { padding: 0 20px 32px; }

    /* Page header: small breadcrumbs, a compact bold title, pill buttons on the right. */
    .fi-page-header-main-ctn { padding-block: 12px 24px; row-gap: 16px; }
    .fi-header { padding-top: 4px; gap: 4px; }
    .fi-breadcrumbs-item-label, .fi-breadcrumbs-item-separator { font-size: 13px; color: #616161; }
    .fi-header-heading { font-size: 20px; line-height: 28px; font-weight: 650; letter-spacing: -0.01em; color: #303030; }
    .fi-header-subheading { font-size: 13px; line-height: 20px; color: #616161; max-width: 64rem; }
    .fi-page-content, .fi-page-main { row-gap: 16px; }
    /* A list page is reached from the sidebar, so "Servers > List" says nothing. */
    .fi-resource-list-records-page .fi-breadcrumbs { display: none; }

    /* Buttons: near-black pills for the main action, white pills with a hairline for the rest. */
    .fi-btn { border-radius: 9999px; font-size: 13px; font-weight: 550; padding: 6px 14px; line-height: 20px; }
    .fi-btn.fi-color-primary {
        background: linear-gradient(180deg, #303030 63%, #1a1a1a);
        box-shadow: inset 0 1px 0 0 rgba(255, 255, 255, .2), inset 0 -1px 0 0 #000, 0 1px 0 0 rgba(0, 0, 0, .25);
        color: #fff;
    }
    .fi-btn.fi-color-gray, .fi-btn:not([class*="fi-color-"]) {
        background: #fff;
        color: #303030;
        box-shadow: inset 0 -1px 0 0 #b5b5b5, inset 0 0 0 1px rgba(0, 0, 0, .1), 0 1px 0 0 rgba(0, 0, 0, .05);
    }

    /* Tables and cards: white, a hairline border, 12px corners. */
    .fi-ta-ctn, .fi-section:not(.fi-section-not-contained), .fi-fo-field .fi-input-wrp {
        border-radius: 12px;
        box-shadow: var(--flitops-card-shadow), 0 0 0 1px var(--flitops-border);
    }
    .fi-ta-header-ctn { border-bottom: 1px solid var(--flitops-border); }
    /* Search and filters fill the bar, like Shopify's "Search and filter". */
    .fi-ta-header-toolbar { padding: 6px 8px; gap: 8px; }
    .fi-ta-header-toolbar > .fi-ta-actions:empty { display: none; }
    .fi-ta-header-toolbar > div:last-child { flex: 1; display: flex; align-items: center; gap: 4px; margin-inline-start: 0; }
    .fi-ta-search-field { flex: 1; max-width: none; }
    .fi-ta-search-field .fi-input-wrp { border-radius: 8px; box-shadow: none; background: transparent; }
    .fi-ta-search-field .fi-input { font-size: 13px; padding-block: 4px; }
    .fi-ta-table > thead > tr { background-color: #f7f7f7; }
    .fi-ta-header-cell, .fi-ta-header-cell-sort-btn {
        font-size: 12px;
        font-weight: 550;
        color: #4a4a4a;
        padding-block: 6px;
    }
    .fi-ta-header-cell { padding-inline: 12px; }
    .fi-ta-cell, .fi-ta-cell:first-of-type, .fi-ta-cell:last-of-type { font-size: 13px; padding: 0; }
    .fi-ta-cell > .fi-ta-col { padding: 7px 12px; }
    .fi-ta-cell .fi-ta-text, .fi-ta-cell .fi-ta-icon { padding: 0; }
    .fi-ta-header-cell:first-of-type { padding-inline-start: 12px; }
    .fi-ta-text-item-label, .fi-ta-text { font-size: 13px; line-height: 20px; color: #303030; }
    .fi-ta-text-description, .fi-ta-text .fi-ta-text-description { font-size: 12px; color: #616161; }
    .fi-ta-row { border-color: #ebebeb; }
    .fi-ta-row.fi-clickable:hover, .fi-ta-row:hover { background-color: #f7f7f7; }
    .fi-ta-table > tbody { border-color: #ebebeb; }
    .fi-ta-description { font-size: 13px; color: #616161; }
    .fi-ta-header-heading { font-size: 13px; line-height: 20px; font-weight: 650; color: #303030; }
    .fi-ta-header { padding: 12px 16px 8px; }
    .fi-pagination-overview, .fi-pagination { font-size: 13px; }

    .fi-section:not(.fi-section-not-contained) { background: #fff; }
    .fi-section:not(.fi-section-not-contained) > .fi-section-header { padding: 12px 16px 4px; }
    .fi-section-header-heading { font-size: 13px; font-weight: 650; color: #303030; }
    .fi-section:not(.fi-section-not-contained) > .fi-section-content-ctn > .fi-section-content { padding: 4px 16px 16px; }
    .fi-section:not(.fi-section-not-contained):not(.fi-aside).fi-section-has-header:not(.fi-collapsed) > .fi-section-content-ctn { border-top-width: 0; }
    .fi-in-entry-label, .fi-in-entry-wrp-label { font-size: 12px; font-weight: 500; color: #616161; }
    .fi-in-text-item, .fi-in-text { font-size: 13px; }

    /* Detail page tabs (servers, sites, ...) as Shopify's flat tab bar. */
    .fi-tabs { box-shadow: none; background: transparent; padding: 0; gap: 4px; margin-inline: 0; justify-content: flex-start; }
    .fi-tabs-item { border-radius: 8px; padding: 4px 10px; font-size: 13px; font-weight: 550; }
    .fi-tabs-item.fi-active { background-color: #e3e3e3; }
    .fi-tabs-item-label { color: #303030; }

    /* Badges: soft pills, as in Shopify's order lists. */
    .fi-badge {
        border-radius: 8px;
        box-shadow: none;
        font-size: 12px;
        font-weight: 550;
        line-height: 16px;
        padding: 2px 8px;
        background-color: #e3e3e3;
        color: #303030;
    }
    .fi-badge.fi-color-success { background-color: #affebf; color: #014b40; }
    .fi-badge.fi-color-warning { background-color: #ffd6a4; color: #5e4200; }
    .fi-badge.fi-color-danger { background-color: #fed1d7; color: #8e0b21; }
    .fi-badge.fi-color-info { background-color: #e0f0ff; color: #00527c; }
    .fi-badge .fi-badge-label-ctn, .fi-badge .fi-badge-label { color: inherit; }

    /* Overview numbers: one strip with a hairline between each, like Shopify's order metrics. */
    .fi-wi-stats-overview .fi-section-content {
        gap: 0;
        background: #fff;
        border-radius: 12px;
        box-shadow: var(--flitops-card-shadow), 0 0 0 1px var(--flitops-border);
        overflow: hidden;
    }
    .fi-wi-stats-overview .fi-grid-col + .fi-grid-col { border-inline-start: 1px solid #ebebeb; }
    .fi-wi-stats-overview-stat { padding: 12px 16px; border-radius: 0; box-shadow: none !important; background: transparent; }
    .fi-wi-stats-overview-stat-label { font-size: 13px; font-weight: 550; color: #303030; }
    .fi-wi-stats-overview-stat-value { font-size: 20px; line-height: 28px; font-weight: 650; }
    .fi-wi-stats-overview-stat-description { font-size: 12px; }

    /* Logs keep their line breaks. */
    .flitops-log { white-space: pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .75rem; line-height: 1.25rem; }
</style>
