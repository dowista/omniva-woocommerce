(function() {
  'use strict';

  function initSwitcher(switcher) {
    var trigger = switcher.querySelector('[data-omnivalt-page-switcher-trigger]');
    var menu = switcher.querySelector('[data-omnivalt-page-switcher-menu]');

    if (!trigger || !menu) {
      return;
    }

    var links = menu.querySelectorAll('a[href]');
    var supportsHover = window.matchMedia && window.matchMedia('(hover: hover)').matches;

    function setOpen(isOpen, restoreFocus) {
      menu.hidden = !isOpen;
      trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      switcher.classList.toggle('is-open', isOpen);

      if (restoreFocus) {
        trigger.focus();
      }
    }

    function focusFirstLink() {
      if (links.length) {
        links[0].focus();
      }
    }

    function focusLastLink() {
      if (links.length) {
        links[links.length - 1].focus();
      }
    }

    if (supportsHover) {
      switcher.addEventListener('mouseenter', function() {
        setOpen(true);
      });

      switcher.addEventListener('mouseleave', function() {
        setOpen(false);
      });
    }

    trigger.addEventListener('click', function(event) {
      if (supportsHover && event.detail !== 0) {
        return;
      }

      setOpen(menu.hidden);
    });

    trigger.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        if (!menu.hidden) {
          event.preventDefault();
          setOpen(false, true);
        }
        return;
      }

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        setOpen(true);
        focusFirstLink();
      }

      if (event.key === 'ArrowUp') {
        event.preventDefault();
        setOpen(true);
        focusLastLink();
      }
    });

    menu.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        setOpen(false, true);
      }
    });

    document.addEventListener('click', function(event) {
      if (!switcher.contains(event.target)) {
        setOpen(false);
      }
    });

    document.addEventListener('focusin', function(event) {
      if (!switcher.contains(event.target)) {
        setOpen(false);
      }
    });
  }

  function init() {
    var switchers = document.querySelectorAll('[data-omnivalt-page-switcher]');

    for (var index = 0; index < switchers.length; index++) {
      initSwitcher(switchers[index]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
