/**
 * Push Subscription Manager
 * Handles Web Push subscription for the PWA
 *
 * @version 1.0.0
 */

const PushSubscriptionManager = {
    SUBSCRIPTION_API: '/push/subscription-api.php',
    vapidPublicKey: null,

    /**
     * Initialize push subscription manager
     */
    async init() {
        // Check if push is supported
        if (!this.isSupported()) {
            console.log('[Push] Push notifications not supported');
            return false;
        }

        console.log('[Push] Initializing push subscription manager');
        console.log('[Push] Notification.permission =', Notification.permission);

        // Get VAPID public key
        const vapidKey = await this.getVapidKey();
        console.log('[Push] VAPID key retrieved:', vapidKey ? 'YES' : 'NO');

        // Auto-subscribe if permission already granted
        if (Notification.permission === 'granted') {
            console.log('[Push] Permission granted, subscribing...');
            const result = await this.subscribe();
            console.log('[Push] Subscribe result:', result);
        } else if (Notification.permission === 'default') {
            console.log('[Push] Permission not yet requested');
        } else {
            console.log('[Push] Permission denied');
        }

        return true;
    },

    /**
     * Check if push notifications are supported
     */
    isSupported() {
        return 'serviceWorker' in navigator &&
               'PushManager' in window &&
               'Notification' in window;
    },

    /**
     * Get VAPID public key from server
     */
    async getVapidKey() {
        try {
            const response = await fetch(`${this.SUBSCRIPTION_API}?action=vapid_key`);
            const data = await response.json();

            if (data.success && data.publicKey) {
                this.vapidPublicKey = data.publicKey;
                console.log('[Push] VAPID key retrieved');
                return this.vapidPublicKey;
            }
        } catch (error) {
            console.error('[Push] Error getting VAPID key:', error);
        }
        return null;
    },

    /**
     * Request permission and subscribe to push
     */
    async subscribe() {
        if (!this.isSupported()) {
            return { success: false, error: 'Push not supported' };
        }

        // Request permission
        const permission = await Notification.requestPermission();

        if (permission !== 'granted') {
            console.log('[Push] Permission denied');
            return { success: false, error: 'Permission denied' };
        }

        try {
            // Get service worker registration
            const registration = await navigator.serviceWorker.ready;

            // Check for existing subscription
            let subscription = await registration.pushManager.getSubscription();

            if (!subscription) {
                // Get VAPID key if not already loaded
                if (!this.vapidPublicKey) {
                    await this.getVapidKey();
                }

                if (!this.vapidPublicKey) {
                    return { success: false, error: 'Failed to get VAPID key' };
                }

                // Subscribe to push
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey)
                });

                console.log('[Push] New subscription created');
            } else {
                console.log('[Push] Using existing subscription');
            }

            // Send subscription to server
            const result = await this.saveSubscription(subscription);

            return result;

        } catch (error) {
            console.error('[Push] Subscribe error:', error);
            return { success: false, error: error.message };
        }
    },

    /**
     * Unsubscribe from push notifications
     */
    async unsubscribe() {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (subscription) {
                // Notify server
                await this.removeSubscription(subscription.endpoint);

                // Unsubscribe locally
                await subscription.unsubscribe();

                console.log('[Push] Unsubscribed successfully');
                return { success: true };
            }

            return { success: true, message: 'No active subscription' };

        } catch (error) {
            console.error('[Push] Unsubscribe error:', error);
            return { success: false, error: error.message };
        }
    },

    /**
     * Save subscription to server
     */
    async saveSubscription(subscription) {
        try {
            const response = await fetch(this.SUBSCRIPTION_API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'subscribe',
                    subscription: subscription.toJSON()
                })
            });

            const data = await response.json();

            if (data.success) {
                console.log('[Push] Subscription saved to server');
                return { success: true, id: data.id };
            } else {
                console.error('[Push] Failed to save subscription:', data.error);
                return { success: false, error: data.error };
            }

        } catch (error) {
            console.error('[Push] Error saving subscription:', error);
            return { success: false, error: error.message };
        }
    },

    /**
     * Remove subscription from server
     */
    async removeSubscription(endpoint) {
        try {
            await fetch(this.SUBSCRIPTION_API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'unsubscribe',
                    endpoint: endpoint
                })
            });
        } catch (error) {
            console.error('[Push] Error removing subscription:', error);
        }
    },

    /**
     * Check if currently subscribed
     */
    async isSubscribed() {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            return !!subscription;
        } catch (error) {
            return false;
        }
    },

    /**
     * Convert VAPID key from base64 to Uint8Array
     */
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
};

// Auto-init when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        PushSubscriptionManager.init();
    });
} else {
    PushSubscriptionManager.init();
}

// Export for use in other modules
if (typeof window !== 'undefined') {
    window.PushSubscriptionManager = PushSubscriptionManager;
}
