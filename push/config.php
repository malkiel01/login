<?php
/**
 * Web Push Configuration
 * @version 2.0.0
 */

// VAPID Public Key (base64url encoded, 65 bytes uncompressed point)
define('VAPID_PUBLIC_KEY', 'BNirEjFNcwY5MxAJZBTs3wcaL2TF96HCODHVDzs9H_0w8kBRoIvYDYtz-lpz1ObArNqwyQmCp3F56sOY5l-twt4');

// VAPID Private Key (PEM format)
define('VAPID_PRIVATE_KEY_PEM', '-----BEGIN PRIVATE KEY-----
MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgRIDikZdCZ2k/w5Ot
Ixs3Jt6ZRLaXLb2uCnT1WEss/eGhRANCAATYqxIxTXMGOTMQCWQU7N8HGi9kxfeh
wjgx1Q87PR/9MPJAUaCL2A2Lc/pac9TmwKzasMkJgqdxeerDmOZfrcLe
-----END PRIVATE KEY-----');

// Contact email for VAPID
define('VAPID_SUBJECT', 'mailto:052840@gmail.com');

/**
 * Get VAPID public key for frontend
 */
function getVapidPublicKey(): string {
    return VAPID_PUBLIC_KEY;
}
