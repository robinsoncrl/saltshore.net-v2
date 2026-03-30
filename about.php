<?php
/**
 * SaltShore Systems V2 — About
 */
$pageTitle       = 'About — SaltShore Systems';
$pageDescription = 'SaltShore Systems is a Maine-based sole-operator software company building offline-first, deterministic tools for freelancers.';
include 'includes/head.php';
include 'includes/sidebar.php';
?>

<main class="main-content">

    <!-- Hero -->
    <section class="hero hero-sm">
        <p class="hero-tagline">About</p>
        <h1>We build tools we actually use.</h1>
        <p class="hero-subhead">
            SaltShore Systems is a Maine-based sole-operator software company
            built on the conviction that freelancers deserve the same precision
            in their tools that they put into their work.
        </p>
    </section>

    <!-- Mission -->
    <section class="split-section">
        <div class="split-content">
            <span class="section-label">Mission</span>
            <h2>Precise tools. No subscriptions. No nonsense.</h2>
            <p>
                SaltShore Systems builds software for the kind of professional
                who cares deeply about accuracy — the freelancer who tracks every
                billable minute, sends clean invoices, and needs their records
                to hold up under scrutiny.
            </p>
            <p>
                Every tool in the suite is designed to be deterministic: same
                input, same output, every time. No cloud sync surprises.
                No platform lock-in. No features that disappear when you skip
                a month's payment.
            </p>
        </div>
        <div class="split-visual">
            <span style="opacity: 0.3;">⚓</span>
        </div>
    </section>

    <!-- Principles -->
    <section class="section-full section-alt">
        <div class="section-inner">
            <span class="section-label">Principles</span>
            <h2>Why we care.</h2>
            <div class="card-grid" style="margin-top: var(--space-xl);">
                <div class="card"
                     style="background: rgba(247,249,251,0.05);
                            border-color: rgba(46,139,192,0.15);">
                    <h3 style="color: var(--color-text);">Offline-First</h3>
                    <p style="color: var(--color-text-muted);">
                        Your data should live on your machine. Not in someone
                        else's cloud. Not contingent on an active subscription
                        or an uptime SLA you didn't sign up for.
                    </p>
                </div>
                <div class="card"
                     style="background: rgba(247,249,251,0.05);
                            border-color: rgba(46,139,192,0.15);">
                    <h3 style="color: var(--color-text);">No Subscriptions</h3>
                    <p style="color: var(--color-text-muted);">
                        One-time purchase. When you own it, you own it. Access
                        never expires. Files open next year exactly as they do today.
                    </p>
                </div>
                <div class="card"
                     style="background: rgba(247,249,251,0.05);
                            border-color: rgba(46,139,192,0.15);">
                    <h3 style="color: var(--color-text);">Coastal Values</h3>
                    <p style="color: var(--color-text-muted);">
                        Maine shapes how we work — direct, unhurried, built to last.
                        Software carved from the same granite that lines this coast.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- The Builder -->
    <section class="split-section reverse">
        <div class="split-content">
            <span class="section-label">The Builder</span>
            <h2>Sole operator. Full stack.</h2>
            <p>
                SaltShore Systems is a one-person operation. Every line of code,
                every design decision, every support email comes from the same
                desk — overlooking the same tide.
            </p>
            <p>
                That means no support queue. No tiered feature tiers. No product
                managers deciding which bugs are "low priority." When something's
                broken, it gets fixed.
            </p>
            <a href="contact.php" class="btn-primary mt-md">Get in Touch</a>
        </div>
        <div class="split-visual">
            <span style="opacity: 0.3;">🌊</span>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

</main>
