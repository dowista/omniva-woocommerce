/* global omniva_eraseCookie, omniva_getCookie, omniva_setCookie, omnivaglobals, omnivatext */
(function($) {
  $(document).on('click', '#filter-form .tablenav-pages a', function(event) {
    event.preventDefault();
    var form = this.closest('form');
    form.action = this.href;
    form.submit();
  });

  $(function() {
    var range = $('#filter_manifest_date');
    if (!range.length) {
      return;
    }
    var placeholder = range.val();
    var panel = $('#manifest-date-range');
    var start = $('#filter_start_date');
    var end = $('#filter_end_date');
    var dates = start.add(end);
    var field = range.closest('.omnivalt-manifest-page__filter-field');
    var card = field.closest('.omnivalt-manifest-page__orders-card');
    var pickerDefaults;
    if (typeof $.fn.datetimepicker === 'function') {
      pickerDefaults = $.fn.datetimepicker.defaults;
      dates.datetimepicker({pickTime: false, useCurrent: false});
    }

    function setRangeOpen(open) {
      if (!open && pickerDefaults) {
        dates.each(function() {
          $(this).data('DateTimePicker').hide();
        });
      }
      range.attr('aria-expanded', String(open));
      card.toggleClass('is-date-range-open', open);
      panel.prop('hidden', !open);
      panel.toggleClass('opens-up', open &&
        range[0].getBoundingClientRect().bottom + panel.outerHeight() + 8 > window.innerHeight);
    }

    function updateRange() {
      var from = start.val();
      var to = end.val();
      if (pickerDefaults) {
        var fromDate = window.moment(from, 'YYYY-MM-DD', true);
        var toDate = window.moment(to, 'YYYY-MM-DD', true);
        start.data('DateTimePicker').setMaxDate(toDate.isValid() ? toDate : pickerDefaults.maxDate);
        end.data('DateTimePicker').setMinDate(fromDate.isValid() ? fromDate : pickerDefaults.minDate);
      }
      range.val(from || to ? [from, to].join(' – ').trim() : placeholder);
    }

    updateRange();
    panel.prop('hidden', true);
    range.prop('hidden', false).on('click', function() {
      setRangeOpen(panel.prop('hidden'));
    });
    dates.on('change', updateRange);
    field.on('keydown', function(event) {
      if (event.key === 'Escape' && !panel.prop('hidden')) {
        event.stopPropagation();
        setRangeOpen(false);
        range.trigger('focus');
      }
    });
    $(document).on('click focusin', function(event) {
      if (!panel.prop('hidden') && !field[0].contains(event.target) &&
        !$(event.target).closest('.bootstrap-datetimepicker-widget').length) {
        setRangeOpen(false);
      }
    });
  });

  /* Mobile order details */
  $(document).on('click', '.omnivalt-manifest-page__mobile-details, .omnivalt-manifest-page__orders-card .customer-name', function() {
    var button = $(this);

    if (!button.hasClass('omnivalt-manifest-page__mobile-details')) {
      button = button.closest('.column-order_customer').find('.omnivalt-manifest-page__mobile-details').first();
    }

    omniva_toggle_mobile_details(button);
  });

  function omniva_toggle_mobile_details(button) {
    var row = button.closest('tr.data-row');
    var expanded = button.attr('aria-expanded') === 'true';
    var label = expanded ? button.attr('data-show-label') : button.attr('data-hide-label');

    row.toggleClass('is-mobile-expanded', !expanded);
    button.attr('aria-expanded', expanded ? 'false' : 'true');
    button.find('span:first').text(label);
  }

  $(document).on('change', '#omnivalt-manifest-page__tabs-select', function() {
    var target = $(this).val();

    if (target) {
      window.location.href = target;
    }
  });

  $(document).on('click', '#omnivalt-manifest-page__filter-toggle', function() {
    omniva_set_filter_drawer_open(true);
  });

  $(document).on('click', '#omnivalt-manifest-page__filter-close, .omnivalt-manifest-page__filter-backdrop', function() {
    omniva_set_filter_drawer_open(false);
  });

  $(document).on('keydown', function(event) {
    if (event.key === 'Escape') {
      omniva_set_filter_drawer_open(false);
    }
  });

  /* Checkbox events */
  $(document).on('click', '.check-all', function() {
    var checked = $(this).prop('checked');
    $(this).parents('table').find('.manifest-item').each(function() {
      $(this).prop('checked', checked);
      omniva_update_checked_list(this);
    });
  });
  $(document).on('change','input.manifest-item', function() {
    $("#call_quantity").val($('input.manifest-item:checkbox:checked').length);
    $("#call_quantity").trigger("change");
    omniva_update_checked_list(this);
  });
  $(document).on('change','input.check-all', function() {
    $("#call_quantity").val($('input.manifest-item:checkbox:checked').length);
    $("#call_quantity").trigger("change");
  });

  /* Selected list */
  $(document).on('click', '#selected-orders .item', function() {
    var value = $(this).attr("data-id");
    if (value) {
      var checkbox = $('input.manifest-item[value="' + value + '"]');
      if (checkbox.length) {
        $(checkbox).prop('checked', false);
        $(checkbox).trigger('change');
      } else {
        omniva_remove_from_checked_list(value);
      }
      omniva_remove_selected_item(this);
    }
  });

  /* Call courier */
  $(document).on('click', '#omniva-courier-modal', function(e) {
    if (e.target === this) {
      $('#omniva-courier-modal').removeClass('open');
    }
  });

  $(document).on('click', '#omniva-call-btn', function(e) {
    e.preventDefault();
    $('#omniva-courier-modal .modal-content').hide();
    $('#modal-content-call').show();
    $('#omniva-courier-modal').addClass('open').scrollTop(0);
    $("#call_quantity").trigger("change");
  });

  $(document).on('click', '#omniva-call-cancel-btn', function(e) {
    e.preventDefault();
    $('#omniva-courier-modal').removeClass('open');
  });

  $(document).on('change', '#call_quantity', function(e){
    var min=parseFloat($(this).attr('min'));
    var max=parseFloat($(this).attr('max'));
    var curr=parseFloat($(this).val());
    if (curr > max) { $(this).val(max); }
    if (curr < min) { $(this).val(min); }

    if (curr <= 0) {
      $('#omniva-call-confirm-btn').prop('disabled', true);
    } else {
      $('#omniva-call-confirm-btn').prop('disabled', false);
    }
  });

  /* Cancel courier */
  $(document).on('click', '.current_calls .action-cancel', function(e) {
    e.preventDefault();
    $('#omniva-courier-modal .modal-content').hide();
    $('#modal-content-cancel').show();
    var call_id = $(this).siblings('input[name="call_id"]')[0].value;
    $('#omniva-cancel-id').val(call_id);
    $('#omniva-courier-modal').addClass('open').scrollTop(0);
  });

  /* Remove courier arrival time */
  $(document).on('click', '.current_calls .action-remove', function(e) {
    e.preventDefault();
    var call_id = $(this).siblings('input[name="call_id"]')[0].value;
    $.ajax({
      type: "post",
      dataType: "json",
      url: omnivaglobals.ajax_url,
      data: {
        action: 'remove_courier_call',
        call_id: call_id,
        nonce: omnivaglobals.remove_courier_call_nonce
      },
      success: function(response) {
        //console.log(response);
        if ( response.status == "error" ) {
          console.log("Error", response.msg);
        }
        if ( response.status == "OK" ) {
          var all_calls = $('.current_calls input[name="call_id"]');
          for ( var i = 0; i < all_calls.length; i++ ) {
            if ( all_calls[i].value == call_id ) {
              var row = $(all_calls[i]).closest("tr");
              $(row).css("color", "#ccc");
              setTimeout(function() {
                $(row).remove();
              }, 1000);
            }
          }
        }
      },
      error: function (jqXHR, exception) {
        console.log("Critical error", jqXHR);
      }
    });
  });

  /* Submit buttons */
  $(document).on('click', '#submit_manifest_labels_1, #submit_manifest_labels_2', function() {
    omniva_submit_bulk_action('#labels-print-form');
  });

  $(document).on('click', '#submit_manifest_items_1, #submit_manifest_items_2', function() {
    omniva_submit_bulk_action('#manifest-print-form');
  });

  // Row label forms open the PDF in a new tab, so this page cannot learn the result of the registration.
  $(document).on('submit', 'form[id^="omnivalt-label-"], form[id^="omnivalt-regenerate-label-"]', function() {
    omniva_mark_orders_as_labeled([$(this).find('input[name="post"]').val()]);
  });

  /* Row action tooltips */
  $(document).on('mouseenter focusin', '.omnivalt-manifest-page__row-action, .omnivalt-manifest-page__tooltip-trigger, .omnivalt-tracking-status, #omnivalt-tracking-sync[aria-disabled="true"]', function() {
    omniva_show_action_tooltip(this);
  });

  $(document).on('mouseleave focusout', '.omnivalt-manifest-page__row-action, .omnivalt-manifest-page__tooltip-trigger, .omnivalt-tracking-status, #omnivalt-tracking-sync', function() {
    omniva_remove_action_tooltip();
  });

  $(function() {
    omniva_update_selected_summary();
  });

  function mark_manifest_ready() {
    $('#omnivalt-manifest-root').addClass('is-ready');
  }

  if (document.readyState === 'complete') {
    mark_manifest_ready();
  } else {
    $(window).one('load', mark_manifest_ready);
  }

  /* Functions */
  function omniva_set_filter_drawer_open(open) {
    var filters = $('#omnivalt-manifest-page__filters');
    var backdrop = $('.omnivalt-manifest-page__filter-backdrop');
    var toggle = $('#omnivalt-manifest-page__filter-toggle');

    if (!filters.length) {
      return;
    }

    filters.toggleClass('is-open', open).attr('aria-hidden', open ? 'false' : 'true');
    backdrop.toggleClass('is-visible', open);
    toggle.attr('aria-expanded', open ? 'true' : 'false');
    $('body').toggleClass('omnivalt-manifest-page__filter-open', open);

    if (!open) {
      toggle.trigger('focus');
    }
  }

  function omniva_update_checked_list(checkbox) {
    var value = $(checkbox).val();
    var cookie_value = [];

    if ($(checkbox).is(':checked')) {
      omniva_add_to_checked_list(value);
    } else {
      omniva_remove_from_checked_list(value);
    }
  }

  function omniva_add_to_checked_list(value) {
    var cookie_value = [];
    if (omniva_getCookie(omnivaglobals.cookie_checked_list) == null) {
      $('#selected-orders').show();
      cookie_value = [value];
      omniva_add_selected_item(value);
    } else {
      var current_cookie = omniva_getCookie(omnivaglobals.cookie_checked_list);
      cookie_value = JSON.parse(current_cookie);
      if (!cookie_value.includes(value)) {
        cookie_value.push(value);
        omniva_add_selected_item(value);
      }
    }
    omniva_set_selected_actions_visible(true);
    omniva_setCookie(omnivaglobals.cookie_checked_list, JSON.stringify(cookie_value), 12*60);
  }

  function omniva_remove_from_checked_list(value) {
    var cookie_value = [];
    var current_cookie = omniva_getCookie(omnivaglobals.cookie_checked_list);
    if (current_cookie != null) {
      cookie_value = JSON.parse(current_cookie);
    }
    if ( ! Array.isArray(cookie_value) ) {
      cookie_value = [];
    }

    cookie_value = cookie_value.filter(function(order_id) {
      return String(order_id) !== String(value);
    });
    if (cookie_value.length == 0) {
      omniva_eraseCookie(omnivaglobals.cookie_checked_list);
    } else {
      omniva_setCookie(omnivaglobals.cookie_checked_list, JSON.stringify(cookie_value), 12*60);
    }

    var selected_item = $('#selected-orders .item').filter(function() {
      return String($(this).attr('data-id')) === String(value);
    });
    selected_item.remove();

    var has_selected_orders = $('#selected-orders .item').length > 0;
    $('#selected-orders').toggle(has_selected_orders);
    omniva_set_selected_actions_visible(has_selected_orders);
  }

  function omniva_add_selected_item(value) {
    var checkbox = $('.manifest-item').filter(function() {
      return String($(this).val()) === String(value);
    }).first();
    var has_barcodes = checkbox.attr('data-has-barcodes') === '1' ? '1' : '0';
    var element = $('<span class="item"></span>');

    element.attr('data-id', value);
    element.attr('data-has-barcodes', has_barcodes);
    element.text('#' + value).append('<span class="dashicons dashicons-no"></span>');
    element.appendTo('#selected-orders');
  }

  function omniva_remove_selected_item(element) {
    $(element).remove();
    omniva_update_selected_summary();
  }

  function omniva_update_selected_summary() {
    var selected_orders = $('#selected-orders');
    var selected_count = selected_orders.find('.item').length;
    var has_many_selected = selected_count > 3;

    selected_orders.find('.selected-count').text(selected_count);
    selected_orders.closest('.omnivalt-manifest-page__bulk-actions').toggleClass('has-many-selected', has_many_selected);
    omniva_update_manifest_action_state();
    omniva_update_label_action_state();
  }

  function omniva_set_selected_actions_visible(visible) {
    omniva_update_selected_summary();
    $('.omnivalt-manifest-page__bulk-actions, .omnivalt-manifest-page__selection-actions, .omnivalt-manifest-page__bottom-actions').toggleClass('is-visible', visible);
  }

  function omniva_update_label_action_state() {
    var bulk_actions = $('.omnivalt-manifest-page__bulk-actions').first();
    var selected_orders = $('#selected-orders .item');
    var sender_info_complete = bulk_actions.attr('data-sender-info-complete') === '1';
    var has_existing_labels = selected_orders.filter('[data-has-barcodes="1"]').length > 0;
    var disabled = !sender_info_complete && !has_existing_labels;
    var buttons = $('#submit_manifest_labels_1, #submit_manifest_labels_2');

    buttons.prop('disabled', disabled);
    if ( disabled ) {
      buttons.attr('aria-disabled', 'true');
    } else {
      buttons.removeAttr('aria-disabled');
    }

    $('.omnivalt-manifest-page__tooltip-trigger').each(function() {
      var trigger = $(this);
      var tooltip = disabled ? trigger.attr('data-sender-tooltip') : '';
      var button_title = trigger.find('button').first().attr('title') || '';

      trigger.attr('data-tooltip', tooltip || '');
      trigger.attr('aria-label', disabled ? (tooltip || button_title) : button_title);
    });
  }

  function omniva_update_manifest_action_state() {
    var has_selected_barcodes = $('#selected-orders .item').filter(function() {
      var selected_item = $(this);
      var order_id = String(selected_item.attr('data-id'));
      var checkbox = $('.manifest-item').filter(function() {
        return String($(this).val()) === order_id;
      }).first();

      if (!checkbox.length) {
        return selected_item.attr('data-has-barcodes') === '1';
      }

      var has_barcodes = checkbox.attr('data-has-barcodes') === '1';
      selected_item.attr('data-has-barcodes', has_barcodes ? '1' : '0');
      return checkbox.is(':checked') && has_barcodes;
    }).length > 0;
    var buttons = $('#submit_manifest_items_1, #submit_manifest_items_2');

    buttons.prop('disabled', !has_selected_barcodes);
    if ( !has_selected_barcodes ) {
      buttons.attr('aria-disabled', 'true');
    } else {
      buttons.removeAttr('aria-disabled');
    }
  }

  // Optimistic: the server stays authoritative and reports orders without barcodes when a manifest is requested.
  function omniva_mark_orders_as_labeled(order_ids) {
    var labeled_ids = $.map(order_ids, function(order_id) {
      return String(order_id);
    });

    $('.manifest-item').filter(function() {
      return $.inArray(String($(this).val()), labeled_ids) !== -1;
    }).attr('data-has-barcodes', '1');
  }

  function omniva_submit_bulk_action(form_selector) {
    var ids = [];
    $(form_selector + ' .post_id').remove();
    if (omniva_getCookie(omnivaglobals.cookie_checked_list) != null) {
      var current_cookie = omniva_getCookie(omnivaglobals.cookie_checked_list);
      ids = JSON.parse(current_cookie);
    }
    $('.manifest-item:checked').each(function() {
      var id = $(this).val();
      if (!ids.includes(id)) {
        ids.push(id);
      }
    });
    for (var i=0; i<ids.length; i++) {
      $(form_selector).append('<input type="hidden" class = "post_id" name="post[]" value = "' + ids[i] + '" />');
    }
    if (!ids.length) {
      alert(omnivatext.alert_select_orders);
    } else {
      // PDF viewers can reload the response URL with GET and cannot replay its POST body.
      var form = $(form_selector)[0];
      var print_url = new URL(form.getAttribute('action'), window.location.href);
      var print_params = new URLSearchParams(new FormData(form));
      print_params.delete('_wp_http_referer');
      print_params.set('process', 'print');
      print_url.search = print_params.toString();
      form.setAttribute('action', print_url.toString());

      if (form_selector === '#labels-print-form') {
        omniva_mark_orders_as_labeled(ids);
      }

      omniva_eraseCookie(omnivaglobals.cookie_checked_list);
      $('#selected-orders .item').remove();
      $('#selected-orders').hide();
      omniva_set_selected_actions_visible(false);
      $('.manifest-item').prop('checked', false);
      $('.check-all').prop('checked', false);
      $(form_selector).submit();
    }
  }

  function omniva_show_action_tooltip(element) {
    var text = $(element).attr('data-tooltip');
    var rect;
    var tooltip;
    var isTracking;
    var timeline;
    var content;
    var eventName;
    var eventDate;
    var locationName;
    var warningText;
    var top;
    var left;

    omniva_remove_action_tooltip();

    if (!text || !element.getBoundingClientRect) {
      return;
    }

    tooltip = $('<div class="omnivalt-manifest-page__floating-tooltip" role="tooltip"></div>');
    isTracking = $(element).is('.omnivalt-tracking-status');
    if (isTracking) {
      tooltip.addClass('omnivalt-manifest-page__floating-tooltip--tracking');
      timeline = $('<div class="omnivalt-manifest-page__tracking-tooltip-timeline"></div>').appendTo(tooltip);
      content = $('<div class="omnivalt-manifest-page__tracking-tooltip-content"></div>').appendTo(timeline);
      eventName = $(element).attr('data-tracking-event') || '';
      eventDate = $(element).attr('data-tracking-date') || '';
      locationName = $(element).attr('data-tracking-location') || '';
      warningText = $(element).attr('data-tracking-warnings') || '';

      if (!eventName && !eventDate && !locationName && !warningText) {
        eventName = text;
      }
      if (eventName) {
        $('<div class="omnivalt-manifest-page__tracking-tooltip-event"></div>').text(eventName).appendTo(content);
      }
      if (eventDate) {
        $('<div class="omnivalt-manifest-page__tracking-tooltip-date"></div>').text(eventDate).appendTo(content);
      }
      if (locationName) {
        $('<div class="omnivalt-manifest-page__tracking-tooltip-location"></div>').text(locationName).appendTo(content);
      }
      if (warningText) {
        warningText.split(/\r?\n/).forEach(function (warning) {
          if (warning) {
            $('<div class="omnivalt-manifest-page__tracking-tooltip-warning"></div>').text(warning).appendTo(content);
          }
        });
      }
    } else {
      tooltip.text(text);
    }
    tooltip.appendTo('body');

    rect = element.getBoundingClientRect();
    left = rect.left + (rect.width / 2) - (tooltip.outerWidth() / 2);
    left = Math.max(8, Math.min(left, window.innerWidth - tooltip.outerWidth() - 8));
    top = rect.top - tooltip.outerHeight() - 8;

    if (top < 8) {
      top = rect.bottom + 8;
      if (isTracking) {
        tooltip.addClass('omnivalt-manifest-page__floating-tooltip--below');
      }
    }

    tooltip.css({
      left: left,
      top: top
    });
  }

  function omniva_remove_action_tooltip() {
    $('.omnivalt-manifest-page__floating-tooltip').remove();
  }
})(jQuery);

