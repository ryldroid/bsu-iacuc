// ===== Cache settings =====
const CACHE_VERSION = "bsu-iacuc-v8";
const FILE_CACHE = "bsu-iacuc-files-v1";
const MAX_CACHED_FILES = 150;

const PRECACHE_URLS = [
  "offline.html",
  "assets/images/bsu.webp",
  "assets/images/ccard.webp",
  "assets/images/favicon.ico",
];

// Never cached: live polling, downloads and server-side tools
const SKIP_PATHS = [
  "/checkupdates",
  "/user/ping",
  "/verification_status",
  "/download_selected",
  "/records_export",
  "/downloadauditlogs",
  "/backups",
];

// ===== Install =====
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(CACHE_VERSION)
      .then((cache) =>
        Promise.allSettled(PRECACHE_URLS.map((u) => cache.add(u))),
      ),
  );
  self.skipWaiting();
});

// ===== Activate (clean old caches) =====
self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key !== CACHE_VERSION && key !== FILE_CACHE)
            .map((key) => caches.delete(key)),
        ),
      ),
  );
  self.clients.claim();
});

// ===== Messages from the page =====
self.addEventListener("message", (event) => {
  const data = event.data || {};
  if (data.type === "prefetch" && Array.isArray(data.urls)) {
    event.waitUntil(warmCache(data.urls));
  } else if (data.type === "purge") {
    event.waitUntil(purgeUserPages());
  }
});

// ===== Fetch routing =====
self.addEventListener("fetch", (event) => {
  const { request } = event;
  const url = new URL(request.url);

  if (request.method !== "GET" || url.origin !== self.location.origin) {
    return;
  }

  const path = url.pathname.toLowerCase();
  if (
    SKIP_PATHS.some((p) => path.includes(p)) ||
    url.searchParams.has("download")
  ) {
    return;
  }

  if (path.includes("/apply/file/")) {
    event.respondWith(fileHandler(request));
  } else if (request.mode === "navigate") {
    event.respondWith(navigationHandler(request));
  } else if (path.includes("/assets/")) {
    event.respondWith(staticAssetHandler(request));
  } else {
    event.respondWith(networkFirst(request));
  }
});

// ===== Cache eligibility =====
// PHP sessions send "no-store" on every page, so that header is ignored here.
// Per-user entries are purged on logout and when the signed-in user changes.
function isCacheable(response) {
  return response.ok && response.status === 200 && !response.redirected;
}

// ===== Navigation handler =====
async function navigationHandler(request) {
  const cache = await caches.open(CACHE_VERSION);
  try {
    const response = await fetch(request);
    if (isCacheable(response)) cache.put(request, response.clone());
    else if (
      response.type === "opaqueredirect" &&
      request.url === self.registration.scope
    ) {
      cacheRedirect(cache, request);
    }
    return response;
  } catch (err) {
    return (
      (await cache.match(request, { ignoreVary: true })) ||
      (await cache.match(request, { ignoreSearch: true, ignoreVary: true })) ||
      (await cache.match("offline.html")) ||
      new Response(
        "<h1>You are offline</h1><p>This page is not saved on this device.</p>",
        {
          status: 503,
          headers: { "Content-Type": "text/html" },
        },
      )
    );
  }
}

// ===== App home redirect (/portal/ to the dashboard) =====
// A followed redirect cannot be reused for a navigation, so the redirect itself is saved.
function cacheRedirect(cache, request) {
  fetch(request.url, { credentials: "same-origin" })
    .then((res) => {
      res.body?.cancel();
      if (new URL(res.url).origin === self.location.origin) {
        cache.put(request, Response.redirect(res.url, 302));
      }
    })
    .catch(() => {});
}

// ===== Static assets handler =====
async function staticAssetHandler(request) {
  const cache = await caches.open(CACHE_VERSION);
  const cached = await cache.match(request);

  const refresh = fetch(request)
    .then((response) => {
      if (response.ok) cache.put(request, response.clone());
      return response;
    })
    .catch(() => null);

  return (
    cached ||
    (await refresh) ||
    (await cache.match(request, { ignoreSearch: true })) ||
    Response.error()
  );
}

