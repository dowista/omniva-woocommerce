jQuery(function($) {
  'use strict';

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

  $('#omnivalt-settings-root .omniva-tabs__tab').on('click', function() {
    var $tab = $(this);

    $tab.addClass('is-active').attr('aria-current', 'page');
    $tab.siblings('.omniva-tabs__tab').removeClass('is-active').removeAttr('aria-current');
  });

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
