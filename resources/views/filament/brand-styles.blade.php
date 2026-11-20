{{-- The Flitops look for the admin panel. The colours themselves are in AdminPanelProvider. --}}
<style>
    .fi-body { background-color: #F1F1F1; }
    html.dark .fi-body { background-color: #0A0A0A; }

    /* A stripe of Flitops blue along the top of the admin. */
    .fi-body-has-topbar::before {
        content: "";
        position: fixed;
        inset: 0 0 auto;
        z-index: 31;
        height: 3px;
        background-color: #2563EB;
    }

    /* Page headings a little smaller than Filament's, like the customer app's. */
    .fi-header-heading { font-size: 1.5rem; line-height: 2rem; }

    /* No line under a card's heading, so the card reads as one block. */
    .fi-section:not(.fi-section-not-contained):not(.fi-aside).fi-section-has-header:not(.fi-collapsed) > .fi-section-content-ctn { border-top-width: 0; }
    .fi-section:not(.fi-section-not-contained):not(.fi-aside).fi-section-has-header > .fi-section-content-ctn > .fi-section-content { padding-top: 0; }

    /* Logs keep their line breaks. */
    .flitops-log { white-space: pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .75rem; line-height: 1.25rem; }
</style>
