/**
 * AttendX Service Worker
 * Handles offline caching and background sync for mobile PWA.
 */

const SW_VERSION   = 'attendx-v1.0.0';
const STATIC_CACHE = SW_VERSION + '-static';
const API_CACHE    = SW_VERSION + '-api';
const SYNC_TAG     = 'attendx-offline-sync';

// ─── Assets to cache on install ──────────────────────────────────────────────

const STATIC_ASSETS = [
  '/',
  '/dashboard',
  '/offline',
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/js/pwa.js',
  '/manifest.json',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
];

// API routes to cache with network-first strategy
const API_CACHE_PATTERNS = [
  /\/api\/v1\/employees/,
  /\/api\/v1\/departments/,
  /\/api\/v1\/designations/,
  /\/mobile\/leave/,
  /\/mobile\/profile/,
];

// Routes that should NEVER be cached
const NEVER_CACHE = [
  /\/api\/v1\/token/,
  /\/mobile\/login/,
  /\/mobile\/logout/,
  /\/mobile\/push-token/,
  /\/system\//,
  /\/payroll\/process/,
];

// ─── Install ──────────────────────────────────────────────────────────────────

self.addEventListener('install', (event) => {
  console.log('[SW] Installing', SW_VERSION);
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => {
      return cache.addAll(STATIC_ASSETS.filter(url => !url.startsWith('http') || url.startsWith(self.location.origin)));
    }).then(() => {
      console.log('[SW] Static assets cached');
      return self.skipWaiting();
    }).catch((err) => {
      console.warn('[SW] Cache install error (non-fatal):', err);
      return self.skipWaiting();
    })
  );
});

// ─── Activate ─────────────────────────────────────────────────────────────────

self.addEventListener('activate', (event) => {
  console.log('[SW] Activating', SW_VERSION);
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys
          .filter(k => k.startsWith('attendx-') && k !== STATIC_CACHE && k !== API_CACHE)
          .map(k => {
            console.log('[SW] Deleting old cache:', k);
            return caches.delete(k);
          })
      );
    }).then(() => self.clients.claim())
  );
});

// ─── Fetch Strategy ───────────────────────────────────────────────────────────

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET and never-cache routes
  if (request.method !== 'GET') {
    return handleMutation(event);
  }

  if (NEVER_CACHE.some(p => p.test(url.pathname))) {
    event.respondWith(networkOnly(request));
    return;
  }

  // API: network-first, fall back to cache
  if (API_CACHE_PATTERNS.some(p => p.test(url.pathname))) {
    event.respondWith(networkFirst(request, API_CACHE, 5000));
    return;
  }

  // Static assets: cache-first
  if (
    url.pathname.startsWith('/assets/') ||
    url.pathname.endsWith('.css') ||
    url.pathname.endsWith('.js') ||
    url.pathname.endsWith('.png') ||
    url.pathname.endsWith('.jpg') ||
    url.pathname.endsWith('.svg') ||
    url.pathname.endsWith('.woff2')
  ) {
    event.respondWith(cacheFirst(request, STATIC_CACHE));
    return;
  }

  // HTML pages: stale-while-revalidate
  if (request.headers.get('Accept')?.includes('text/html')) {
    event.respondWith(staleWhileRevalidate(request));
    return;
  }

  event.respondWith(networkOnly(request));
});

// ─── Cache Strategies ─────────────────────────────────────────────────────────

async function cacheFirst(request, cacheName) {
  const cached = await caches.match(request);
  if (cached) return cached;
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    return offlineFallback(request);
  }
}

async function networkFirst(request, cacheName, timeout = 4000) {
  try {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeout);
    const response = await fetch(request, { signal: controller.signal });
    clearTimeout(timer);
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    const cached = await caches.match(request);
    return cached || offlineFallback(request);
  }
}

async function networkOnly(request) {
  try {
    return await fetch(request);
  } catch {
    return offlineFallback(request);
  }
}

async function staleWhileRevalidate(request) {
  const cached = await caches.match(request);
  const fetchPromise = fetch(request).then((response) => {
    if (response.ok) {
      caches.open(STATIC_CACHE).then(cache => cache.put(request, response.clone()));
    }
    return response;
  }).catch(() => null);

  return cached || await fetchPromise || offlineFallback(request);
}