// ===== Network-first handler =====
async function networkFirst(request) {
  const cache = await caches.open(CACHE_VERSION);
  try {
    const response = await fetch(request);
    if (isCacheable(response)) cache.put(request, response.clone());
    return response;
  } catch (err) {
    const cached =
      (await cache.match(request)) ||
      (await cache.match(request, { ignoreSearch: true }));
    if (cached) return cached;
    throw err;
  }
}

// ===== Protocol files (PDFs, clearances) =====
async function fileHandler(request) {
  const cache = await caches.open(FILE_CACHE);
  const key = new Request(request.url);
  try {
    const response = await fetch(request);
    if (isCacheable(response)) {
      await cache.put(key, response.clone());
      trimFileCache(cache);
    }
    return response;
  } catch (err) {
    const cached = await cache.match(key);
    if (cached) return cached;
    throw err;
  }
}

// Keeps the most recent files only (cache keys are in insertion order)
async function trimFileCache(cache) {
  const keys = await cache.keys();
  const extra = keys.length - MAX_CACHED_FILES;
  for (let i = 0; i < extra; i++) await cache.delete(keys[i]);
}

// ===== Purge per-user entries =====
async function purgeUserPages() {
  await caches.delete(FILE_CACHE);
  const cache = await caches.open(CACHE_VERSION);
  const requests = await cache.keys();
  await Promise.all(
    requests
      .filter((req) => {
        const path = new URL(req.url).pathname;
        return !path.includes("/assets/") && !/offline\.html$/.test(path);
      })
      .map((req) => cache.delete(req)),
  );
}

// ===== Warm the cache for offline use =====
// Pages are fetched, then the assets and protocol files they reference.
async function warmCache(urls) {
  const pages = await caches.open(CACHE_VERSION);
  const files = await caches.open(FILE_CACHE);
  const seen = new Set();
  const assetQueue = [];
  let savedPages = 0;

  const fetchInto = async (cache, href, accept) => {
    if (seen.has(href)) return null;
    seen.add(href);
    try {
      const res = await fetch(href, {
        credentials: "same-origin",
        headers: accept ? { Accept: accept } : {},
      });
      if (!isCacheable(res)) return null;
      await cache.put(new Request(href), res.clone());
      if (cache === pages && accept) savedPages++;
      return res;
    } catch (err) {
      return null;
    }
  };

  for (const raw of urls) {
    const url = new URL(raw, self.location.origin);
    if (url.origin !== self.location.origin) continue;

    const res = await fetchInto(pages, url.href, "text/html");
    if (!res) continue;

    const html = (await res.text()).replace(/\\\//g, "/");

    for (const m of html.matchAll(/["'(]([^"'()\s]*\/assets\/[^"'()\s]+)/g)) {
      const asset = new URL(m[1], url);
      if (asset.origin === self.location.origin) assetQueue.push(asset.href);
    }
    for (const m of html.matchAll(/[^"'\s]*\/apply\/file\/\d+/g)) {
      await fetchInto(files, new URL(m[0], url).href, null);
    }
    const version = html.match(/const VERSION_ID = (\d+)/);
    const annot = html.match(/const ANNOT_API = "([^"]+)"/);
    if (version && annot) {
      await fetchInto(
        pages,
        new URL(annot[1] + "?version_id=" + version[1], url).href,
        null,
      );
    }
  }

  // Assets, plus fonts and images referenced from stylesheets
  for (let i = 0; i < assetQueue.length; i++) {
    const href = assetQueue[i];
    const res = await fetchInto(pages, href, null);
    if (res && /\.css(\?|$)/.test(href)) {
      const css = await res.text();
      for (const m of css.matchAll(/url\(\s*["']?([^"')]+)["']?\s*\)/g)) {
        if (!m[1].startsWith("data:"))
          assetQueue.push(new URL(m[1], href).href);
      }
    }
  }

  if (savedPages === 0) return;
  const windows = await self.clients.matchAll({ type: "window" });
  windows.forEach((client) =>
    client.postMessage({ type: "warmed", at: Date.now() }),
  );
}
