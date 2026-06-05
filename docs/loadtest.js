// Stage-1 load / performance check (k6).
//
//   k6 run -e BASE=https://booking.pi2.co.id docs/loadtest.js
//
// Run against STAGING, not prod, to avoid skewing prod metrics. The default
// scenario hits the shallow /up boot check; to exercise the authenticated
// booking/calendar pages, pass a session cookie via -e COOKIE="...".
//
// Record p95 latency + error rate in docs/decision-log.md (Step 5).

import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  vus: 25,
  duration: '2m',
  thresholds: {
    http_req_duration: ['p(95)<800'], // budget: p95 under 800ms
    http_req_failed: ['rate<0.01'],   // error rate under 1%
  },
};

const BASE = __ENV.BASE || 'http://localhost';
const COOKIE = __ENV.COOKIE || '';

export default function () {
  const params = COOKIE ? { headers: { Cookie: COOKIE } } : {};

  const up = http.get(`${BASE}/up`, params);
  check(up, { 'up 200': (r) => r.status === 200 });

  // Authenticated pages — only meaningful when COOKIE is set.
  if (COOKIE) {
    const dash = http.get(`${BASE}/dashboard`, params);
    check(dash, { 'dashboard 200': (r) => r.status === 200 });
    const cal = http.get(`${BASE}/calendar`, params);
    check(cal, { 'calendar 200': (r) => r.status === 200 });
  }

  sleep(1);
}
