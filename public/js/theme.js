(function () {
  'use strict';

  var KEY = 'usagi-theme';
  var root = document.documentElement;
  var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function stored() {
    try {
      var value = localStorage.getItem(KEY);
      return value === 'dark' || value === 'light' ? value : null;
    } catch (e) {
      return null;
    }
  }

  function preferred() {
    return stored() || (media && media.matches ? 'dark' : 'light');
  }

  function apply(theme) {
    root.setAttribute('data-bs-theme', theme);
    var buttons = document.querySelectorAll('[data-theme-toggle]');
    for (var i = 0; i < buttons.length; i++) {
      buttons[i].setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
      buttons[i].title = theme === 'dark' ? 'ライトモードに切り替え' : 'ダークモードに切り替え';
    }
  }

  apply(preferred());

  document.addEventListener('DOMContentLoaded', function () { apply(preferred()); });

  document.addEventListener('click', function (e) {
    var button = e.target.closest ? e.target.closest('[data-theme-toggle]') : null;
    if (!button) return;
    var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem(KEY, next); } catch (err) { /* storage unavailable */ }
    apply(next);
  });

  window.addEventListener('storage', function (e) {
    if (e.key === KEY) apply(preferred());
  });

  if (media) {
    var onChange = function () { if (!stored()) apply(preferred()); };
    if (media.addEventListener) media.addEventListener('change', onChange);
    else if (media.addListener) media.addListener(onChange);
  }
})();
