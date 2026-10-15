if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    const base = document.querySelector('meta[name="api-base"]')?.content || '/';
    navigator.serviceWorker.register(base + 'sw.js').catch(() => {});
  });
}
