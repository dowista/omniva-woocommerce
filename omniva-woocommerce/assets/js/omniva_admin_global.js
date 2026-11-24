(function() {
  function initializeWhatsNew() {
    var config = window.omnivaltWhatsNew;
    if (!config || !window.HTMLDialogElement || typeof HTMLDialogElement.prototype.showModal !== 'function') {
      return;
    }

    var trigger = document.querySelector('[data-settings-news-open]');
    var placeholder = document.getElementById('omnivalt-settings-news-placeholder');
    var dialog = null;
    var request = null;

    function saveDismissal(nonce) {
      if (!nonce) {
        return;
      }

      var body = new URLSearchParams({ action: 'omnivalt_dismiss_whats_new', nonce: nonce });
      fetch(config.url, { method: 'POST', body: body, keepalive: true }).catch(function() {
        // Without a saved dismissal, the server offers the update on the next visit.
      });
    }

    function restoreTriggerFocus() {
      if (trigger.getClientRects().length && !trigger.closest('[aria-hidden="true"]')) {
        trigger.focus();
        return;
      }

      var sidebarToggle = document.querySelector('[data-settings-sidebar-toggle]');
      if (sidebarToggle) {
        sidebarToggle.focus();
      }
    }

    function loadChangelog() {
      if (!dialog || !dialog.open || request) {
        return;
      }

      var updates = dialog.querySelector('.omnivalt-settings-news__updates');
      var changelog = dialog.querySelector('[data-settings-news-changelog]');
      var content = dialog.querySelector('[data-settings-news-changelog-content]');
      var status = dialog.querySelector('[data-settings-news-changelog-status]');
      var back = dialog.querySelector('[data-settings-news-changelog-back]');
      if (!updates || !changelog || !content) {
        return;
      }

      updates.hidden = true;
      changelog.hidden = false;
      if (back) {
        back.focus();
      }
      if (changelog.dataset.loaded === '1') {
        return;
      }

      if (status) {
        status.textContent = config.changelogLoading;
      }
      changelog.setAttribute('aria-busy', 'true');

      var controller = new AbortController();
      request = controller;

      var url = new URL(config.url);
      url.searchParams.set('action', 'omnivalt_load_whats_new');
      url.searchParams.set('nonce', config.loadNonce);
      url.searchParams.set('mode', 'changelog');

      fetch(url, { cache: 'no-store', signal: controller.signal })
        .then(function(response) {
          return response.ok ? response.json() : null;
        })
        .then(function(result) {
          if (request !== controller || !dialog || !dialog.open) {
            return;
          }
          if (!result || !result.success || !result.data || typeof result.data.html !== 'string') {
            throw new Error('Could not load changelog');
          }

          content.innerHTML = result.data.html;
          changelog.dataset.loaded = '1';
          changelog.removeAttribute('aria-busy');
          var title = dialog.querySelector('#omnivalt-settings-news-changelog-title');
          if (title) {
            title.focus();
          }
        })
        .catch(function() {
          if (request === controller && dialog && dialog.open) {
            if (status) {
              status.textContent = config.changelogError;
            }
            changelog.removeAttribute('aria-busy');
          }
        })
        .then(function() {
          if (request === controller) {
            request = null;
          }
        });
    }

    function showUpdates() {
      if (!dialog) {
        return;
      }

      if (request) {
        request.abort();
        request = null;
      }

      var updates = dialog.querySelector('.omnivalt-settings-news__updates');
      var changelog = dialog.querySelector('[data-settings-news-changelog]');
      var openButton = dialog.querySelector('[data-settings-news-changelog-open]');
      if (changelog) {
        changelog.hidden = true;
        changelog.removeAttribute('aria-busy');
      }
      if (updates) {
        updates.hidden = false;
      }
      if (openButton) {
        openButton.focus();
      }
    }

    function showDialog(nextDialog, manual) {
      nextDialog.addEventListener('click', function(event) {
        var target = event.target;
        if (!target || typeof target.closest !== 'function') {
          return;
        }

        if (target.closest('[data-settings-news-changelog-open]')) {
          loadChangelog();
        } else if (target.closest('[data-settings-news-changelog-back]')) {
          showUpdates();
        }
      });

      nextDialog.addEventListener('close', function() {
        if (request) {
          request.abort();
          request = null;
        }
        document.body.classList.remove('omnivalt-settings-news-open');
        nextDialog.remove();
        dialog = null;
        saveDismissal(nextDialog.dataset.dismissNonce);
        if (manual && trigger) {
          restoreTriggerFocus();
        }
      }, { once: true });

      document.body.appendChild(nextDialog);
      try {
        nextDialog.showModal();
      } catch (error) {
        nextDialog.remove();
        throw error;
      }

      dialog = nextDialog;
      document.body.classList.add('omnivalt-settings-news-open');
    }

    function showContent(data, manual) {
      var template = document.createElement('template');
      template.innerHTML = data.html;
      var content = template.content.firstElementChild;
      if (!content || content.id !== 'omnivalt-settings-news' || typeof content.showModal !== 'function') {
        throw new Error('Invalid news dialog');
      }

      if (!manual) {
        content.dataset.dismissNonce = data.dismissNonce || '';
        showDialog(content, false);
        return;
      }

      dialog.replaceChildren(...content.childNodes);
      dialog.setAttribute('aria-labelledby', content.getAttribute('aria-labelledby'));
      dialog.dataset.dismissNonce = data.dismissNonce || '';
      var close = dialog.querySelector('[autofocus]');
      if (close) {
        close.focus();
      }
    }

    function open(manual) {
      if (dialog || (!manual && request)) {
        return;
      }

      if (manual) {
        if (request) {
          request.abort();
        }
        showDialog(placeholder.content.firstElementChild.cloneNode(true), true);
      }

      var controller = new AbortController();
      request = controller;

      var url = new URL(config.url);
      url.searchParams.set('action', 'omnivalt_load_whats_new');
      url.searchParams.set('nonce', config.loadNonce);
      url.searchParams.set('mode', manual ? 'manual' : 'auto');

      fetch(url, { cache: 'no-store', signal: controller.signal })
        .then(function(response) {
          return response.ok ? response.json() : null;
        })
        .then(function(result) {
          if (request !== controller || (manual && (!dialog || !dialog.open))) {
            return;
          }
          if (!result || !result.success || !result.data || typeof result.data.html !== 'string') {
            throw new Error('Could not load news');
          }
          if (result.data.html || manual) {
            showContent(result.data, manual);
          }
        })
        .catch(function() {
          if (manual && request === controller && dialog && dialog.open) {
            var status = dialog.querySelector('[role="status"]');
            if (status) {
              status.textContent = config.loadError;
            }
          }
        })
        .then(function() {
          if (request === controller) {
            request = null;
          }
        });
    }

    if (trigger && placeholder) {
      trigger.addEventListener('click', function() {
        open(true);
      });
      trigger.hidden = false;
    }

    if (config.autoOpen === '1') {
      if (document.readyState === 'complete') {
        open(false);
      } else {
        window.addEventListener('load', function() {
          open(false);
        }, { once: true });
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeWhatsNew, { once: true });
  } else {
    initializeWhatsNew();
  }
})();