function offlineFallback(request) {
  if (request.headers.get('Accept')?.includes('text/html')) {
    return caches.match('/offline') || new Response(
      '<h1>You are offline</h1><p>Please check your connection.</p>',
      { headers: { 'Content-Type': 'text/html' } }
    );
  }
  if (request.headers.get('Accept')?.includes('application/json')) {
    return new Response(
      JSON.stringify({ error: 'offline', message: 'No network connection.' }),
      { status: 503, headers: { 'Content-Type': 'application/json' } }
    );
  }
  return new Response('Offline', { status: 503 });
}

// ─── Mutation Queuing (POST/PATCH/DELETE offline) ─────────────────────────────

const OFFLINE_QUEUE_KEY = 'attendx-offline-queue';

function handleMutation(event) {
  const { request } = event;

  // For critical mutations, queue if offline
  const queueable = ['/mobile/leave/apply', '/mobile/attendance'];
  const url = new URL(request.url);

  if (!queueable.some(p => url.pathname.startsWith(p))) {
    return; // Let it fall through to network
  }

  event.respondWith(
    fetch(request.clone()).catch(async () => {
      // Store request in IndexedDB for later sync
      await queueOfflineRequest(request.clone());

      // Register background sync
      if (self.registration.sync) {
        await self.registration.sync.register(SYNC_TAG);
      }

      return new Response(
        JSON.stringify({ success: false, offline: true, message: 'Queued for sync when online.' }),
        { status: 202, headers: { 'Content-Type': 'application/json' } }
      );
    })
  );
}

async function queueOfflineRequest(request) {
  const db   = await openOfflineDB();
  const body = await request.text();
  const tx   = db.transaction('queue', 'readwrite');
  tx.objectStore('queue').add({
    url:     request.url,
    method:  request.method,
    headers: Object.fromEntries(request.headers.entries()),
    body:    body,
    queued:  Date.now(),
  });
}

function openOfflineDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open('attendx-offline', 1);
    req.onupgradeneeded = (e) => {
      e.target.result.createObjectStore('queue', { autoIncrement: true });
    };
    req.onsuccess = (e) => resolve(e.target.result);
    req.onerror   = (e) => reject(e.target.error);
  });
}

// ─── Background Sync ─────────────────────────────────────────────────────────

self.addEventListener('sync', (event) => {
  if (event.tag === SYNC_TAG) {
    console.log('[SW] Background sync triggered');
    event.waitUntil(flushOfflineQueue());
  }
});

async function flushOfflineQueue() {
  const db    = await openOfflineDB();
  const tx    = db.transaction('queue', 'readwrite');
  const store = tx.objectStore('queue');
  const items = await new Promise((res, rej) => {
    const req = store.getAll();
    req.onsuccess = () => res(req.result);
    req.onerror   = () => rej(req.error);
  });
  const keys = await new Promise((res, rej) => {
    const req = store.getAllKeys();
    req.onsuccess = () => res(req.result);
    req.onerror   = () => rej(req.error);
  });

  for (let i = 0; i < items.length; i++) {
    const item = items[i];
    try {
      const response = await fetch(item.url, {
        method:  item.method,
        headers: item.headers,
        body:    item.body || undefined,
      });
      if (response.ok) {
        const delTx = db.transaction('queue', 'readwrite');
        delTx.objectStore('queue').delete(keys[i]);
        console.log('[SW] Synced queued request:', item.url);
      }
    } catch (err) {
      console.warn('[SW] Sync failed for', item.url, err);
    }
  }

  // Notify clients that sync is complete
  const clients = await self.clients.matchAll({ type: 'window' });
  clients.forEach(client => client.postMessage({ type: 'SYNC_COMPLETE', count: items.length }));
}

// ─── Push Notifications ───────────────────────────────────────────────────────

self.addEventListener('push', (event) => {
  if (!event.data) return;

  let data;
  try {
    data = event.data.json();
  } catch {
    data = { title: 'AttendX', body: event.data.text() };
  }

  const options = {
    body:              data.body    || '',
    icon:              data.icon    || '/assets/images/icon-192.png',
    badge:             data.badge   || '/assets/images/icon-72.png',
    tag:               data.tag     || 'attendx-notification',
    data:              data.data    || {},
    vibrate:           [200, 100, 200],
    requireInteraction: data.require_interaction ?? false,
    actions: data.actions || [],
  };

  event.waitUntil(
    self.registration.showNotification(data.title || 'AttendX', options)
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data?.url || '/dashboard';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          client.focus();
          client.navigate(url);
          return;
        }
      }
      return self.clients.openWindow(url);
    })
  );
});
