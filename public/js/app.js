(function ($) {
  'use strict';

  $(function () {
    // quantity selector
    $(document).on('click', '[data-qty-step]', function () {
      var $input = $(this).closest('.qty-selector').find('input[type=number]');
      var step = parseInt($(this).attr('data-qty-step'), 10);
      var min = parseInt($input.attr('min'), 10) || 1;
      var max = parseInt($input.attr('max'), 10) || 20;
      var next = Math.min(max, Math.max(min, (parseInt($input.val(), 10) || min) + step));
      $input.val(next).trigger('change');
    });

    // cart: submit quantity change automatically
    $('.cart-qty-form input[type=number]').on('change', function () {
      $(this).closest('form').submit();
    });

    // checkout: live shipping fee lookup
    var $zip = $('#ship_zip');
    if ($zip.length) {
      var lookup = function () {
        var zip = $.trim($zip.val());
        var subtotal = parseInt($('#checkout-summary').attr('data-subtotal'), 10) || 0;
        if (!/^\d{3}-?\d{4}$/.test(zip)) {
          $('#shipping-fee').text('郵便番号を入力してください');
          $('#shipping-region').text('');
          return;
        }
        $('#shipping-fee').text('計算中…');
        $.getJSON('/api/shipping.php', { zip: zip, subtotal: subtotal })
          .done(function (data) {
            var fee = parseInt(data.fee, 10);
            $('#shipping-fee').text(fee === 0 ? '無料' : '¥' + fee.toLocaleString('ja-JP'));
            $('#shipping-region').text('（' + data.region_label + '）');
            $('#order-total').text('¥' + (subtotal + fee).toLocaleString('ja-JP'));
          })
          .fail(function () {
            $('#shipping-fee').text('取得できませんでした');
          });
      };
      $zip.on('change keyup', function () {
        clearTimeout($zip.data('timer'));
        $zip.data('timer', setTimeout(lookup, 300));
      });
      lookup();
    }

    // image preview for upload fields
    $('input[type=file][data-preview]').on('change', function () {
      var target = $($(this).attr('data-preview'));
      if (this.files && this.files[0] && window.URL) {
        target.attr('src', window.URL.createObjectURL(this.files[0]));
      }
    });

    // auto dismiss success messages
    setTimeout(function () {
      $('.alert-success.auto-dismiss').fadeOut(400);
    }, 4000);
  });
})(jQuery);

document.querySelectorAll('.js-autosubmit').forEach(function (el) {
  el.addEventListener('change', function () { el.form.submit(); });
});
