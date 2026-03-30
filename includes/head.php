<?php
/**
 * SaltShore Systems V2 — includes/head.php
 *
 * Usage: set $pageTitle and $pageDescription before including this file.
 * Asset paths are relative to the V2 root when served from the saltshore.net
 * parent directory: php -S localhost:8080  (from saltshore.net/)
 */
$pageTitle       = $pageTitle       ?? 'SaltShore Systems — Where Tides Meet Tech';
$pageDescription = $pageDescription ?? 'Offline-first, deterministic tools for freelancers and sole operators. CalGen, FinPro, and LedgerPro — no subscriptions, no cloud lock-in.';
$assetBase       = $assetBase       ?? '';
?><!DOCTYPE html>
<html lang="en" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="color-scheme" content="light">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="theme-color" content="#0A1A2F">

    <!-- Open Graph -->
    <meta property="og:title"       content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type"        content="website">
    <meta property="og:url"         content="https://saltshore.net/">
    <meta property="og:image"       content="https://saltshore.net/assets/SSS_logo_2.png">

    <!-- Favicon (relative to V2 root → ../assets/favicon/ = saltshore.net/assets/favicon/) -->
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon/favicon-16.png">
    <link rel="shortcut icon" href="../assets/favicon/favicon.ico">

    <!-- Stylesheet -->
    <link rel="stylesheet" href="<?= $assetBase ?>assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">

    <!-- JS (deferred) -->
    <script defer src="<?= $assetBase ?>assets/js/main.js"></script>
</head>
<body>
