jQuery(function($) {
  'use strict';

  initializeShippingMethodsLayout();
  initializePositionSortable();

  function initializeShippingMethodsLayout() {
    var $layout = $('#omnivalt-settings-root').find('[data-omniva-shipping-layout]');
    if (!$layout.length) {
      return;
    }

    var $source = $layout.find('[data-omniva-delivery-source]');
    var $groups = $layout.find('[data-omniva-method-group]');
    var $internationalGroup = $groups.filter(function() {
      return $(this).attr('data-method-key') === 'international';
    });
    var destinationIndex = 0;

    $source.find('.prices_box').each(function() {
      var $sourceBox = $(this);
      var countryCode = ($sourceBox.attr('data-country') || '').toUpperCase();
      var planKey = $sourceBox.attr('data-plan') || '';
      var destinationType = countryCode ? 'country' : 'plan';
      var destinationKey = countryCode || planKey;
      var destinationTitle = $.trim($sourceBox.find('.pb-lang span').first().text());
      var imageSource = $sourceBox.find('.pb-lang img').first().attr('src');

      $sourceBox.find('.block-prices[data-method]').each(function() {
        var $block = $(this);
        var methodKey = $block.attr('data-method-key') || $block.attr('data-method');
        var $group = $groups.filter(function() {
          return $(this).attr('data-method-key') === methodKey;
        });

        if (!$group.length) {
          $group = $internationalGroup;
        }
        if (!$group.length || !destinationKey) {
          return;
        }

        var $list = $group.find('[data-method-country-list]').first();
        var $tabs = $group.find('[data-method-country-tabs]').first();
        var $destination = $list.children('[data-omniva-destination]').filter(function() {
          return $(this).attr('data-destination-type') === destinationType
            && $(this).attr('data-destination-key') === destinationKey;
        }).first();

        if (!$destination.length) {
          var $destinationBox = $('<div class="prices_box omniva-delivery-country__box"></div>');
          if (destinationType === 'country') {
            $destinationBox.attr('data-country', destinationKey);
          } else {
            $destinationBox.attr('data-plan', destinationKey);
          }

          destinationIndex += 1;
          var destinationId = 'omniva-delivery-country-' + destinationIndex;
          var destinationTabId = destinationId + '-tab';
          var $destinationTab = $('<div class="omniva-delivery-country-tab" data-omniva-country-tab role="presentation"></div>');
          var $destinationButton = $('<button type="button" class="omniva-delivery-country-tab__button" role="tab" aria-selected="false"></button>');
          var $destinationTitle = $('<span class="omniva-delivery-country-tab__title"></span>');
          if (imageSource) {
            $destinationTitle.append(
              $('<img class="omniva-delivery-country__flag" alt="">').attr('src', imageSource)
            );
          }
          $destinationTitle.append($('<span></span>').text(destinationTitle));
          $destinationButton.append($destinationTitle);
          $destinationButton.attr('id', destinationTabId);
          var $destinationToggle = $('<span class="omniva-delivery-country-tab__toggle" data-omniva-country-toggle-wrap></span>');
          $destinationTab.append($destinationButton, $destinationToggle).appendTo($tabs);

          var $destinationHeader = $('<div class="omniva-delivery-country__header omniva-delivery-country__destination-header"></div>');
          var $destinationHeaderTitle = $('<span class="omniva-delivery-country__title"></span>');
          if (imageSource) {
            $destinationHeaderTitle.append(
              $('<img class="omniva-delivery-country__flag" alt="">').attr('src', imageSource)
            );
          }
          $destinationHeaderTitle.append($('<span></span>').text(destinationTitle));
          $destinationHeader.append($destinationHeaderTitle);

          var $destinationBody = $('<div class="omniva-delivery-country__body" data-destination-body></div>');
          $destinationBox.append($destinationHeader, $destinationBody);
          $destination = $('<table class="form-table omniva-settings omniva-delivery-country" data-omniva-destination role="tabpanel"><tbody><tr><td></td></tr></tbody></table>')
            .attr('id', destinationId)
            .attr('aria-labelledby', destinationTabId)
            .attr('aria-hidden', 'true')
            .attr('hidden', 'hidden')
            .attr('data-destination-type', destinationType)
            .attr('data-destination-key', destinationKey)
            .appendTo($list);
          $destination.find('td').append($destinationBox);
          $destination.data('omniva-destination-tab', $destinationTab);
          $destinationTab.data('omniva-destination', $destination);
          $destinationButton.data('omniva-destination', $destination);
          $destinationButton.on('click', function() {
            activateDestination($(this).data('omniva-destination'));
          });

          if (destinationType === 'plan') {
            var $planToggle = $('<div class="switcher" title=""></div>');
            var $planToggleInput = $('<input type="checkbox" class="omniva-international-toggle" data-omniva-destination-toggle>');
            var $planToggleLabel = $('<label class="switch"></label>');
            $planToggleLabel.append($planToggleInput, '<span class="slider round"></span>');
            $planToggle.append($planToggleLabel);
            $planToggle.attr('title', destinationTitle);
            $planToggleInput.data('omniva-destination', $destination);
            $destinationToggle.append($planToggle);
          }
        }

        $destinationTab = $destination.data('omniva-destination-tab');

        $block.appendTo($destination.find('[data-destination-body]'));

        var $switcher = $block.find('.sec-title .switcher').first();
        var $methodLabel = $block.find('.sec-title > label').first();
        if ($switcher.length) {
          var $countryToggle = $switcher.find('input[type="checkbox"]').first();
          $countryToggle
            .attr('data-omniva-country-toggle', 'true')
            .attr('aria-label', $.trim($methodLabel.text()) || destinationTitle);
          $block.data('omniva-country-toggle', $countryToggle);
          $countryToggle.data('omniva-destination', $destination);

          if (destinationType === 'country') {
            $destinationTab.find('[data-omniva-country-toggle-wrap]').append($switcher);
            $methodLabel.remove();
            $block.find('.sec-title').remove();
          } else {
            var $methodHeader = $('<div class="omniva-delivery-country__header omniva-delivery-country__method-header"></div>');
            if ($methodLabel.length) {
              $methodHeader.append($methodLabel);
            }
            $methodHeader.append($switcher);
            $block.find('.sec-title').remove();
            $methodHeader.insertBefore($block);
          }
        }
      });
    });

    $source.remove();

    $('#omnivalt-settings-root').on('change', '#woocommerce_omnivalt_api_country, input[id^="woocommerce_omnivalt_method_"]', function() {
      // The legacy handler also updates the checkbox state. Refresh after it has finished.
      window.setTimeout(refreshShippingMethodsLayout, 0);
    });
    $('#omnivalt-settings-root').on('change', 'input[data-omniva-country-toggle]', function() {
      var $destination = getDestinationForControl($(this));

      if (!$destination.length) {
        return;
      }

      $destination.find('.block-prices[data-method]').each(function() {
        updateCountryBlock($(this), false);
      });

      if ($(this).is(':checked')) {
        activateDestination($destination);
      } else {
        refreshDestinationTabs($destination.closest('[data-omniva-method-group]'));
      }
    });
    $('#omnivalt-settings-root').on('change', 'input[data-omniva-destination-toggle]', function() {
      var $destination = getDestinationForControl($(this));

      if (!$destination.length) {
        return;
      }

      var enabled = $(this).is(':checked');

      $destination.find('input[data-omniva-country-toggle]').each(function() {
        var $regionToggle = $(this);
        if ($regionToggle.is(':checked') !== enabled) {
          $regionToggle.prop('checked', enabled).trigger('change');
        }
      });

      if (enabled) {
        activateDestination($destination);
      } else {
        refreshDestinationTabs($destination.closest('[data-omniva-method-group]'));
      }
    });

    refreshShippingMethodsLayout();
    $('#omnivalt-settings-root').removeClass('is-layout-pending');

    function refreshShippingMethodsLayout() {
      var availableMethods = getAvailableMethods();

      $groups.each(function() {
        var $group = $(this);
        var methodKey = $group.attr('data-method-key');
        if (methodKey === 'international') {
          $group.find('[data-omniva-destination] .block-prices[data-method]').each(function() {
            updateCountryBlock($(this), false);
          });
          $group.toggle($group.find('[data-omniva-destination]').length > 0);
          refreshDestinationTabs($group);
          return;
        }

        var $globalField = $('#woocommerce_omnivalt_method_' + methodKey);
        var methodEnabled = !$globalField.length || ($globalField.is(':checked') && !$globalField.is(':disabled'));
        $group.toggleClass('is-method-disabled', !methodEnabled);

        $group.find('[data-omniva-destination]').each(function() {
          var $destination = $(this);
          var countryCode = $destination.attr('data-destination-key');
          var allowed = isMethodAvailable(availableMethods[countryCode], methodKey);
          var $destinationTab = $destination.data('omniva-destination-tab');

          $destination.toggleClass('is-unavailable', !allowed);
          if ($destinationTab && $destinationTab.length) {
            $destinationTab.toggleClass('is-unavailable', !allowed);
          }
          $destination.find('.block-prices[data-method]').each(function() {
            var $block = $(this);
            $block.toggleClass('is-method-disabled', !methodEnabled || !allowed);
            updateCountryBlock($block, !methodEnabled || !allowed);
          });
        });

        refreshDestinationTabs($group);
      });
    }

    function updateCountryBlock($block, methodDisabled) {
      var $destination = $block.closest('[data-omniva-destination]');
      var $destinationTab = $destination.data('omniva-destination-tab');
      var $toggle = $block.data('omniva-country-toggle');
      if (!$toggle || !$toggle.length) {
        $toggle = $block.find('input[data-omniva-country-toggle]').first();
      }
      if (!$toggle.length) {
        $toggle = $destination.find('input[data-omniva-country-toggle]').first();
      }
      var enabled = !$toggle.length || $toggle.is(':checked');
      var editable = enabled && !methodDisabled;
      $block.toggleClass('is-country-disabled', !enabled);
      $block.toggleClass('disabled', !!methodDisabled);
      $block.find('.sec-prices, .sec-other').toggleClass('disabled', !editable);
      if ($destination.length) {
        var $countryToggles = $destination.find('input[data-omniva-country-toggle]');
        if (!$countryToggles.length && $destinationTab && $destinationTab.length) {
          $countryToggles = $destinationTab.find('input[data-omniva-country-toggle]');
        }
        var hasEnabledBlock = $countryToggles.filter(':checked').length > 0;
        $destination.toggleClass('is-country-disabled', !hasEnabledBlock);
        if ($destinationTab && $destinationTab.length) {
          $destinationTab.toggleClass('is-country-disabled', !hasEnabledBlock);
          $destinationTab.find('[role="tab"]')
            .prop('disabled', !hasEnabledBlock)
            .attr('aria-disabled', hasEnabledBlock ? 'false' : 'true');
          $destinationTab.find('input[data-omniva-destination-toggle]').prop('checked', hasEnabledBlock);
        }
      }
    }

    function getDestinationForControl($control) {
      var $destination = $control.closest('[data-omniva-destination]');
      if ($destination.length) {
        return $destination;
      }

      $destination = $control.data('omniva-destination');
      if ($destination && $destination.length) {
        return $destination;
      }

      $destination = $control.closest('[data-omniva-country-tab]').data('omniva-destination');
      return ($destination && $destination.length) ? $destination : $();
    }

    function activateDestination($destination) {
      if (!$destination || !$destination.length || $destination.hasClass('is-unavailable')) {
        return;
      }

      var $list = $destination.closest('[data-method-country-list]');
      var $group = $destination.closest('[data-omniva-method-group]');
      var $tabs = $group.find('[data-method-country-tabs]').first();
      var $destinationTab = $destination.data('omniva-destination-tab');

      if (!$list.length || !$destinationTab || !$destinationTab.length || $destination.hasClass('is-country-disabled') || $destinationTab.hasClass('is-country-disabled')) {
        return;
      }

      $list.children('[data-omniva-destination]')
        .removeClass('is-active')
        .attr('aria-hidden', 'true')
        .attr('hidden', 'hidden');
      $tabs.children('[data-omniva-country-tab]')
        .removeClass('is-active')
        .find('[role="tab"]')
        .attr('aria-selected', 'false')
        .attr('tabindex', '-1');

      $destination.addClass('is-active').attr('aria-hidden', 'false').removeAttr('hidden');
      $destinationTab.addClass('is-active');
      $destinationTab.find('[role="tab"]')
        .attr('aria-selected', 'true')
        .attr('tabindex', '0');
    }

    function refreshDestinationTabs($group) {
      var $destinations = $group.find('[data-omniva-destination]');
      var $availableDestinations = $destinations.filter(':not(.is-unavailable):not(.is-country-disabled)');
      var $activeDestination = $destinations.filter('.is-active').first();

      if (
        !$activeDestination.length ||
        $activeDestination.hasClass('is-unavailable') ||
        $activeDestination.hasClass('is-country-disabled')
      ) {
        $activeDestination = $availableDestinations.first();
      }

      if ($activeDestination.length) {
        activateDestination($activeDestination);
        return;
      }

      $destinations.removeClass('is-active').attr('aria-hidden', 'true').attr('hidden', 'hidden');
      $group.find('[data-method-country-tabs] [data-omniva-country-tab]')
        .removeClass('is-active')
        .find('[role="tab"]')
        .attr('aria-selected', 'false')
        .attr('tabindex', '-1');
    }

    function getAvailableMethods() {
      var apiCountry = $('#woocommerce_omnivalt_api_country').val();
      if (window.omnivalt_params && window.omnivalt_params.available_methods && window.omnivalt_params.available_methods[apiCountry]) {
        return window.omnivalt_params.available_methods[apiCountry];
      }

      return {};
    }

    function isMethodAvailable(countryMethods, methodKey) {
      if (!countryMethods) {
        return false;
      }

      var aliases = {
        'pt': ['pt', 'terminal', 'pickup'],
        'c': ['c', 'courier'],
        'cp': ['cp', 'courier_plus'],
        'pc': ['pc', 'private_customer'],
        'pn': ['pn', 'post_near'],
        'ps': ['ps', 'post_specific'],
        'lc': ['lc', 'letter_courier'],
        'lp': ['lp', 'letter_post']
      };
      var methodAliases = aliases[methodKey] || [methodKey];

      for (var i = 0; i < methodAliases.length; i++) {
        if ($.inArray(methodAliases[i], countryMethods) !== -1) {
          return true;
        }
      }

      return false;
    }
  }

  function initializePositionSortable() {
    $('#omnivalt-settings-root .field-position').each(function() {
      var $fieldset = $(this);
      var $table = $fieldset.children('table').first();
      var $rows = $table.find('tr');
      var $list = $('<ol class="omnivalt-position-list" data-settings-position-list></ol>');
      var items = [];

      if (!$table.length || !$rows.length) {
        return;
      }

      for (var rowIndex = 0; rowIndex < $rows.length; rowIndex += 2) {
        var $labelCells = $rows.eq(rowIndex).children('th');
        var $valueCells = $rows.eq(rowIndex + 1).children('td');

        $labelCells.each(function(cellIndex) {
          var $input = $valueCells.eq(cellIndex).find('input[type="number"]').first();

          if (!$input.length) {
            return;
          }

          items.push({
            input: $input,
            title: $.trim($(this).text())
          });
        });
      }

      if (!items.length) {
        return;
      }

      $.each(items, function(index, item) {
        var $handle = $('<span class="omnivalt-position-list__handle" aria-hidden="true">&#8942;</span>');
        var $title = $('<span class="omnivalt-position-list__title"></span>').text(item.title);
        var $listItem = $('<li class="omnivalt-position-list__item"></li>');

        $listItem.append($handle, $title, item.input);
        $list.append($listItem);
      });

      $table.replaceWith($list);
      sortPositionItems($list);
      var initialOrder = $list.children('.omnivalt-position-list__item').toArray();

      if ($.fn.sortable) {
        $list.sortable({
          axis: 'y',
          cursor: 'grabbing',
          forcePlaceholderSize: true,
          placeholder: 'omnivalt-position-list__placeholder',
          update: function() {
            updatePositionValues($list);
          }
        });
      }

      $list.data('omnivaltPositionReset', function() {
        $.each(initialOrder, function(index, item) {
          $list.append(item);
        });

        if ($.fn.sortable && $list.hasClass('ui-sortable')) {
          $list.sortable('refresh');
        }
      });
    });

    function updatePositionValues($list) {
      $list.children('.omnivalt-position-list__item').each(function(index) {
        $(this).find('input[type="number"]').val(index + 1).trigger('change');
      });
    }

    function sortPositionItems($list) {
      var positioned = [];
      var unpositioned = [];
      var ordered = [];

      $list.children('.omnivalt-position-list__item').each(function(index) {
        var value = $(this).find('input[type="number"]').val();
        var position = parseInt(value, 10);

        if (value !== '' && !isNaN(position) && position !== 0) {
          positioned.push({
            item: this,
            position: position,
            originalIndex: index
          });
        } else {
          unpositioned.push(this);
        }
      });

      positioned.sort(function(first, second) {
        if (first.position === second.position) {
          return first.originalIndex - second.originalIndex;
        }

        return first.position - second.position;
      });

      $.each(positioned, function(index, positionedItem) {
        var targetIndex = positionedItem.position > 0 ? positionedItem.position - 1 : 0;

        while (ordered[targetIndex]) {
          targetIndex++;
        }

        ordered[targetIndex] = positionedItem.item;
      });

      $.each(unpositioned, function(index, item) {
        var targetIndex = 0;

        while (ordered[targetIndex]) {
          targetIndex++;
        }

        ordered[targetIndex] = item;
      });

      $.each(ordered, function(index, item) {
        if (item) {
          $list.append(item);
        }
      });
    }
  }

  // Keep sender details visually grouped as a compact data-entry form while
  // preserving WooCommerce's generated field names and values.
  $( [
    '#woocommerce_omnivalt_company',
    '#woocommerce_omnivalt_shop_name',
    '#woocommerce_omnivalt_shop_city',
    '#woocommerce_omnivalt_shop_address',
    '#woocommerce_omnivalt_shop_postcode',
    '#woocommerce_omnivalt_shop_email',
    '#woocommerce_omnivalt_bank_account'
  ].join(', ') ).each(function() {
    var $input = $(this);
    var $row = $input.closest('tr');
    var $label = $row.children('th').find('label').first();

    if (!$label.length) {
      return;
    }

    $row.addClass('omnivalt-floating-field');
    $input.wrap('<span class="omnivalt-floating-input"></span>');

    var $wrapper = $input.parent();
    $label.addClass('omnivalt-floating-input__label').prependTo($wrapper);

    function updateFloatingLabel() {
      $wrapper.toggleClass('is-active', $input.is(':focus') || $input.val().length > 0 || $input.is('[placeholder]'));
    }

    $input.on('focus blur input change', updateFloatingLabel);
    updateFloatingLabel();
  });

  var $pickupStart = $('#woocommerce_omnivalt_pick_up_start');
  var $pickupEnd = $('#woocommerce_omnivalt_pick_up_end');

  if ($pickupStart.length && $pickupEnd.length) {
    function validatePickupWindow() {
      var startTime = $pickupStart.val();
      var endTime = $pickupEnd.val();
      var isInvalid = startTime && endTime && startTime >= endTime;
      var message = window.omnivaltSettingsPhone && window.omnivaltSettingsPhone.pickup_window_invalid
        ? window.omnivaltSettingsPhone.pickup_window_invalid
        : 'End time must be later than start time.';

      $pickupEnd[0].setCustomValidity(isInvalid ? message : '');

      return !isInvalid;
    }

    $pickupStart.add($pickupEnd).on('input change', validatePickupWindow);
    $('#omnivalt-settings-root form').on('submit', validatePickupWindow);
    validatePickupWindow();
  }

  function refreshSettingsTab($tab) {
    var selectedTab = $tab.attr('data-settings-tab');

    $('#omnivalt-settings-root [data-settings-card]').each(function() {
      var $card = $(this);
      var cardTab = $card.attr('data-settings-tab');

      $card.toggle(!cardTab || cardTab === selectedTab);
    });
  }

  $('#omnivalt-settings-root .omniva-tabs__tab').on('click', function() {
    var $tab = $(this);

    $tab.addClass('is-active').attr('aria-current', 'page');
    $tab.siblings('.omniva-tabs__tab').removeClass('is-active').removeAttr('aria-current');
    refreshSettingsTab($tab);
  });

  refreshSettingsTab($('#omnivalt-settings-root .omniva-tabs__tab.is-active').first());

  if (window.omnivaltSettingsPhone) {
    var phoneSettings = window.omnivaltSettingsPhone;

    // Replace the visible phone control with a country-aware input and keep the original field for submission.
    $('#woocommerce_omnivalt_shop_phone, #woocommerce_omnivalt_shop_mobile').each(function() {
      var $original = $(this);
      var isMobile = $original.attr('id') === 'woocommerce_omnivalt_shop_mobile';
      var selectedCountry = $('#woocommerce_omnivalt_shop_countrycode').val() || 'LT';
      var storedValue = $original.val() || '';
      var $wrapper = $('<span class="omnivalt-phone-input"></span>');
      var $country = $('<button type="button" class="omnivalt-phone-input__country" aria-label="Country calling code" aria-expanded="false"></button>');
      var $number = $('<input type="tel" class="omnivalt-phone-input__number" inputmode="tel" autocomplete="tel-national">');
      var $dropdown = $('<div class="omnivalt-phone-input__dropdown" role="listbox"></div>').hide();

      $.each(phoneSettings.countries, function(countryCode, countryData) {
        var prefix = '+' + countryData.dial_code;
        if (storedValue.indexOf(prefix) === 0) {
          selectedCountry = countryCode;
          storedValue = storedValue.substring(prefix.length);
          return false;
        }
      });

      $number.val(storedValue.replace(/\D/g, '')).attr('placeholder', phoneSettings.placeholder);
      $number.prop('required', isMobile);
      $original.attr('type', 'hidden').addClass('omnivalt-phone-input__value');
      $original.after($wrapper);
      $wrapper.append($country, $number, $dropdown);

      function flag(countryCode) {
        return $('<img class="omnivalt-phone-input__flag" alt="">').attr('src', phoneSettings.flag_url + countryCode.toLowerCase() + '.svg');
      }

      function renderCountryPicker() {
        var countryData = phoneSettings.countries[selectedCountry];

        $country.empty().append(
          flag(selectedCountry),
          $('<span class="omnivalt-phone-input__dial-code"></span>').text('+' + countryData.dial_code),
          $('<span class="omnivalt-phone-input__chevron" aria-hidden="true"></span>')
        );
        $dropdown.empty();
        $.each(phoneSettings.countries, function(countryCode, optionData) {
          var $option = $('<button type="button" class="omnivalt-phone-input__option" role="option"></button>');
          $option.attr('aria-selected', countryCode === selectedCountry ? 'true' : 'false');
          $option.toggleClass('is-selected', countryCode === selectedCountry);
          $option.append(flag(countryCode), $('<span></span>').text('+' + optionData.dial_code), $('<span></span>').text(optionData.name));
          $option.on('click', function() {
            selectedCountry = countryCode;
            renderCountryPicker();
            $dropdown.hide();
            $country.attr('aria-expanded', 'false').focus();
            syncPhone();
          });
          $dropdown.append($option);
        });
      }

      function syncPhone() {
        var countryData = phoneSettings.countries[selectedCountry];
        var number = $number.val().replace(/\D/g, '').slice(0, countryData.max);
        var isValid = !number || (number.length >= countryData.min && number.length <= countryData.max && (!isMobile || new RegExp(countryData.mobile).test(number)));

        $number.val(number);
        $number.attr('maxlength', countryData.max);
        $number[0].setCustomValidity(isValid ? '' : phoneSettings.invalid);
        $original.val(number ? '+' + countryData.dial_code + number : '');
      }

      $country.on('click', function() {
        var isOpen = $dropdown.is(':visible');
        $dropdown.toggle(!isOpen);
        $country.attr('aria-expanded', isOpen ? 'false' : 'true');
      });
      $(document).on('mousedown', function(event) {
        if (!$(event.target).closest($wrapper).length) {
          $dropdown.hide();
          $country.attr('aria-expanded', 'false');
        }
      });
      $country.on('keydown', function(event) {
        if (event.key === 'Escape') {
          $dropdown.hide();
          $country.attr('aria-expanded', 'false');
        }
      });
      $number.on('input change blur', syncPhone);
      renderCountryPicker();
      syncPhone();

      var initialPhoneValue = $original.val() || '';
      var initialPhoneCountry = selectedCountry;

      $original.data('omnivaltPhoneReset', function() {
        var countryData = phoneSettings.countries[initialPhoneCountry];
        var number = initialPhoneValue;
        var prefix = '+' + countryData.dial_code;

        if (number.indexOf(prefix) === 0) {
          number = number.substring(prefix.length);
        }

        selectedCountry = initialPhoneCountry;
        $number.val(number.replace(/\D/g, ''));
        renderCountryPicker();
        syncPhone();
      });
    });
  }

  $('#omnivalt-settings-root select.wc-enhanced-select[multiple]').each(function() {
    // Keep the original select in the form and use a searchable UI for selecting multiple values.
    var $select = $(this);
    var $select2 = $select.next('.select2-container');
    var $picker = $('<div class="omniva-picker"></div>');
    var $selectedWrap = $('<div class="omniva-picker__selected-wrap"></div>');
    var $selectedHeader = $('<div class="omniva-picker__selected-header"></div>');
    var $selectedCount = $('<span class="omniva-picker__selected-count"></span>');
    var $clear = $('<button type="button" class="omniva-picker__clear"></button>').text('Clear all');
    var $selected = $('<div class="omniva-picker__selected"></div>');
    var $inputWrap = $('<div class="omniva-picker__input-wrap"></div>');
    var $input = $('<input type="search" class="omniva-input omniva-picker__search" autocomplete="off" aria-expanded="false" aria-haspopup="listbox">');
    var $dropdown = $('<div class="omniva-picker__dropdown" role="listbox" aria-multiselectable="true"></div>').hide();

    if ($select.attr('id') === 'woocommerce_omnivalt_restricted_categories') {
      $input.attr('placeholder', 'Search categories...');
    } else if ($select.attr('id') === 'woocommerce_omnivalt_restricted_shipclass') {
      $input.attr('placeholder', 'Search shipping classes...');
    } else {
      $input.attr('placeholder', 'Search...');
    }
    $selectedHeader.append($selectedCount, $clear);
    $selectedWrap.append($selectedHeader, $selected);
    $inputWrap.append($input);
    $picker.append($selectedWrap, $inputWrap, $dropdown);
    $picker.insertAfter($select2);
    $select2.hide();

    function getValues() {
      return $select.val() || [];
    }

    function setValues(values) {
      $select.val(values).trigger('change');
    }

    function renderSelected() {
      var values = getValues();

      $selected.empty();
      if (!values.length) {
        $selectedWrap.hide();
        return;
      }

      $selectedWrap.show();
      $selectedCount.text(values.length + ' selected');
      $select.find('option:selected').each(function() {
        var optionValue = $(this).val();
        var $tag = $('<span class="omniva-picker__tag"></span>');
        var $remove = $('<button type="button" class="omniva-picker__remove" aria-label="Remove selected item">&times;</button>');

        $tag.append($('<span></span>').text($(this).text()), $remove);
        $remove.on('click', function() {
          setValues(getValues().filter(function(value) {
            return value !== optionValue;
          }));
        });
        $selected.append($tag);
      });
    }

    function renderOptions() {
      var query = $input.val().toLowerCase();
      var values = getValues();
      var $list = $('<div class="omniva-picker__list"></div>');
      var count = 0;

      $select.find('option').each(function() {
        var value = $(this).val();
        var label = $(this).text();
        var isSelected = values.indexOf(value) !== -1;

        if (label.toLowerCase().indexOf(query) === -1) {
          return;
        }

        count++;
        var $option = $('<button type="button" class="omniva-picker__option" role="option"></button>');
        $option.toggleClass('omniva-picker__option--selected', isSelected);
        $option.attr('aria-selected', isSelected ? 'true' : 'false');
        $option.append(
          $('<span class="omniva-picker__option-check"></span>').text(isSelected ? '\u2713' : ''),
          $('<span class="omniva-picker__option-label"></span>').text(label)
        );
        $option.on('mousedown', function(event) {
          event.preventDefault();
          var nextValues = getValues().slice();
          var index = nextValues.indexOf(value);

          if (index === -1) {
            nextValues.push(value);
          } else {
            nextValues.splice(index, 1);
          }

          setValues(nextValues);
          renderOptions();
        });
        $list.append($option);
      });

      if (!count) {
        $list.append($('<p class="omniva-picker__empty"></p>').text('No matches found.'));
      }

      $dropdown.empty().append($list);
    }

    $clear.on('click', function() {
      setValues([]);
    });
    $input.on('focus input', function() {
      renderOptions();
      $dropdown.show();
      $input.attr('aria-expanded', 'true');
    });
    $input.on('blur', function() {
      window.setTimeout(function() {
        $dropdown.hide();
        $input.attr('aria-expanded', 'false');
      }, 150);
    });
    $select.on('change', renderSelected);

    renderSelected();
  });

  initializeSettingsSaveBar();

  function initializeSettingsSaveBar() {
    var $root = $('#omnivalt-settings-root');
    var $form = $root.find('form').first();
    var $status = $root.find('[data-settings-save-status]');
    var $statusText = $status.find('[data-settings-save-status-text]');
    var $discard = $root.find('[data-settings-discard]');
    var $save = $root.find('.woocommerce-save-button').first();

    if (!$form.length || !$status.length || !$discard.length || !$save.length) {
      return;
    }

    var initialControls = [];
    var savedFormState;
    var updateTimer = null;

    $form.find(':input').each(function() {
      var $control = $(this);
      var value = $control.val();

      initialControls.push({
        element: this,
        type: (this.type || '').toLowerCase(),
        value: $.isArray(value) ? value.slice() : value,
        checked: this.checked
      });
    });

    savedFormState = $form.serialize();

    function renderSaveState() {
      var hasUnsavedChanges = $form.serialize() !== savedFormState;
      var statusLabel = hasUnsavedChanges ? $status.attr('data-unsaved-label') : $status.attr('data-saved-label');

      $status
        .toggleClass('is-unsaved', hasUnsavedChanges)
        .toggleClass('is-saved', !hasUnsavedChanges);
      $statusText.text(statusLabel || '');
      $discard.prop('hidden', !hasUnsavedChanges);
      $save.prop('disabled', !hasUnsavedChanges);
    }

    function scheduleSaveStateUpdate() {
      if (updateTimer) {
        window.clearTimeout(updateTimer);
      }

      updateTimer = window.setTimeout(function() {
        updateTimer = null;
        renderSaveState();
      }, 0);
    }

    $form.on('input.omnivaltSettingsSave change.omnivaltSettingsSave', ':input', scheduleSaveStateUpdate);

    $discard.on('click', function() {
      $.each(initialControls, function(index, controlState) {
        var $control = $(controlState.element);

        if (!$control.length) {
          return;
        }

        $control.val(controlState.value);
        if (controlState.type === 'checkbox' || controlState.type === 'radio') {
          $control.prop('checked', controlState.checked);
        }
      });

      $form.find(':input').trigger('change');
      $form.find('.omnivalt-phone-input__value').each(function() {
        var resetPhone = $(this).data('omnivaltPhoneReset');

        if ($.isFunction(resetPhone)) {
          resetPhone();
        }
      });
      $form.find('[data-settings-position-list]').each(function() {
        var resetPositionList = $(this).data('omnivaltPositionReset');

        if ($.isFunction(resetPositionList)) {
          resetPositionList();
        }
      });

      renderSaveState();
    });

    renderSaveState();
  }
});
