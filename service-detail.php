<?php
/**
 * SaltShore Systems V2 — Individual Product Detail
 *
 * Usage: service-detail.php?product=calgen|finpro|ledgerpro
 * Unknown product keys redirect to services.php.
 */

// ——— Product data registry ———
$products = [
    'calgen' => [
        'name'        => 'CalGen',
        'category'    => '01 &mdash; Time',
        'tagline'     => 'A deterministic, offline iCalendar (.ics) generator and validator&mdash;built to survive real-world client incompatibilities without hiding them.',
        'description' => 'CalGen is a standards-first, offline tool for generating and validating iCalendar (.ics) files that remain portable across major calendar clients. The iCalendar standard (RFC 5545) is widely implemented inconsistently, and modern clients can silently reject or structurally damage events when calendars are non-compliant&mdash;often without any visible error. CalGen addresses the portion of this problem that is structurally solvable: deterministic generation, required-field correctness, property ordering compliance, stable UID behavior, correct all-day event formatting, and timezone correctness through canonical VTIMEZONE handling. It is intentionally honest about boundaries&mdash;it generates the most defensible standard-compliant file possible and surfaces limitations explicitly rather than hiding them.',
        'features'    => [
            'Timezone-safe output with canonical VTIMEZONE handling and explicit TZID strategy (no accidental floating time)',
            'Required-field correctness by construction (VERSION, PRODID, UID, DTSTART, DTSTAMP)',
            'Strict RFC&nbsp;5545 property ordering&mdash;prevents modern client parsers from silently dropping fields',
            'Resilient recurrence generation (RRULE, EXDATE, RECURRENCE-ID) designed to reduce DST drift and exception loss',
            'Stable deterministic UIDs for CalGen-generated events to support reconciliation and deduplication over time',
            'Correct all-day event formatting (DATE semantics and exclusive end-date rules)',
            'SEQUENCE versioning discipline to reduce stale-event persistence when updates are imported',
            'Local audit logging of generation events for defensible artifacts and compliance workflows',
            'Deterministic outputs: same input &rarr; same output, enabling versioning and verification',
        ],
        'who_for'     => 'Freelancers and sole operators who track scheduled work, recurring obligations, and client deliverables in calendar format&mdash;and need those files to be reliable, portable, and provably correct. Any workflow where iCalendar output must survive Outlook, Apple Calendar, and third-party import without silent data loss.',
        'price_note'  => 'One-time purchase. No subscription. Lifetime access.',
        'cta_text'    => 'Download CalGen',
        'icon'        => '📅',
        'logo'        => 'assets/img/calgen-logo.png',
        'status'      => 'available',
    ],
    'finpro' => [
        'name'        => 'FinPro',
        'category'    => '02 &mdash; Billing',
        'tagline'     => 'A deterministic, offline financial workstation that converts structured time into reproducible, defensible money artifacts.',
        'description' => 'FinPro is a deterministic, offline-first financial workstation designed to close the gap between work performed and money correctly documented. Freelancers, contractors, and households routinely lose income not because they charge too little, but because time tracking, calculation, and billing systems fail silently&mdash;through missed hours, manual transcription, spreadsheet formula drift, and inconsistent rate application. FinPro ingests structured time data (from CalGen or manual entry), applies explicit rate rules, and produces reproducible financial outputs: earnings summaries, invoices, receipts, expense records, and net-pay estimates. Every line item shows its derivation. Totals are always calculated, never typed. Rate definitions are versioned, calculations are immutable after issuance, and regenerated artifacts always match their originals.',
        'features'    => [
            'Deterministic earnings calculation from structured time, tasks, and projects (same input &rarr; same output)',
            'Explicit rule-based rate application (base, overtime, weekend, holiday, per-task, per-project)',
            'Versioned rate definitions with effective-date boundaries to prevent retroactive drift',
            'Integrated invoice and receipt generation directly from calculated data&mdash;no transcription step',
            'Immutable invoice line items preserving applied rates, quantities, and totals at issuance time',
            'Transparent gross-to-net estimation using user-defined deductions with labeled breakdowns',
            'Expense tracking with client/project attribution and direct invoice inclusion',
            'Spreadsheet-grade exports (CSV, JSON, XLS/XLSX) for verification and downstream use',
            'Fully offline operation with local data storage and no subscription dependency',
        ],
        'who_for'     => 'Freelancers and independent contractors who bill hourly or per-project, need invoice records that hold up under client or tax scrutiny, and refuse to pay a monthly subscription just to access their own financial data.',
        'price_note'  => 'One-time purchase. No subscription. Lifetime access.',
        'cta_text'    => 'Get FinPro',
        'icon'        => '💰',
        'logo'        => 'assets/img/finpro-logo.png',
        'status'      => 'coming-soon',
    ],
    'ledgerpro' => [
        'name'        => 'LedgerPro',
        'category'    => '03 &mdash; Records',
        'tagline'     => 'A deterministic, offline financial records normalization and reconciliation system&mdash;built to convert raw bank statement files into defensible, auditable transaction ledgers.',
        'description' => 'LedgerPro is a standards-first, offline system for converting bank statement files into a complete, defensible financial record. It treats file-based import as a first-class workflow, ingesting CSV, OFX, QFX, fixed-width reports, and text-based PDFs with deterministic normalization rules. Dates, amounts, debit/credit models, encodings, and schemas are resolved explicitly&mdash;ambiguous values are flagged rather than guessed. Duplicate transactions are detected using composite fingerprints. Transfers are modeled as first-class ledger events, refunds and reversals can be explicitly linked to original purchases, and missing statement periods are surfaced before tax time. LedgerPro does not pull live transactions, store credentials, interpret tax law, or forecast future behavior. It eliminates every error class that originates from a bank statement file, while making all remaining limitations visible rather than silent.',
        'features'    => [
            'First-class manual import of CSV, OFX, QFX, fixed-width, and text-based PDF statements&mdash;no live bank feeds required',
            'Deterministic normalization of dates, amounts, debit/credit models, encodings, and schemas',
            'Explicit handling of ambiguous data&mdash;no silent heuristics or guessing',
            'Duplicate transaction detection using composite fingerprints with user-visible resolution paths',
            'Transfer-aware reconciliation that prevents internal money movement from inflating income or expenses',
            'Explicit refund and reversal linking to preserve correct category and expense totals',
            'Vendor aliasing and ordered categorization rules with full provenance',
            'Statement coverage indexing to surface missing months or gaps before reconciliation or tax preparation',
            'Structured Financial Records Vault with consistent naming, integrity hashing, and long-term archival',
            'Policy-aware exports (CSV, OFX, QFX, JSON, XLS/XLSX) with clear labeling of downstream constraints',
            'Deterministic outputs: same input &rarr; same ledger, enabling verification and audit defensibility',
        ],
        'who_for'     => 'Freelancers and sole operators who manage their own finances, import statements from one or more institutions, and need a local record that reconciles against what they invoiced&mdash;without uploading their bank data to a third party.',
        'price_note'  => 'One-time purchase. No subscription. Lifetime access.',
        'cta_text'    => 'Get LedgerPro',
        'icon'        => '📊',
        'logo'        => 'assets/img/ledgerpro-logo.svg',
        'status'      => 'coming-soon',
    ],
];