/* global omnivaltTracking */
(function ($) {
  'use strict';
  var config = omnivaltTracking, opener = null, openerOrder = '', openerBarcode = '', dialog = null, progressTimer = null, historyRequest = null;
  function request(operation, data) {
    return $.ajax({url: config.url, method: 'POST', dataType: 'json', timeout: 30000,
      data: $.extend({action: 'omnivalt_tracking', nonce: config.nonce, operation: operation}, data || {})});
  }
  function refreshRows() {
    var slots = $('.omnivalt-tracking-slot'), rows = [], batchSize = 100;
    slots.each(function () { rows.push({order:$(this).attr('data-order'), barcode:$(this).attr('data-barcode')}); });
    function refreshBatch(offset) {
      var batch = rows.slice(offset, offset + batchSize);
      if (!batch.length) { return; }
      request('rows', {rows:batch}).done(function (response) {
        if (!response.success) { return; }
        response.data.forEach(function (row) {
          $('.omnivalt-tracking-slot').filter(function () { return $(this).attr('data-order') === String(row.order) && $(this).attr('data-barcode') === row.barcode; }).replaceWith(row.html);
        });
        refreshBatch(offset + batch.length);
      });
    }
    refreshBatch(0);
  }
  function trackingListTarget(form) {
    var target = new URL(form.action);
    var perPage = new URLSearchParams(window.location.search).get('perpage');
    if (perPage) {
      target.searchParams.set('perpage', perPage);
    }
    return target.href;
  }
  function hasUnappliedFilters(form) {
    return $(form).find('input[name^="filter_"], select[name^="filter_"]').is(function () {
      var value = this.defaultValue;
      if (this.tagName === 'SELECT') {
        var appliedOption = $(this.options).filter(function () { return this.defaultSelected; })[0];
        value = appliedOption ? appliedOption.value : this.options[0].value;
      }
      return this.value !== value;
    });
  }
  function close() {
    if (historyRequest) {
      var previous = historyRequest;
      historyRequest = null;
      previous.abort();
    }
    if (dialog) { dialog.remove(); dialog = null; }
    $('body').removeClass('omnivalt-tracking-open');
    if (opener && opener.isConnected) { opener.focus(); }
    else if (opener) {
      $('.omnivalt-tracking-slot').filter(function () { return $(this).attr('data-order') === openerOrder && $(this).attr('data-barcode') === openerBarcode; }).find('button').first().trigger('focus');
    }
    opener = null;
    // Restoring focus triggers the badge's tooltip handler synchronously.
    $('.omnivalt-manifest-page__floating-tooltip').remove();
  }
  $(document).on('click', '.omnivalt-tracking-status', function () {
    close(); opener = this;
    var badge = $(this), slot = badge.closest('.omnivalt-tracking-slot');
    openerOrder = slot.attr('data-order'); openerBarcode = slot.attr('data-barcode');
    var status = badge.attr('data-tracking-status');
    var label = badge.attr('data-tracking-label') || '';
    var stages = {processing: 2, returning: 2, parcel_machine: 2, post_office: 2, pickup: 2, delivered: 3, returned: 3};
    var stage = stages[status] || 1;
    dialog = $('<div class="modal open"><section role="dialog" aria-modal="true" aria-labelledby="omnivalt-tracking-title" class="modal-content omnivalt-tracking-dialog">' +
      '<header class="omnivalt-tracking-heading"><h2 id="omnivalt-tracking-title" class="omnivalt-tracking-barcode"></h2><span class="omnivalt-tracking-summary"></span></header>' +
      '<div class="omnivalt-tracking-progress" role="progressbar" aria-valuemin="0" aria-valuemax="3"><span></span><span></span><span></span></div>' +
      '<div class="omnivalt-tracking-destination" hidden><div class="omnivalt-tracking-destination-label"></div><div class="omnivalt-tracking-destination-value"><span class="dashicons dashicons-location-alt" aria-hidden="true"></span><span></span></div></div>' +
      '<h3 class="omnivalt-tracking-history-title"></h3><p class="omnivalt-tracking-message" role="status"></p><ol class="omnivalt-tracking-events" role="list" hidden></ol>' +
      '<div class="modal-footer"><button type="button" class="button omnivalt-manifest-page__button omnivalt-manifest-page__button--secondary"></button></div></section></div>');
    dialog.find('.omnivalt-tracking-dialog').attr('data-status', status);
    dialog.find('.omnivalt-tracking-barcode').text(openerBarcode);
    dialog.find('.omnivalt-tracking-summary').text(label);
    var progress = dialog.find('.omnivalt-tracking-progress');
    progress.attr({'aria-label': config.title, 'aria-valuenow': stage, 'aria-valuetext': label});
    progress.children().slice(0, stage).addClass('is-filled');
    var service = slot.closest('tr').find('.column-order_service .address > p').first().clone();
    service.children('.title').remove();
    service.find('br').replaceWith(function () { return document.createTextNode(' '); });
    var destination = service.text().replace(/\s+/g, ' ').trim();
    if (destination) {
      dialog.find('.omnivalt-tracking-destination-label').text(config.destination);
      dialog.find('.omnivalt-tracking-destination-value > span:last-child').text(destination);
      dialog.find('.omnivalt-tracking-destination').prop('hidden', false);
    }
    dialog.find('.omnivalt-tracking-history-title').text(config.historyTitle);
    dialog.on('click', function (event) { if (dialog && event.target === dialog[0]) { close(); } });
    dialog.find('button').text(config.close).on('click', close);
    var message = dialog.find('.omnivalt-tracking-message').text(config.loading);
    var list = dialog.find('.omnivalt-tracking-events');
    $('body').addClass('omnivalt-tracking-open'); $('#omnivalt-manifest-root').append(dialog); dialog.find('button').trigger('focus');
    var current = request('history', {order: openerOrder, barcode: openerBarcode});
    historyRequest = current;
    current.done(function (response) {
      if (historyRequest !== current) { return; }
      if (!response.success || !response.data || !Array.isArray(response.data.events)) {
        message.text(config.error);
        return;
      }
      var events = response.data.events;
      if (!events.length) {
        message.text(config.empty);
        return;
      }
      message.empty().prop('hidden', true);
      events.slice().reverse().forEach(function (event, index) {
        var item = $('<li>').appendTo(list);
        var marker = index === 0 && stage !== 3 ? 'dashicons-marker' : 'dashicons-yes';
        if (index === 0 && stage === 2) { item.addClass('is-current'); }
        $('<span class="dashicons omnivalt-tracking-event-marker" aria-hidden="true">').addClass(marker).appendTo(item);
        if (index === 0) { $('<strong>').text(label).appendTo(item); }
        $('<div class="omnivalt-tracking-event-name">').text(event.name).appendTo(item);
        if (event.date) { $('<div>').text(event.date).appendTo(item); }
        if (event.location) { $('<div>').text(event.location).appendTo(item); }
      });
      list.prop('hidden', false);
    }).fail(function () {
      if (historyRequest === current) { message.text(config.error); }
    }).always(function () {
      if (historyRequest === current) { historyRequest = null; }
    });
  });
  $(document).on('keydown.omnivaltTracking', function (event) {
    if (!dialog) { return; }
    if (event.key === 'Escape') { close(); }
    if (event.key === 'Tab') { event.preventDefault(); dialog.find('button').trigger('focus'); }
  });
  var cooldownTimer = null;
  function setSyncCooldown(button, seconds) {
    window.clearTimeout(cooldownTimer);
    button.prop('disabled', false);
    if (seconds > 0) {
      button.attr({'aria-disabled': 'true', 'aria-describedby': 'omnivalt-tracking-cooldown-help'});
      cooldownTimer = window.setTimeout(function () {
        button.removeAttr('aria-disabled aria-describedby');
      }, seconds * 1000);
    } else {
      button.removeAttr('aria-disabled aria-describedby');
    }
  }
  function watchSync(button, preparing) {
    var status = $('#omnivalt-tracking-updated');
    var lastSuccess = Number(status.attr('data-last-success'));
    var appliedShipmentStatus = $('#filter_shipment_status option[selected]').val();
    var statusFiltered = appliedShipmentStatus && appliedShipmentStatus !== '-1';
    var filterForm = document.getElementById('filter-form');
    var notices = $('.omnivalt-tracking-notices');
    var lastLabel = status.text();
    notices.find('.omnivalt-tracking-request-error, .omnivalt-tracking-list-refresh').remove();
    var attempts = 0;
    var retryAfter = 0;
    function offerListRefresh(message) {
      var notice = $('<div>', {class: 'notice notice-info omnivalt-tracking-notice omnivalt-tracking-list-refresh'});
      $('<p>', {role: 'status', text: message}).appendTo(notice);
      $('<p>').append($('<button>', {
        class: 'button',
        type: 'submit',
        form: filterForm.id,
        formaction: trackingListTarget(filterForm),
        text: config.refreshList
      })).appendTo(notice);
      notices.prepend(notice);
    }
    function checkAgain(delay) {
      if (attempts >= 90) {
        status.text(config.continuing);
        if (statusFiltered) { offerListRefresh(config.continuing); }
        else if (!preparing) { refreshRows(); }
        setSyncCooldown(button, retryAfter);
        return;
      }
      attempts++;
      progressTimer = window.setTimeout(function () {
        request('status').done(receive).fail(function (xhr) { failed(xhr); });
      }, delay);
    }
    function receive(response) {
      if (!response.success) { failed(); return; }
      preparing = response.data.preparing;
      retryAfter = response.data.retry_after;
      if (preparing) {
        status.text(response.data.label);
        checkAgain(5000);
        return;
      }
      lastLabel = response.data.label;
      if (!response.data.error) { notices.find('.omnivalt-tracking-failure').remove(); }
      if (response.data.failed) { failed(null, response.data.failure_message); return; }
      if (!response.data.complete) { checkAgain(2000); return; }
      status.text(response.data.label);
      if (statusFiltered) {
        if (Number(response.data.last_success) > lastSuccess) {
          if (hasUnappliedFilters(filterForm)) {
            offerListRefresh(config.listReady);
          } else {
            filterForm.action = trackingListTarget(filterForm);
            filterForm.submit();
            return;
          }
        }
      } else {
        refreshRows();
      }
      setSyncCooldown(button, retryAfter);
    }
    function failed(xhr, failureMessage) {
      if (preparing) { checkAgain(5000); return; }
      var data = xhr && xhr.status === 409 && xhr.responseJSON && xhr.responseJSON.data;
      var message = typeof failureMessage === 'string' && failureMessage ? failureMessage : (data && data.message ? data.message : config.error);
      var notice = $('<div class="notice notice-error omnivalt-tracking-notice omnivalt-tracking-request-error" role="alert"></div>');
      $('<p>').text(message).appendTo(notice);
      notices.prepend(notice);
      status.text(lastLabel); setSyncCooldown(button, retryAfter);
    }
    if (preparing) { checkAgain(5000); return; }
    button.prop('disabled', true); status.text(config.loading);
    request('sync').done(receive).fail(function (xhr) { failed(xhr); });
  }
  $(document).on('click', '#omnivalt-tracking-sync', function () {
    var button = $(this);
    if (button.attr('aria-disabled') !== 'true') { watchSync(button, false); }
  });
  var syncButton = $('#omnivalt-tracking-sync');
  if (syncButton.is('[data-tracking-preparing]')) {
    watchSync(syncButton, true);
  } else if (syncButton.length) {
    setSyncCooldown(syncButton, Number(syncButton.attr('data-retry-after')));
  }
  $(document).on('click', '.omnivalt-tracking-dismiss', function () {
    var notice = $(this).closest('.omnivalt-tracking-gap');
    request('dismiss_gap', {gap_id: notice.attr('data-gap-id')}).done(function (response) { if (response.success) { notice.remove(); } });
  });
  $(window).on('pagehide', function () { close(); if (progressTimer) { window.clearTimeout(progressTimer); } });
}(jQuery));
