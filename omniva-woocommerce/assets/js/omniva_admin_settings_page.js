jQuery(function($) {
  'use strict';

  initializeShippingMethodsLayout();

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

          var $destinationHeader = $('<div class="omniva-delivery-country__header"></div>');
          var $destinationTitle = $('<div class="omniva-delivery-country__title"></div>');
          if (imageSource) {
            $destinationTitle.append(
              $('<img class="omniva-delivery-country__flag" alt="">').attr('src', imageSource)
            );
          }
          $destinationTitle.append($('<span></span>').text(destinationTitle));
          $destinationHeader.append($destinationTitle);
          if (destinationType === 'country' || destinationType === 'plan') {
            $destinationHeader.append('<div class="omniva-delivery-country__toggle"></div>');
          }

          var $destinationBody = $('<div class="omniva-delivery-country__body" data-destination-body></div>');
          $destinationBox.append($destinationHeader, $destinationBody);
          $destination = $('<table class="form-table omniva-settings omniva-delivery-country" data-omniva-destination><tbody><tr><td></td></tr></tbody></table>')
            .attr('data-destination-type', destinationType)
            .attr('data-destination-key', destinationKey)
            .appendTo($list);
          $destination.find('td').append($destinationBox);

          if (destinationType === 'plan') {
            var $planToggle = $('<div class="switcher" title=""></div>');
            var $planToggleInput = $('<input type="checkbox" class="omniva-international-toggle" data-omniva-destination-toggle>');
            var $planToggleLabel = $('<label class="switch"></label>');
            $planToggleLabel.append($planToggleInput, '<span class="slider round"></span>');
            $planToggle.append($planToggleLabel);
            $planToggle.attr('title', destinationTitle);
            $destination.find('.omniva-delivery-country__toggle').append($planToggle);
          }
        }

        $block.appendTo($destination.find('[data-destination-body]'));

        var $switcher = $block.find('.sec-title .switcher').first();
        var $methodLabel = $block.find('.sec-title > label').first();
        if ($switcher.length) {
          var $countryToggle = $switcher.find('input[type="checkbox"]').first();
          $countryToggle
            .attr('data-omniva-country-toggle', 'true')
            .attr('aria-label', $.trim($methodLabel.text()) || destinationTitle);
          $block.data('omniva-country-toggle', $countryToggle);

          if (destinationType === 'country') {
            $destination.find('.omniva-delivery-country__toggle').append($switcher);
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
      var $destination = $(this).closest('[data-omniva-destination]');
      $destination.find('.block-prices[data-method]').each(function() {
        updateCountryBlock($(this), false);
      });
    });
    $('#omnivalt-settings-root').on('change', 'input[data-omniva-destination-toggle]', function() {
      var $destination = $(this).closest('[data-omniva-destination]');
      var enabled = $(this).is(':checked');

      $destination.find('input[data-omniva-country-toggle]').each(function() {
        var $regionToggle = $(this);
        if ($regionToggle.is(':checked') !== enabled) {
          $regionToggle.prop('checked', enabled).trigger('change');
        }
      });
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
          return;
        }

        var $globalField = $('#woocommerce_omnivalt_method_' + methodKey);
        var methodEnabled = !$globalField.length || ($globalField.is(':checked') && !$globalField.is(':disabled'));
        $group.toggleClass('is-method-disabled', !methodEnabled);

        $group.find('[data-omniva-destination]').each(function() {
          var $destination = $(this);
          var countryCode = $destination.attr('data-destination-key');
          var allowed = isMethodAvailable(availableMethods[countryCode], methodKey);

          $destination.toggleClass('is-unavailable', !allowed).toggle(allowed);
          $destination.find('.block-prices[data-method]').each(function() {
            var $block = $(this);
            $block.toggleClass('is-method-disabled', !methodEnabled || !allowed);
            updateCountryBlock($block, !methodEnabled || !allowed);
          });
        });
      });
    }

    function updateCountryBlock($block, methodDisabled) {
      var $destination = $block.closest('[data-omniva-destination]');
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
        var hasEnabledBlock = $destination.find('input[data-omniva-country-toggle]:checked').length > 0;
        $destination.toggleClass('is-country-disabled', !hasEnabledBlock);
        $destination.find('input[data-omniva-destination-toggle]').prop('checked', hasEnabledBlock);
      }
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

  // Add the custom visual treatment while keeping WooCommerce's generated field names and values.
  $('#woocommerce_omnivalt_shop_name').each(function() {
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
      $wrapper.toggleClass('is-active', $input.is(':focus') || $input.val().length > 0);
    }

    $input.on('focus blur input change', updateFloatingLabel);
    updateFloatingLabel();
  });

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
});
