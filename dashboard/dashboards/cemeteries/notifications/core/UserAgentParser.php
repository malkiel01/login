<?php
/**
 * UserAgentParser - User-Agent String Parser Utility
 *
 * Parses User-Agent strings to extract device, OS, and browser information.
 * Used by both push-log.php (file logging) and NotificationLogger (DB logging).
 *
 * SINGLE SOURCE OF TRUTH for User-Agent parsing in the notification system.
 *
 * USAGE:
 * ```php
 * require_once 'UserAgentParser.php';
 *
 * // Parse detailed device info
 * $info = UserAgentParser::parse($userAgent);
 * // Returns: ['device' => 'iPhone', 'os' => 'iOS', 'browser' => 'Safari']
 *
 * // Detect device type (for responsive UI)
 * $deviceType = UserAgentParser::detectDeviceType();
 * // Returns: 'mobile' or 'desktop'
 * ```
 *
 * @package     Notifications
 * @subpackage  Core
 * @version     1.1.0 - Added detectDeviceType() for mobile/desktop detection
 * @since       1.0.0
 */

/**
 * UserAgentParser - Static utility class for parsing User-Agent strings
 *
 * @package Notifications
 * @since   1.0.0
 */
class UserAgentParser {

    /**
     * Parse User-Agent string to extract device information
     *
     * Analyzes the User-Agent header to determine device type, OS, and browser.
     *
     * @param string|null $ua User-Agent string (defaults to $_SERVER['HTTP_USER_AGENT'])
     *
     * @return array Device info with keys:
     *               - device: 'iPhone'|'iPad'|'Android Phone'|'Android Tablet'|'Desktop'|'Unknown'
     *               - os: 'iOS'|'Android'|'Windows'|'macOS'|'Linux'|'Unknown'
     *               - browser: 'Chrome'|'Safari'|'Firefox'|'Edge'|'Unknown'
     */
    public static function parse(?string $ua = null): array {
        $ua = $ua ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');

        $device = 'Unknown';
        $os = 'Unknown';
        $browser = 'Unknown';

        // Detect OS and Device
        if (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            $os = 'iOS';
            $device = preg_match('/iPad/i', $ua) ? 'iPad' : 'iPhone';
        } elseif (preg_match('/Android/i', $ua)) {
            $os = 'Android';
            $device = 'Android Phone';
            if (preg_match('/Mobile/i', $ua) === 0) {
                $device = 'Android Tablet';
            }
        } elseif (preg_match('/Windows/i', $ua)) {
            $os = 'Windows';
            $device = 'Desktop';
        } elseif (preg_match('/Mac OS X/i', $ua)) {
            $os = 'macOS';
            $device = 'Desktop';
        } elseif (preg_match('/Linux/i', $ua)) {
            $os = 'Linux';
            $device = 'Desktop';
        }

        // Detect Browser
        if (preg_match('/Edg/i', $ua)) {
            $browser = 'Edge';
        } elseif (preg_match('/Chrome/i', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari/i', $ua) && !preg_match('/Chrome/i', $ua)) {
            $browser = 'Safari';
        } elseif (preg_match('/Firefox/i', $ua)) {
            $browser = 'Firefox';
        }

        return [
            'device' => $device,
            'os' => $os,
            'browser' => $browser
        ];
    }

    /**
     * Get shortened User-Agent string
     *
     * Returns the first N characters of the User-Agent for logging purposes.
     *
     * @param string|null $ua     User-Agent string
     * @param int         $length Maximum length (default: 100)
     *
     * @return string Shortened User-Agent string
     */
    public static function getShort(?string $ua = null, int $length = 100): string {
        $ua = $ua ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
        return substr($ua, 0, $length);
    }

    /**
     * Detect device type (mobile or desktop)
     *
     * Checks cookie first, then falls back to User-Agent parsing.
     * Used by UI pages to adapt layout to device type.
     *
     * @return string 'mobile' or 'desktop'
     */
    public static function detectDeviceType(): string {
        // Check cookie first (set by client-side detection)
        if (isset($_COOKIE['deviceType']) && in_array($_COOKIE['deviceType'], ['mobile', 'desktop'])) {
            return $_COOKIE['deviceType'];
        }

        // Fall back to User-Agent parsing
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $mobileKeywords = ['Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'BlackBerry', 'Windows Phone'];

        foreach ($mobileKeywords as $keyword) {
            if (stripos($userAgent, $keyword) !== false) {
                return 'mobile';
            }
        }

        return 'desktop';
    }
}
