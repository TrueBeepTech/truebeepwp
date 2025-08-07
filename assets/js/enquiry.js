(function ($) {
  $('#shazabo-manager-enquiry-form form').on('submit', function (event) {
    event.preventDefault();

    var data = $(this).serialize();

    $.post(shazabo_manager_data.ajax_url, data, function (response) {
      console.log('response ', response);
    }).fail(function () {
      console.log(shazabo_manager_data.message);
    });
  });
})(jQuery);
