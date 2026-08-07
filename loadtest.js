import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    stages: [
        { duration: '1m', target: 20 },  // ramp up to 20 virtual users
        { duration: '5m', target: 20 },  // hold at 20 concurrent users
        { duration: '1m', target: 0 },   // ramp down
    ],
    thresholds: {
        http_req_failed: ['rate<0.05'],   // fail the test if >5% of requests error
        http_req_duration: ['p(95)<2000'], // 95% of requests should complete under 2s
    },
};

const BASE_URL = 'https://thetest2-production.up.railway.app';

// --- OPTIONAL: authenticated-route testing ---
// If you want to test real logged-in pages (/dashboard, /chat, /api/conversations)
// instead of just the public landing page, log in via your browser, open DevTools
// > Application > Cookies, copy the value of your Laravel session cookie
// (usually named something like "laravel_session" or "<app_name>_session"),
// and set it below. Leave blank to test only public pages.
const SESSION_COOKIE_NAME = ''; // e.g. 'laravel_session'
const SESSION_COOKIE_VALUE = ''; // paste the cookie value here

function authHeaders() {
    if (SESSION_COOKIE_NAME && SESSION_COOKIE_VALUE) {
        return {
            headers: {
                Cookie: `${SESSION_COOKIE_NAME}=${SESSION_COOKIE_VALUE}`,
            },
        };
    }
    return {};
}

export default function () {
    // Always-safe: public landing page
    const res1 = http.get(`${BASE_URL}/`);
    check(res1, {
        'landing page status is 200': (r) => r.status === 200,
    });

    sleep(1);

    // Only runs meaningfully if you've set the session cookie above.
    // These are READ-ONLY GET endpoints — safe, no MSG91 dispatch.
    if (SESSION_COOKIE_NAME && SESSION_COOKIE_VALUE) {
        const res2 = http.get(`${BASE_URL}/dashboard`, authHeaders());
        check(res2, {
            'dashboard status is 200': (r) => r.status === 200,
        });

        sleep(1);

        const res3 = http.get(`${BASE_URL}/api/conversations`, authHeaders());
        check(res3, {
            'conversations API status is 200': (r) => r.status === 200,
        });

        sleep(1);
    }
}
