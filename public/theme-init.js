/* Apply the saved palette before the first paint. */
(function () {
    var light = false;
    try { light = localStorage.getItem('nfcheck.theme') === 'light'; } catch (_) {}
    document.documentElement.classList.toggle('light', light);
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', light ? '#F4F2EC' : '#0B0D0E');
})();
