<?php
/**
 * SaltShore Systems V2 — includes/sidebar.php
 *
 * Renders: hamburger button, overlay backdrop, and the sticky sidebar nav.
 * Logo src: ../SSS_logo_2.png — resolves to saltshore.net/SSS_logo_2.png
 * when served from saltshore.net/ root.
 */
?>
<button class="hamburger"
        aria-label="Open navigation"
        aria-expanded="false"
        aria-controls="sidebar-nav">
    <span></span>
    <span></span>
    <span></span>
</button>

<div class="sidebar-overlay" aria-hidden="true"></div>

<nav class="sidebar" id="sidebar-nav" aria-label="Primary navigation">

    <!-- Logo block -->
    <a href="<?= $assetBase ?>index.php" class="sidebar-logo" aria-label="SaltShore Systems — Home">
        <img src="<?= $assetBase ?>assets/img/SSS_logo_2.png"
             alt="SaltShore Systems circular anchor logo"
             width="72"
             height="72">
        <span class="sidebar-logo-text">
            SALTSHORE SYSTEMS
            <span>Where tides meet tech.</span>
        </span>
    </a>

    <!-- Primary navigation -->
    <ul class="sidebar-nav">
        <li><a href="<?= $assetBase ?>index.php">Home</a></li>
        <li><a href="<?= $assetBase ?>about.php">About</a></li>
        <li><a href="<?= $assetBase ?>services.php">Products</a></li>
        <li><a href="<?= $assetBase ?>contact.php">Contact</a></li>
        <li><a href="<?= $assetBase ?>docs.php">Docs</a></li>
    </ul>

    <!-- CTA + contact -->
    <div class="sidebar-bottom">
        <a href="<?= $assetBase ?>contact.php"
           class="btn-primary"
           style="width: 100%; box-sizing: border-box;">
            Get in Touch
        </a>
        <a href="mailto:support@saltshore.net" class="sidebar-contact-link">
            <svg width="14" height="14" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 aria-hidden="true">
                <rect x="2" y="4" width="20" height="16" rx="2"/>
                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
            </svg>
            support@saltshore.net
        </a>
    </div>

</nav>
