const CACHE_VERSION = "bsu-iacuc-v5";

const PRECACHE_URLS = [
  "offline.html",
  "assets/images/bsu.webp",
  "assets/images/ccard.webp",
  "assets/images/favicon.ico",
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => cache.addAll(PRECACHE_URLS)),
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key !== CACHE_VERSION)
            .map((key) => caches.delete(key)),
        ),
      ),
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  const { request } = event;
  const url = new URL(request.url);

  if (request.method !== "GET" || url.origin !== self.location.origin) {
    return;
  }

  if (url.pathname.includes("/apply/file/")) {
    event.respondWith(networkFirst(request));
    return;
  }

  if (request.mode === "navigate") {
    event.respondWith(navigationHandler(request));
  } else if (url.pathname.includes("/assets/")) {
    event.respondWith(staticAssetHandler(request));
  } else {
    event.respondWith(networkFirst(request));
  }
});

async function navigationHandler(request) {
  const cache = await caches.open(CACHE_VERSION);
  try {
    const response = await fetch(request);
    cache.put(request, response.clone());
    return response;
  } catch (err) {
    return (await cache.match(request)) || cache.match("offline.html");
  }
}

async function staticAssetHandler(request) {
  const cache = await caches.open(CACHE_VERSION);
  const cached = await cache.match(request);

  const refresh = fetch(request)
    .then((response) => {
      cache.put(request, response.clone());
      return response;
    })
    .catch(() => null);

  return cached || (await refresh) || Response.error();
}

async function networkFirst(request) {
  const cache = await caches.open(CACHE_VERSION);
  try {
    const response = await fetch(request);
    cache.put(request, response.clone());
    return response;
  } catch (err) {
    const cached = await cache.match(request);
    if (cached) return cached;
    throw err;
  }
}
