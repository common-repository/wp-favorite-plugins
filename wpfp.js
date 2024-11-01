(function($) {
  $(document).ready(function() {

    // $('.wpfp-add-plugin').on('click', function(e) {
    //   e.preventDefault();

    //   // Post data
    //   $.get($(this).attr('href'), function(data) {
    //     console.log(data);
    //   });

    // });

    /**
     * Add the bulk actions to the fields
     */
    $(document).ready(function() {
      $('<option>').val('favorite').text(wpfp.favorite).appendTo("select[name='action'], select[name='action2']");
      $('<option>').val('unfavorite').text(wpfp.unfavorite).appendTo("select[name='action'], select[name='action2']");
    });

  });
})(jQuery);