<?php
/**
 * SaltShore Systems V2 — Docs (Stub)
 */
$pageTitle       = 'Documentation — SaltShore Systems';
$pageDescription = 'SaltShore Systems product documentation for CalGen, FinPro, and LedgerPro.';
include 'includes/head.php';
include 'includes/sidebar.php';
?>

<main class="main-content">

    <!-- Hero -->
    <section class="hero hero-sm">
        <p class="hero-tagline">Docs</p>
        <h1>Documentation</h1>
        <p class="hero-subhead">
            Reference documentation for CalGen, FinPro, and LedgerPro.
            Full migration is in progress &mdash; sections below are being expanded.
        </p>
    </section>

    <!-- CalGen Docs -->
    <section class="section">
        <span class="section-label">CalGen</span>
        <h2>CalGen Documentation</h2>
        <ul class="feature-list mt-md">
            <li>Installation &amp; Setup</li>
            <li>Creating Your First Calendar</li>
            <li>Routine Builder Guide</li>
            <li>RRULE Compression Reference</li>
            <li>Apple Calendar Compatibility</li>
            <li>Plugin System Overview</li>
            <li>Audit Log Format</li>
            <li>Changelog</li>
        </ul>
    </section>

    <!-- FinPro Docs -->
    <section class="section-full section-alt">
        <div class="section-inner">
            <span class="section-label">FinPro</span>
            <h2 style="color: var(--color-text);">FinPro Documentation</h2>
            <em style="color: var(--color-text-muted); font-style: normal;
                       display: block; margin-bottom: var(--space-lg);">
                Coming soon &mdash; FinPro is in active development.
            </em>
            <ul class="feature-list">
                <li>Installation &amp; Setup</li>
                <li>Importing CalGen Time Entries</li>
                <li>Setting Up Billing Rates</li>
                <li>Generating Invoices</li>
                <li>Gross-to-Net Breakdown</li>
                <li>Export Formats</li>
            </ul>
        </div>
    </section>

    <!-- LedgerPro Docs -->
    <section class="section">
        <span class="section-label">LedgerPro</span>
        <h2>LedgerPro Documentation</h2>
        <em style="color: #6a7a8a; font-style: normal;
                   display: block; margin-bottom: var(--space-lg);">
            Coming soon &mdash; LedgerPro is in active development.
        </em>
        <ul class="feature-list">
            <li>Installation &amp; Setup</li>
            <li>Importing Bank Statements (CSV / OFX)</li>
            <li>Institution Profiles</li>
            <li>Reconciling Against FinPro</li>
            <li>Gap Detection &amp; Reports</li>
            <li>Year-End Archive Export</li>
        </ul>
    </section>

    <!-- Legal & EULAs -->
    <section class="section-full section-alt">
        <div class="section-inner">
            <span class="section-label">Legal</span>
            <h2 style="color: var(--color-text);">Licenses &amp; EULAs</h2>
            <p style="color: var(--color-text-muted); max-width: 58ch;
                      line-height: 1.7; margin-bottom: var(--space-xl);">
                Each SaltShore Systems product is sold under a perpetual,
                single-seat end-user license. Your license does not expire.
                Access is never revoked for non-payment after purchase.
            </p>
            <div class="card-grid">
                <div class="card"
                     style="background: rgba(247,249,251,0.05);
                            border-color: rgba(46,139,192,0.15);">
                    <span class="card-label">CalGen</span>
                    <h3 style="color: var(--color-text);">CalGen EULA</h3>
                    <p style="color: var(--color-text-muted); font-size: 0.92rem;">
                        Perpetual single-seat license. Permitted: personal and
                        commercial use on owned machines. Prohibited: redistribution,
                        resale, or sublicensing without written permission.
                    </p>
                    <a href="legal/eula-calgen.php" class="card-link" style="color: var(--color-accent);">
                        Full license text &rarr;
                    </a>
                </div>
                <div class="card"
                     style="background: rgba(247,249,251,0.05);
                            border-color: rgba(46,139,192,0.15);">
                    <span class="card-label">FinPro</span>
                    <h3 style="color: var(--color-text);">FinPro EULA</h3>
                    <p style="color: var(--color-text-muted); font-size: 0.92rem;">
                        Perpetual single-seat license. Same terms as CalGen.
                        Full license text published at time of release.
                    </p>
                    <a href="legal/eula-finpro.php" class="card-link"
                       style="color: rgba(247,249,251,0.35); cursor: default; pointer-events: none;">
                        Available at launch
                    </a>
                </div>
                <div class="card"
                     style="background: rgba(247,249,251,0.05);
                            border-color: rgba(46,139,192,0.15);">
                    <span class="card-label">LedgerPro</span>
                    <h3 style="color: var(--color-text);">LedgerPro EULA</h3>
                    <p style="color: var(--color-text-muted); font-size: 0.92rem;">
                        Perpetual single-seat license. Same terms as CalGen.
                        Full license text published at time of release.
                    </p>
                    <a href="legal/eula-ledgerpro.php" class="card-link"
                       style="color: rgba(247,249,251,0.35); cursor: default; pointer-events: none;">
                        Available at launch
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

</main>
