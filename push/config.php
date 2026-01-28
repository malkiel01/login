<?php
/**
 * Web Push Configuration
 * @version 1.0.0
 */

// VAPID Keys - DO NOT SHARE THE PRIVATE KEY!
define('VAPID_PUBLIC_KEY', 'BOJudeeQkZKFCSgVzyXZZTfQ1HihWPU4bOlEuAVfQYIBc4jccss2ue9axSbgeeFbj1JcMkXxgPqdWcchGSdwFm0');
define('VAPID_PRIVATE_KEY', 'fwtfCGg5kgQUV0tKu8iwyMyufgbXqZUnq76w_VpVBuo');
define('VAPID_SUBJECT', 'mailto:052840@gmail.com');

/**
 * Get VAPID public key for frontend
 */
function getVapidPublicKey(): string {
    return VAPID_PUBLIC_KEY;
}
