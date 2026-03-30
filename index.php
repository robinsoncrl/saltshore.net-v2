<?php
/**
 * SaltShore Systems V2 — Home
 */
$pageTitle       = 'SaltShore Systems — Where Tides Meet Tech';
$pageDescription = 'Offline-first, deterministic tools for freelancers and sole operators. CalGen, FinPro, and LedgerPro — structured time, defensible invoices, reconciled records.';
include 'includes/head.php';
include 'includes/sidebar.php';
?>

<main class="main-content">

    <!-- ==============================
         Hero
         ============================== -->
    <section class="hero">
        <p class="hero-tagline">Where Tides Meet Tech</p>
        <img src="assets/img/SSS_logo_2.png"
             alt="SaltShore Systems"
             class="hero-logo"
             width="160"
             height="160">
        <h1>SALTSHORE SYSTEMS</h1>
        <p class="hero-subhead">
            Three tools. One pipeline. Built for freelancers and sole operators
            who are done losing money to disconnected apps and SaaS subscriptions.
        </p>
        <div class="btn-group">
            <a href="services.php" class="btn-primary">Explore the Suite</a>
            <a href="about.php"
               class="btn-ghost"
               style="color: #fff; border-color: rgba(247,249,251,0.25);">Learn More</a>
        </div>
    </section>

    <!-- ==============================
         Product Overview Cards
         ============================== -->
    <section class="section">
        <span class="section-label">The Suite</span>
        <h2>Three tools. One pipeline.</h2>
        <p class="text-muted mt-md"
           style="max-width: 58ch; line-height: 1.7;">
            CalGen, FinPro, and LedgerPro are designed to work together —
            structured time flows into defensible invoices, which reconcile
            against your bank records.
        </p>
        <div class="card-grid">
            <div class="card">
                <span class="card-label">01 &mdash; Time</span>
                <h3>CalGen</h3>
                <p>A deterministic, offline iCalendar (.ics) generator and validator built to
                   survive real-world client incompatibilities&mdash;timezone drift, missing
                   fields, recurrence fragility&mdash;without hiding them.</p>
                <a href="service-detail.php?product=calgen" class="card-link">
                    Learn more &rarr;
                </a>
            </div>
            <div class="card">
                <span class="card-label">02 &mdash; Billing</span>
                <h3>FinPro</h3>
                <p>A deterministic financial workstation that converts structured time into
                   reproducible, defensible money artifacts&mdash;immutable invoices, versioned
                   rates, and transparent gross-to-net breakdowns.</p>
                <a href="service-detail.php?product=finpro" class="card-link">
                    Learn more &rarr;
                </a>
            </div>
            <div class="card">
                <span class="card-label">03 &mdash; Records</span>
                <h3>LedgerPro</h3>
                <p>A deterministic, offline records normalization and reconciliation system
                   that converts raw bank statement files into defensible, auditable
                   transaction ledgers&mdash;without silent failures.</p>
                <a href="service-detail.php?product=ledgerpro" class="card-link">
                    Learn more &rarr;
                </a>
            </div>
        </div>
    </section>

    <!-- ==============================
         Trust Badges
         ============================== -->
    <section class="section-full section-alt">
        <div class="section-inner">
            <span class="section-label">Why SaltShore</span>
            <h2>Built on three non-negotiables.</h2>
            <div class="trust-row">
                <div class="trust-badge">
                    <span class="trust-badge-icon">📴</span>
                    <strong>Offline-First</strong>
                    <p>No cloud. No credential delegation. No account required.
                       Your data lives on your machine.</p>
                </div>
                <div class="trust-badge">
                    <span class="trust-badge-icon">🔑</span>
                    <strong>One-Time Purchase</strong>
                    <p>No subscription. No monthly fee. Access never expires
                       and data is never held hostage.</p>
                </div>
                <div class="trust-badge">
                    <span class="trust-badge-icon">⚓</span>
                    <strong>Rooted in Maine</strong>
                    <p>Built on values shaped by our coastal home — precise,
                       reliable, and direct.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ==============================
         Pipeline Flow
         ============================== -->
    <section class="section">
        <span class="section-label">The Pipeline</span>
        <h2>Structured time &rarr; Defensible invoice &rarr; Reconciled record.</h2>
        <p class="text-muted mt-md"
           style="max-width: 54ch; line-height: 1.7;">
            Each tool hands off to the next — no manual transcription,
            no format gymnastics. One pipeline from first billable minute
            to year-end archive.
        </p>
        <div class="pipeline-flow">
            <div class="pipeline-step">
                <strong>CalGen</strong>
                <span>Log structured time</span>
            </div>
            <span class="pipeline-arrow" aria-hidden="true">&rarr;</span>
            <div class="pipeline-step">
                <strong>FinPro</strong>
                <span>Generate invoices</span>
            </div>
            <span class="pipeline-arrow" aria-hidden="true">&rarr;</span>
            <div class="pipeline-step">
                <strong>LedgerPro</strong>
                <span>Reconcile records</span>
            </div>
        </div>
    </section>

    <!-- ==============================
         CTA Band
         ============================== -->
    <section class="cta-band">
        <div class="cta-band-inner">
            <h2>Ready to stop losing money to disconnected tools?</h2>
            <p>Explore the suite, read the docs, or reach out directly.</p>
            <div class="btn-group" style="justify-content: center;">
                <a href="services.php" class="btn-primary">See All Products</a>
                <a href="contact.php"
                   class="btn-ghost"
                   style="color: rgba(247,249,251,0.8);
                          border-color: rgba(247,249,251,0.2);">Get in Touch</a>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

</main>
