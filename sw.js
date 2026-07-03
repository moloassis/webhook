const CACHE_NAME = 'alertas-ai-cache-v1';
const ASSETS_TO_CACHE = [
  'index.html',
  'manifest.json',
  'assets/css/index.css',
  'assets/js/index.js',
  'assets/img/icon_192.png',
  'assets/img/icon_512.png',
  'assets/audio/notificacao.mp3'
];

// Instalação do Service Worker e caching de recursos estáticos
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Service Worker: Caching recursos estáticos...');
        return cache.addAll(ASSETS_TO_CACHE).catch(err => {
          console.warn('Alguns assets opcionais não puderam ser cacheados:', err);
        });
      })
      .then(() => self.skipWaiting())
  );
});

// Ativação do Service Worker e limpeza de caches antigos
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cache => {
          if (cache !== CACHE_NAME) {
            console.log('Service Worker: Limpando cache antigo:', cache);
            return caches.delete(cache);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Interceptação de requisições
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // BYPASS: Ignora completamente requisições para scripts PHP e fluxos dinâmicos
  if (url.pathname.endsWith('.php') || url.search.includes('last_id') || event.request.method !== 'GET') {
    return; // Passa direto para a rede
  }

  // Estratégia Stale-While-Revalidate para recursos estáticos (HTML, ícones, som)
  event.respondWith(
    caches.match(event.request)
      .then(cachedResponse => {
        if (cachedResponse) {
          // Dispara busca na rede em background para atualizar o cache
          fetch(event.request).then(networkResponse => {
            if (networkResponse.status === 200) {
              caches.open(CACHE_NAME).then(cache => cache.put(event.request, networkResponse));
            }
          }).catch(() => { /* ignora erro de rede off-line */ });
          
          return cachedResponse;
        }

        // Se não estiver no cache, busca na rede
        return fetch(event.request);
      })
  );
});
