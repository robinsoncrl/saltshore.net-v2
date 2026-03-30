<?php
/**
 * SaltShore Systems V2 — Contact
 *
 * Form posts to ../contact/submit.php (the existing handler in the V1 tree).
 * Served from saltshore.net/ root, this resolves to saltshore.net/contact/submit.php.
 */
$pageTitle       = 'Contact — SaltShore Systems';
$pageDescription = 'Get in touch with SaltShore Systems. Questions about CalGen, FinPro, LedgerPro, or anything else — reach out directly.';
include 'includes/head.php';
include 'includes/sidebar.php';
?>

<main class="main-content">

    <!-- Hero -->
    <section class="hero hero-sm">
        <p class="hero-tagline">Contact</p>
        <h1>Get in Touch.</h1>
        <p class="hero-subhead">
            Questions, feedback, or just want to talk shop &mdash; send a message
            and you&#8217;ll hear back from the same person who wrote the code.
        </p>
    </section>

    <!-- Contact Form -->
    <section class="section">
        <div class="form-section">

            <form action="../contact/submit.php"
                  method="POST"
                  novalidate>

                <div class="form-group">
                    <label for="contact-name">Name</label>
                    <input type="text"
                           id="contact-name"
                           name="name"
                           autocomplete="name"
                           required
                           placeholder="Your name">
                </div>

                <div class="form-group">
                    <label for="contact-email">Email</label>
                    <input type="email"
                           id="contact-email"
                           name="email"
                           autocomplete="email"
                           required
                           placeholder="you@example.com">
                </div>

                <div class="form-group">
                    <label for="contact-subject">Subject</label>
                    <input type="text"
                           id="contact-subject"
                           name="subject"
                           required
                           placeholder="What's this about?">
                </div>

                <div class="form-group">
                    <label for="contact-message">Message</label>
                    <textarea id="contact-message"
                              name="message"
                              required
                              placeholder="Your message..."></textarea>
                </div>

                <button type="submit" class="btn-primary">Send Message</button>

            </form>

            <!-- Direct contact -->
            <div style="margin-top: var(--space-2xl);
                        padding-top: var(--space-2xl);
                        border-top: 1px solid rgba(10,26,47,0.1);">
                <h3 style="color: var(--color-primary);
                           margin-bottom: var(--space-md);">
                    Or reach out directly.
                </h3>
                <p>
                    <a href="mailto:support@saltshore.net">
                        support@saltshore.net
                    </a>
                </p>
                <p class="mt-md" style="font-size: 0.9rem; color: #6a7a8a;">
                    Response time: typically within one business day.
                </p>
                <div style="margin-top: var(--space-lg);
                            display: flex;
                            gap: var(--space-md);
                            flex-wrap: wrap;">
                    <a href="https://www.facebook.com/profile.php?id=61581291097599"
                       target="_blank"
                       rel="noopener noreferrer"
                       style="font-size: 0.9rem;">Facebook</a>
                </div>
            </div>

        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

</main>
