# PWA Back Button Bug - Debug Summary
## Date: 2026-02-04

## The Problem
When pressing the back button in the PWA on Android:
1. The app closes completely instead of:
   - Closing the notification modal (if open)
   - Staying in the app (if no modal)
2. User should NEVER see the login page when pressing back
3. Back should ONLY close modals or do nothing - never exit the app

## Technical Background

### PWA Navigation Requirements
- PWA runs as standalone app (no browser chrome)
- Back button is the phone's gesture/button
- User expects: back closes modal or does nothing
- App should never show login page on back press

### History Stack Issue
When PWA opens:
- `history.length = 1` (only the dashboard page)
- We push a state: `history.length = 2`
- When back is pressed from our pushed state, it should pop to the original page
- BUT: On Android PWA, pressing back seems to exit the app entirely instead of triggering `popstate`

## What We Tried

### 1. HTTP Header Redirects (Instead of JavaScript)
**Goal:** Prevent login page from appearing in history

**Changes made:**
- `/auth/redirect-handler.php`: Changed to HTTP 303 redirect after login
- `/auth/login.php`: Added `header('Location: ...')` for already-logged-in users
- `/dashboard/index.php`: Changed to HTTP header redirect
- `/index.php`: Using HTTP headers for redirects

**Result:** Login page shouldn't be in history stack

### 2. No-Cache Headers
**Goal:** Prevent login page from being cached in bfcache

**Changes made to `/auth/login.php`:**
```php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
```

Also added to `/index.php` and other redirect pages.

### 3. BFCache Protection
**Goal:** If login page loads from cache, redirect to dashboard

**Added to `/auth/login.php`:**
```javascript
window.addEventListener('pageshow', function(e) {
    if (e.persisted) {
        fetch('/auth/check-session.php', { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                if (data.logged_in) {
                    location.replace('/dashboard/dashboards/cemeteries/');
                }
            });
    }
});
```

### 4. Comprehensive Back Button Handler
**Goal:** Intercept back button, close modals, prevent app exit

**Code in `/dashboard/dashboards/cemeteries/index.php`:**
```javascript
(function() {
    // Push initial state
    history.pushState({ app: 'cemeteries', type: 'initial' }, '');

    // Handle back button
    window.addEventListener('popstate', function(e) {
        // Check for open modal
        var overlay = document.querySelector('.notification-overlay');
        if (overlay && NotificationTemplates.activeModal) {
            NotificationTemplates.close();
        }

        // Push new state to prevent exit
        history.pushState({ app: 'cemeteries', type: 'after_back' }, '');
    });
})();
```

**Result:** The `popstate` event is NOT firing at all!

## Debug Log Analysis

### Key Findings from Server Logs

**Good signs:**
- `PAGE_LOAD` - Handler starts correctly
- `INITIAL_STATE_PUSHED` - State pushed, history.length: 1 -> 2
- `HANDLER_READY` - All listeners registered

**Bad signs:**
- **NO `BACK_BUTTON_PRESSED` event ever logged!**
- `PAGE_HIDE` with `persisted: false` - Page completely destroyed

### Log Sample
```json
{
  "event": "PAGE_HIDE",
  "eventData": {
    "persisted": false,
    "message": "Page hidden (no cache)"
  },
  "history": {
    "length": 2,
    "currentState": {"app": "cemeteries", "type": "initial"}
  },
  "modals": {
    "hasActiveModal": true,
    "activeModalClass": "notification-overlay entity-approval-overlay"
  },
  "environment": {
    "isPWA": true
  }
}
```

### What This Means
1. The `popstate` event is **NOT** being triggered
2. Android PWA interprets back as "exit app" gesture
3. The page is completely unloaded (not cached)
4. Our `pushState` approach doesn't work on Android PWA

## Environment Details
- Device: Android 10
- Browser: Chrome 144
- Mode: Standalone PWA (`isPWA: true`)
- Screen: 384x787 (mobile)

## Files Modified
1. `/auth/login.php` - No-cache headers, bfcache protection
2. `/auth/redirect-handler.php` - HTTP 303 redirect
3. `/auth/check-session.php` - NEW: Session check API
4. `/index.php` - No-cache headers, HTTP redirects
5. `/dashboard/index.php` - HTTP header redirect
6. `/dashboard/dashboards/cemeteries/index.php` - Back button handler
7. `/dashboard/dashboards/cemeteries/notifications/templates/entity-approval-notification.js` - Removed history manipulation

## Possible Solutions to Try Next

### 1. Service Worker Navigation Handler
The Service Worker might be able to intercept navigation:
```javascript
// In service-worker.js
self.addEventListener('fetch', event => {
    if (event.request.mode === 'navigate') {
        // Handle navigation
    }
});
```

### 2. beforeunload Prevention
Try to prevent the page from unloading:
```javascript
window.addEventListener('beforeunload', function(e) {
    e.preventDefault();
    e.returnValue = '';
    return '';
});
```
Note: This usually shows a confirmation dialog, not ideal for UX.

### 3. Multiple History States
Push multiple states to create a "buffer":
```javascript
for (var i = 0; i < 5; i++) {
    history.pushState({ buffer: i }, '');
}
```
This might give more room before exiting the app.

### 4. Android-Specific PWA Manifest Options
Check if there are manifest options for back button behavior:
- `"display": "standalone"` vs `"fullscreen"`
- `"orientation"` settings
- `"scope"` settings

### 5. Web App Manifest - Handle Links
```json
{
  "handle_links": "preferred"
}
```

### 6. Use `beforeinstallprompt` and `appinstalled` Events
Track PWA installation state and adjust behavior.

### 7. Consider Native App Approach
If PWA back button behavior can't be controlled, consider:
- TWA (Trusted Web Activity) wrapper
- Capacitor/Cordova wrapper
- React Native / Flutter

## Debug Logging System
A comprehensive logging system was added to `/dashboard/dashboards/cemeteries/index.php`:
- Logs to: `/dashboard/dashboards/cemeteries/logs/debug.log`
- API: `/dashboard/dashboards/cemeteries/api/debug-log.php`
- Events: PAGE_LOAD, INITIAL_STATE_PUSHED, HANDLER_READY, BACK_BUTTON_PRESSED, MODAL_DETECTED, PAGE_HIDE, VISIBILITY_CHANGE

## Session Storage Keys
- `login_notifications_shown` - Tracks if notifications were shown this session

## Next Steps
1. Research Android PWA back button behavior specifically
2. Try Service Worker navigation handling
3. Consider pushing multiple history states as buffer
4. Test on iOS to see if behavior differs
5. Consider if beforeunload can help (with UX tradeoffs)

## Git Commits Made
- `chore: add logs directory for debug logging`
- `fix: use var instead of const/let, add try-catch for debugging`
