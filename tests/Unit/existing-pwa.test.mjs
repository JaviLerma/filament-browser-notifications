import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

test('only activates the current device after the app confirms registration', async () => {
    const options = {
        worker: '/app-sw.js',
        scope: '/app/',
        publicKey: 'YQ',
        subscribeUrl: '/app/push/subscriptions',
        unsubscribeUrl: '/app/push/subscriptions',
        statusUrl: '/app/push/subscriptions/status',
        messages: { active: 'active', inactive: 'inactive', blocked: 'blocked', conflict: 'conflict' },
    };
    const subscription = {
        endpoint: 'https://fcm.googleapis.com/test',
        toJSON() { return { endpoint: this.endpoint }; },
        async unsubscribe() { unsubscribed = true; },
    };
    let browserSubscription = null;
    let unsubscribed = false;
    let responseCode = 409;
    let subscribeCalls = 0;
    const requests = [];
    const context = {
        window: { matchMedia: () => ({ matches: true }), Notification: {}, PushManager: {} },
        navigator: {
            serviceWorker: {
                ready: Promise.resolve(),
                async register(path, { scope }) {
                    assert.equal(path, '/app-sw.js');
                    assert.equal(scope, '/app/');
                    return {
                        pushManager: {
                            getSubscription: async () => browserSubscription,
                            subscribe() {
                                subscribeCalls++;
                                browserSubscription = subscription;
                                return Promise.resolve(subscription);
                            },
                        },
                    };
                },
            },
        },
        Notification: { permission: 'default' },
        document: { querySelector: () => ({ content: 'csrf-test' }) },
        Uint8Array,
        atob,
        async fetch(url, request) {
            requests.push([url, request.method, JSON.parse(request.body)]);
            return {
                ok: responseCode === 200,
                status: responseCode,
                json: async () => ({ active: true }),
            };
        },
    };
    const view = readFileSync(new URL('../../resources/views/existing-pwa-script.blade.php', import.meta.url), 'utf8');
    vm.runInNewContext(view.replace('@js($options)', JSON.stringify(options)).replace(/<\/?script>/g, ''), context);

    const profile = context.window.emuniqExistingPwaProfile();
    await profile.init();
    assert.equal(profile.active, false);

    const activation = profile.subscribe();
    assert.equal(subscribeCalls, 1);
    await activation;
    assert.equal(profile.active, false);
    assert.equal(profile.status, 'conflict');

    responseCode = 200;
    context.Notification.permission = 'granted';
    await profile.subscribe();
    assert.equal(profile.active, true);
    assert.equal(subscribeCalls, 1);

    context.Notification.permission = 'denied';
    await profile.refresh();
    assert.equal(profile.active, true);
    assert.equal(profile.blocked, true);
    assert.deepEqual(requests.at(-1), ['/app/push/subscriptions/status', 'POST', { endpoint: subscription.endpoint }]);

    await profile.unsubscribe();
    assert.equal(profile.active, false);
    assert.equal(unsubscribed, true);
    assert.deepEqual(requests.at(-1), ['/app/push/subscriptions', 'DELETE', { endpoint: subscription.endpoint }]);
});
