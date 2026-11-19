<script>
(() => {
    const options = @js($options);
    const messages = options.messages;
    const installed = navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;
    const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    const worker = 'serviceWorker' in navigator
        ? navigator.serviceWorker.register(options.worker, { scope: options.scope })
        : null;
    worker?.catch(() => {});

    async function request(url, method, payload) {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(payload),
        });
        if (!response.ok) {
            throw new Error(response.status === 409 ? messages.conflict : messages.request_failed);
        }
        return response;
    }

    window.emuniqExistingPwaProfile = () => {
        let registration;
        let subscription;

        return {
            installed,
            ready: false,
            active: false,
            blocked: false,
            busy: false,
            status: messages.checking,

            async init() {
                await this.refresh();
            },

            showState(active) {
                this.active = active;
                this.blocked = Notification.permission === 'denied';
                this.status = this.blocked ? messages.blocked : active ? messages.active : messages.inactive;
            },

            async refresh() {
                if (this.busy) return;
                if (!installed || !supported || !options.publicKey) {
                    this.status = !installed ? messages.install : !supported ? messages.unsupported : messages.unconfigured;
                    return;
                }

                this.busy = true;
                this.ready = false;
                try {
                    registration = await worker;
                    await navigator.serviceWorker.ready;
                    subscription = await registration.pushManager.getSubscription();
                    const response = subscription
                        ? await request(options.statusUrl, 'POST', { endpoint: subscription.endpoint })
                        : null;
                    this.showState(response !== null && (await response.json()).active === true);
                    this.ready = true;
                } catch (_) {
                    this.status = messages.status_failed;
                } finally {
                    this.busy = false;
                }
            },

            async subscribe() {
                if (!this.ready || this.busy || this.blocked) return;
                this.busy = true;
                try {
                    const key = options.publicKey.replace(/-/g, '+').replace(/_/g, '/');
                    const applicationServerKey = Uint8Array.from(
                        atob(key.padEnd(Math.ceil(key.length / 4) * 4, '=')),
                        byte => byte.charCodeAt(0),
                    );
                    subscription ??= await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey });
                    await request(options.subscribeUrl, 'POST', subscription.toJSON());
                    this.showState(true);
                } catch (error) {
                    if (Notification.permission === 'denied') {
                        this.showState(false);
                    } else {
                        this.status = error.message === messages.conflict ? messages.conflict : messages.request_failed;
                    }
                } finally {
                    this.busy = false;
                }
            },

            async unsubscribe() {
                if (!this.ready || this.busy || !subscription) return;
                this.busy = true;
                try {
                    await request(options.unsubscribeUrl, 'DELETE', { endpoint: subscription.endpoint });
                    this.showState(false);
                    await subscription.unsubscribe();
                    subscription = null;
                } catch (_) {
                    this.status = messages.request_failed;
                } finally {
                    this.busy = false;
                }
            },
        };
    };
})();
</script>