// ——— Validate and resolve product key ———
$productKey = isset($_GET['product'])
    ? preg_replace('/[^a-z]/', '', strtolower(trim($_GET['product'])))
    : '';

if (!array_key_exists($productKey, $products)) {
    header('Location: services.php');
    exit;
}

$product = $products[$productKey];

$pageTitle       = htmlspecialchars($product['name']) . ' — SaltShore Systems';
$pageDescription = strip_tags($product['tagline']) . ' ' .
                   substr(strip_tags($product['description']), 0, 120) . '...';

include 'includes/head.php';
include 'includes/sidebar.php';
?>

<main class="main-content">

    <!-- Hero -->
    <section class="hero hero-sm">
        <p class="hero-tagline"><?= $product['category'] ?></p>
        <h1><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="hero-subhead">
            <?= htmlspecialchars($product['tagline'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php if ($product['status'] === 'coming-soon'): ?>
            <p style="font-size: 0.78rem; letter-spacing: 1.8px;
                      text-transform: uppercase;
                      color: rgba(247,249,251,0.45);
                      margin-top: var(--space-md);">Coming Soon</p>
        <?php endif; ?>
    </section>

    <!-- What it does -->
    <section class="split-section">
        <div class="split-content">
            <span class="section-label">What it does</span>
            <h2><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?> in plain English.</h2>
            <p><?= htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="split-visual">
            <?php if (!empty($product['logo'])): ?>
                <img src="<?= htmlspecialchars($product['logo'], ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?> logo"
                     style="max-width: 180px; height: auto; border-radius: var(--radius-lg);">
            <?php else: ?>
                <span style="opacity: 0.25;"><?= $product['icon'] ?></span>
            <?php endif; ?>
        </div>
    </section>

    <!-- Key Features -->
    <section class="section-full section-alt">
        <div class="section-inner">
            <span class="section-label">Key Features</span>
            <h2 style="color: var(--color-text);">What you get.</h2>
            <ul class="feature-list mt-lg">
                <?php foreach ($product['features'] as $feature): ?>
                    <li><?= $feature /* HTML entities pre-encoded in data array */ ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <!-- Who it's for -->
    <section class="split-section reverse">
        <div class="split-content">
            <span class="section-label">Who it&#8217;s for</span>
            <h2>Built for operators, not enterprises.</h2>
            <p><?= htmlspecialchars($product['who_for'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="split-visual">
            <span style="opacity: 0.25;">⚓</span>
        </div>
    </section>

    <!-- Purchase CTA -->
    <section class="cta-band">
        <div class="cta-band-inner">
            <h2><?= htmlspecialchars($product['cta_text'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars($product['price_note'], ENT_QUOTES, 'UTF-8') ?></p>
            <div class="btn-group" style="justify-content: center;">
                <?php if ($product['status'] === 'available'): ?>
                    <a href="contact.php" class="btn-primary">
                        <?= htmlspecialchars($product['cta_text'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php else: ?>
                    <span class="btn-primary"
                          style="opacity: 0.5; cursor: default;"
                          aria-disabled="true">Coming Soon</span>
                <?php endif; ?>
                <a href="services.php"
                   class="btn-ghost"
                   style="color: rgba(247,249,251,0.8);
                          border-color: rgba(247,249,251,0.2);">
                    &larr; Back to Suite
                </a>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

</main>
