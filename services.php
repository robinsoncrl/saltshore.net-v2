<?php
/**
 * SaltShore Systems V2 — Products / Services Overview
 */
$pageTitle       = 'Products — SaltShore Systems';
$pageDescription = 'CalGen, FinPro, and LedgerPro — three offline-first tools forming one pipeline for freelancers. One-time purchase. No subscriptions.';
include 'includes/head.php';
include 'includes/sidebar.php';
?>

<main class="main-content">

    <!-- Hero -->
    <section class="hero hero-sm">
        <p class="hero-tagline">The Suite</p>
        <h1>Three tools. One pipeline.</h1>
        <p class="hero-subhead">
            Each tool solves one problem completely &mdash; and hands off cleanly to the next.
        </p>
    </section>

    <!-- CalGen -->
    <section class="split-section">
        <div class="split-content">
            <span class="section-label">01 &mdash; Time</span>
            <h2>CalGen</h2>
            <p>
                CalGen generates and validates iCalendar (.ics) files built to survive
                real-world client incompatibilities. It targets the failure modes that actually
                break calendar workflows&mdash;timezone drift, missing required fields,
                recurrence fragility, and strict client enforcement&mdash;and solves the
                portion that is structurally solvable.
            </p>
            <ul class="feature-list mt-md">
                <li>Timezone-safe output with canonical VTIMEZONE handling and explicit TZID strategy</li>
                <li>Required-field correctness by construction (VERSION, PRODID, UID, DTSTART, DTSTAMP)</li>
                <li>Strict RFC&nbsp;5545 property ordering&mdash;prevents modern client parsers from silently dropping fields</li>
                <li>Resilient recurrence generation (RRULE, EXDATE, RECURRENCE-ID) to reduce DST drift and exception loss</li>
                <li>Stable deterministic UIDs for reconciliation and deduplication over time</li>
                <li>Deterministic outputs: same input &rarr; same file, enabling versioning and verification</li>
            </ul>
            <a href="service-detail.php?product=calgen" class="btn-primary mt-lg">
                See CalGen Details &rarr;
            </a>
        </div>
        <div class="split-visual">
            <img src="assets/img/calgen-logo.png"
                 alt="CalGen product logo — calendar with gear icon"
                 style="max-width: 180px; height: auto;
                        border-radius: var(--radius-lg);">
        </div>
    </section>

    <!-- FinPro -->
    <section class="section-full section-alt">
        <div class="split-section"
             style="padding: 0; max-width: var(--content-wide); margin: 0 auto;">
            <div class="split-visual">
                 <img src="assets/img/finpro-logo.png"
                     alt="FinPro product logo — invoice document with dollar badge"
                     style="max-width: 180px; height: auto;
                            border-radius: var(--radius-lg);">
            </div>
            <div class="split-content">
                <span class="section-label">02 &mdash; Billing</span>
                <h2 style="color: var(--color-text);">FinPro</h2>
                <p style="color: var(--color-text-muted);">
                    FinPro closes the gap between work performed and money correctly documented.
                    It ingests structured time data from CalGen or manual entry, applies explicit
                    rate rules, and produces reproducible financial outputs&mdash;every line item
                    shows its derivation, totals are always calculated, never typed.
                </p>
                <ul class="feature-list mt-md">
                    <li>Deterministic earnings calculation from structured time&mdash;same input, same output</li>
                    <li>Versioned rate definitions with effective-date boundaries to prevent retroactive drift</li>
                    <li>Immutable invoice line items preserving rates, quantities, and totals at issuance</li>
                    <li>Transparent gross-to-net estimation with labeled deduction breakdowns</li>
                    <li>Expense tracking with client/project attribution and direct invoice inclusion</li>
                    <li>Fully offline&mdash;local data storage, no subscription dependency</li>
                </ul>
                <a href="service-detail.php?product=finpro"
                   class="btn-primary mt-lg"
                   style="align-self: flex-start;">
                    See FinPro Details &rarr;
                </a>
            </div>
        </div>
    </section>

    <!-- LedgerPro -->
    <section class="split-section">
        <div class="split-content">
            <span class="section-label">03 &mdash; Records</span>
            <h2>LedgerPro</h2>
            <p>
                LedgerPro converts raw bank statement files into defensible, auditable
                transaction ledgers. It ingests CSV, OFX, QFX, and fixed-width reports
                from any institution, normalizes them deterministically, and reconciles
                against FinPro invoices&mdash;surfacing gaps before tax time finds them
                for you.
            </p>
            <ul class="feature-list mt-md">
                <li>First-class import of CSV, OFX, QFX, and fixed-width statements&mdash;no live bank feeds</li>
                <li>Deterministic normalization of dates, amounts, and debit/credit models</li>
                <li>Duplicate detection using composite fingerprints with user-visible resolution paths</li>
                <li>Transfer-aware reconciliation preventing internal movements from inflating income</li>
                <li>Statement coverage indexing to surface missing months or gaps</li>
                <li>Structured Records Vault with integrity hashing and long-term archival</li>
            </ul>
            <a href="service-detail.php?product=ledgerpro" class="btn-primary mt-lg">
                See LedgerPro Details &rarr;
            </a>
        </div>
        <div class="split-visual">
            <img src="assets/img/ledgerpro-logo.svg"
                 alt="LedgerPro product logo — open ledger book with checkmark badge"
                 style="max-width: 180px; height: auto;
                        border-radius: var(--radius-lg);">
        </div>
    </section>

    <!-- CTA Band -->
    <section class="cta-band">
        <div class="cta-band-inner">
            <h2>Start with CalGen. End with reconciled records.</h2>
            <p>
                Each tool is available individually or as a complete suite.
                One-time purchase. Local install. Your data stays yours.
            </p>
            <div class="btn-group" style="justify-content: center;">
                <a href="contact.php" class="btn-primary">Get in Touch</a>
                <a href="docs.php"
                   class="btn-ghost"
                   style="color: rgba(247,249,251,0.8);
                          border-color: rgba(247,249,251,0.2);">Read the Docs</a>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

</main>
