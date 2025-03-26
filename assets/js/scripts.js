jQuery(document).ready(function ($) {
      jQuery('#wpce-search-form').on('submit', function () {
            jQuery(this).find('input, select').each(function () {
                  if (!jQuery(this).val()) {
                        jQuery(this).removeAttr('name');
                  }
            });
      });
});
