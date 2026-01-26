<?php
/**
 * iOS PWA Meta Tags
 * כולל את כל המטא-תגיות הנדרשות ל-iOS PWA
 *
 * שימוש: <?php include __DIR__ . '/pwa/splash/ios-meta-tags.php'; ?>
 */
?>
<!-- iOS PWA Configuration -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="חברה קדישא">
<meta name="format-detection" content="telephone=no">

<!-- iOS Icons -->
<link rel="apple-touch-icon" href="/pwa/icons/ios/180.png">
<link rel="apple-touch-icon" sizes="152x152" href="/pwa/icons/ios/152.png">
<link rel="apple-touch-icon" sizes="167x167" href="/pwa/icons/ios/167.png">
<link rel="apple-touch-icon" sizes="180x180" href="/pwa/icons/ios/180.png">

<!-- iOS Splash Screens -->
<?php
// iPhone 15 Pro Max, 14 Pro Max - 430x932pt @3x
?>
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=1290&h=2796" media="(device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=2796&h=1290" media="(device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3) and (orientation: landscape)">

<?php
// iPhone 15 Pro, 15, 14 Pro - 393x852pt @3x
?>
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=1179&h=2556" media="(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=2556&h=1179" media="(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3) and (orientation: landscape)">

<?php
// iPhone 14 Plus, 13 Pro Max, 12 Pro Max - 428x926pt @3x
?>
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=1284&h=2778" media="(device-width: 428px) and (device-height: 926px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=2778&h=1284" media="(device-width: 428px) and (device-height: 926px) and (-webkit-device-pixel-ratio: 3) and (orientation: landscape)">

<?php
// iPhone 14, 13 Pro, 13, 12 Pro, 12 - 390x844pt @3x
?>
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=1170&h=2532" media="(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=2532&h=1170" media="(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3) and (orientation: landscape)">

<?php
// iPhone 13 mini, 12 mini - 375x812pt @3x
?>
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=1125&h=2436" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=2436&h=1125" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3) and (orientation: landscape)">

<?php
// iPhone 11 Pro Max, XS Max - 414x896pt @3x
?>
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=1242&h=2688" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=2688&h=1242" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 3) and (orientation: landscape)">

<?php
// iPhone 11, XR - 414x896pt @2x
?>
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=828&h=1792" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=1792&h=828" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)">

<?php
// iPhone 11 Pro, X, XS - 375x812pt @3x (covered above with 13 mini)
?>

<?php
// iPhone 8 Plus, 7 Plus, 6s Plus, 6 Plus - 414x736pt @3x
?>
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=1242&h=2208" media="(device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=2208&h=1242" media="(device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3) and (orientation: landscape)">

<?php
// iPhone 8, 7, 6s, 6, SE 2/3 - 375x667pt @2x
?>
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=750&h=1334" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=1334&h=750" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)">

<?php
// iPhone SE 1, 5s, 5c, 5 - 320x568pt @2x
?>
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=640&h=1136" media="(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=1136&h=640" media="(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)">

<?php
// iPad Pro 12.9" - 1024x1366pt @2x
?>
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=2048&h=2732" media="(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=2732&h=2048" media="(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)">

<?php
// iPad Pro 11" - 834x1194pt @2x
?>
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=1668&h=2388" media="(device-width: 834px) and (device-height: 1194px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=2388&h=1668" media="(device-width: 834px) and (device-height: 1194px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)">

<?php
// iPad Pro 10.5", iPad Air 3 - 834x1112pt @2x
?>
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=1668&h=2224" media="(device-width: 834px) and (device-height: 1112px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=2224&h=1668" media="(device-width: 834px) and (device-height: 1112px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)">

<?php
// iPad Air 4, 5 - 820x1180pt @2x
?>
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=1640&h=2360" media="(device-width: 820px) and (device-height: 1180px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=2360&h=1640" media="(device-width: 820px) and (device-height: 1180px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)">

<?php
// iPad 10.2" - 810x1080pt @2x
?>
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=1620&h=2160" media="(device-width: 810px) and (device-height: 1080px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=2160&h=1620" media="(device-width: 810px) and (device-height: 1080px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)">

<?php
// iPad Mini 6 - 744x1133pt @2x
?>
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=1488&h=2266" media="(device-width: 744px) and (device-height: 1133px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=2266&h=1488" media="(device-width: 744px) and (device-height: 1133px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)">

<?php
// iPad Mini 5, iPad 9.7", iPad Pro 9.7" - 768x1024pt @2x
?>
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=1536&h=2048" media="(device-width: 768px) and (device-height: 1024px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/pwa/splash/generate.php?w=2048&h=1536" media="(device-width: 768px) and (device-height: 1024px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)">

<!-- Splash Screen CSS & JS -->
<link rel="stylesheet" href="/pwa/splash/splash-screen.css">
<script src="/pwa/splash/splash-screen.js" defer></script>
