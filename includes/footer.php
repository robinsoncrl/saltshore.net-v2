<?php
/**
 * SaltShore Systems V2 — includes/footer.php
 *
 * Renders the site footer AND closes </body></html>.
 * Must be the last include on every page.
 */

$assetBase = $assetBase ?? '';
?>
    <footer class="site-footer">
        <div class="footer-inner">

            <!-- Brand block -->
            <div class="footer-brand">
                <div class="footer-brand-row">
                    <a href="<?= $assetBase ?>portal/login.php"
                       class="footer-login-logo"
                       title="Business Owner Portal">
                        <img src="<?= $assetBase ?>assets/img/SSS_logo_2.png"
                             alt="SaltShore Systems — Owner Portal"
                             width="72"
                             height="72">
                    </a>
                    <div class="footer-brand-copy">
                        <strong>SALTSHORE SYSTEMS</strong>
                        <p>Offline-first, deterministic tools for freelancers and sole operators. Built in Maine.</p>
                        <div class="footer-social">
                            <a href="https://www.facebook.com/profile.php?id=61581291097599"
                               target="_blank"
                               rel="noopener noreferrer">Facebook</a>
                            <a href="mailto:support@saltshore.net">Email</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="footer-col">
                <h4>Navigation</h4>
                <ul>
                    <li><a href="<?= $assetBase ?>index.php">Home</a></li>
                    <li><a href="<?= $assetBase ?>about.php">About</a></li>
                    <li><a href="<?= $assetBase ?>services.php">Products</a></li>
                    <li><a href="<?= $assetBase ?>contact.php">Contact</a></li>
                    <li><a href="<?= $assetBase ?>docs.php">Docs</a></li>
                </ul>
            </div>

            <!-- Products -->
            <div class="footer-col">
                <h4>Products</h4>
                <ul>
                    <li><a href="<?= $assetBase ?>service-detail.php?product=calgen">CalGen</a></li>
                    <li><a href="<?= $assetBase ?>service-detail.php?product=finpro">FinPro</a></li>
                    <li><a href="<?= $assetBase ?>service-detail.php?product=ledgerpro">LedgerPro</a></li>
                </ul>
            </div>

            <!-- Connect -->
            <div class="footer-col">
                <h4>Connect</h4>
                <ul>
                    <li><a href="mailto:support@saltshore.net">support@saltshore.net</a></li>
                    <li>
                        <a href="https://www.facebook.com/profile.php?id=61581291097599"
                           target="_blank"
                           rel="noopener noreferrer">Facebook</a>
                    </li>
                </ul>
                <p class="footer-service-areas">
                    Service Areas: Maine &middot; Remote / Nationwide
                </p>
            </div>

        </div><!-- /.footer-inner -->

        <div class="footer-trust-row">
            <span>Offline-First Build</span>
            <span>Deterministic Systems</span>
            <span>No Forced Subscription Access</span>
        </div>

        <div class="footer-bottom">
            <p>&copy; 2026 SaltShore Systems &mdash; Where tides meet tech.</p>
            <a href="<?= $assetBase ?>privacy.php">Privacy Policy</a>
        </div>

    </footer>

</body>
</html>
