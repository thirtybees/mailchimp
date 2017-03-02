<script type="text/javascript">
  (function() {
    $(document).ready(function() {
      var inProgress = false;

      var PRODUCT_COMPLETED = 1;
      var PRODUCT_STOP = 2;
      var PRODUCT_IN_PROGRESS = 3;

      function productExportStatus(status) {
        switch (status) {
          case PRODUCT_COMPLETED:
            $('#export_products_stop').hide();
            $('#export_products_progressing').hide();
            $('#export_products_finished').show();
            $('#export_products_stop_button').hide();
            $('#export_products_close_button').show();
            break;
          case PRODUCT_STOP:
            $('#export_products_stop').show();
            $('#export_products_progressing').hide();
            $('#export_products_finished').hide();
            $('#export_products_stop_button').hide();
            $('#export_products_close_button').show();
            break;
          case PRODUCT_IN_PROGRESS:
            $('#export_products_stop').hide();
            $('#export_products_progressing').show();
            $('#export_products_finished').hide();
            $('#export_products_stop_button').show();
            $('#export_products_close_button').hide();
            break;
        }
      }

      function exportProducts(elem, remaining) {
        var idShop = parseInt(elem.attr('data-shop'), 10);
        productExportStatus(PRODUCT_IN_PROGRESS);

        $.get(exportUrl + '&ajax=true&action=exportAllProducts&shop=' + idShop +'&start' + (remaining ? '&remaining' : ''), function (response) {
          response = JSON.parse(response);
          $('#export_products_total').html(response.totalProducts);
          $('#export_products_progressbar_done').width('0%');
          $('#export_products_progression_done').html(0);
          $('#export_products_current').html(0);

          inProgress = true;
          exportProductsNext(idShop, response.totalProducts, response.totalChunks, remaining);
        });
      }

      function exportProductsNext(idShop, totalProducts, totalChunks, remaining) {
        if (!inProgress) {
          return;
        }

        $.get(exportUrl + '&ajax&action=exportAllProducts&shop=' + idShop + '&next' + (remaining ? '&remaining' : ''), function (response) {
          response = JSON.parse(response);
          var remaining = parseInt(response.remaining, 10);
          var processed = (totalChunks - remaining) * 1000;
          var progress = (processed / totalProducts) * 100;

          // check max
          if (processed > totalProducts) {
            processed = totalProducts;


          }
          if (progress > 100) {
            progress = 100;

            inProgress = false;
          }

          $('#export_products_progressbar_done').width(parseInt(progress, 10) + '%');
          $('#export_products_progression_done').html(parseInt(progress, 10));
          $('#export_products_current').html(parseInt(processed, 10));

          if (response.remaining && inProgress) {
            return exportProductsNext(idShop, totalProducts, totalChunks, remaining);
          }

          // finish
          productExportStatus(PRODUCT_COMPLETED);
        });
      }

      [].slice.call(document.querySelectorAll('.tabs')).forEach(function (el) {
        new ConfigTabs(el);
      });

      availableShops = [{foreach $availableShops as $idShop}{$idShop|intval},{/foreach}];
      exportUrl = '{$exportUrl|escape:'javascript':'UTF-8'}';

      for (var i = 0; i < availableShops.length; i++) {
        $('#sync-all-products-btn-' + availableShops[i]).click(function () {
          $('#exportProductsProgress').modal('show');
          exportProducts($(this), false);
        });
        $('#sync-remaining-products-btn-' + availableShops[i]).click(function () {
          $('#exportProductsProgress').modal('show');
          exportProducts($(this), true);
        });
      }

      $('#export_products_stop_button').click(function () {
        inProgress = false;
        productExportStatus(PRODUCT_STOP);
      });
    });
  })();
</script>
