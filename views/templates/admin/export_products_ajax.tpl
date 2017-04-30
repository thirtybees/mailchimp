{*
 * 2017 Thirty Bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 *  @author    Thirty Bees <modules@thirtybees.com>
 *  @copyright 2017 Thirty Bees
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}
<script type="text/javascript">
  (function() {
    $(document).ready(function() {
      var inProgress = false;

      var PRODUCT_COMPLETED = 1;
      var PRODUCT_STOP = 2;
      var PRODUCT_IN_PROGRESS = 3;

      var availableShops = [{foreach $availableShops as $idShop}{$idShop|intval},{/foreach}];
      var exportUrl = '{$exportUrl|escape:'javascript':'UTF-8'}';

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

      function exportProducts(elem, exportRemaining) {
        var idShop = parseInt(elem.attr('data-shop'), 10);
        productExportStatus(PRODUCT_IN_PROGRESS);

        $.get(exportUrl + '&ajax=true&action=exportAllProducts&shop=' + idShop +'&start' + (exportRemaining ? '&remaining' : ''), function (response) {
          response = JSON.parse(response);
          $('#export_products_total').html(response.totalProducts);
          $('#export_products_progressbar_done').width('0%');
          $('#export_products_progression_done').html(0);
          $('#export_products_current').html(0);

          inProgress = true;
          exportProductsNext(idShop, response.totalProducts, response.totalChunks, exportRemaining);
        });
      }

      function exportProductsNext(idShop, totalProducts, totalChunks, exportRemaining) {
        if (!inProgress) {
          return;
        }

        $.get(exportUrl + '&ajax&action=exportAllProducts&shop=' + idShop + '&next' + (exportRemaining ? '&remaining' : ''), function (response) {
          response = JSON.parse(response);
          var remaining = parseInt(response.remaining, 10);
          var processed = (totalChunks - remaining) * {MailChimp::EXPORT_CHUNK_SIZE|intval};
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
            return exportProductsNext(idShop, totalProducts, totalChunks, exportRemaining);
          }

          // finish
          productExportStatus(PRODUCT_COMPLETED);
        });
      }

      [].slice.call(document.querySelectorAll('.tabs')).forEach(function (el) {
        new ConfigTabs(el);
      });

      $.each(availableShops, function(index, idShop) {
        $('#sync-all-products-btn-' + idShop).click(function () {
          $('#exportProductsProgress').modal({
            backdrop: 'static',
            keyboard: false
          }).modal('show');
          exportProducts($(this), false);
        });

        $('#sync-remaining-products-btn-' + idShop).click(function () {
          $('#exportProductsProgress').modal({
            backdrop: 'static',
            keyboard: false
          }).modal('show');
          exportProducts($(this), true);
        });

        $('#reset-product-sync-data-btn-' + idShop).click(function () {
          $.get(exportUrl + '&ajax=true&action=resetProducts&shop=' + idShop, function (response) {
            if (response && JSON.parse(response).success) {
              alert('{l s='Product sync data has been reset' mod='mailchimp' js=1}');
            } else {
              alert('{l s='Unable to reset product sync data' mod='mailchimp' js=1}');
            }
          });
        });
      });

      $('#export_products_stop_button').click(function () {
        inProgress = false;
        productExportStatus(PRODUCT_STOP);
      });
    });
  })();
</script>
