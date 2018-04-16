{*
 * 2018 thirty bees
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
 *  @author    thirty bees <modules@thirtybees.com>
 *  @copyright 2017-2018 thirty bees
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

        var jqXhr = $.get(exportUrl + '&ajax=true&action=exportAllProducts&shops[]=' + idShop +'&start' + (exportRemaining ? '&remaining' : ''), function (response) {
          response = JSON.parse(response);
          $('#export_products_total').html(response.totalProducts);
          $('#export_products_progressbar_done').width('0%');
          $('#export_products_progression_done').html(0);
          $('#export_products_current').html(0);

          inProgress = true;
          exportProductsNext(idShop, response.totalProducts, response.totalChunks, exportRemaining);
        }).fail(function () {
          inProgress = true;
          exportProductsNext(idShop, totalProducts, totalChunks, exportRemaining);
        }).fail(function () {
          if (jqXhr.status === 504) {
            swal({
              icon: 'error',
              text: '{l s='Server timed out. Please try again when it is less busy, try the CLI method or upgrade your server to solve the problem.' mod='mailchimp'}'
            });
          } else {
            swal({
              icon: 'error',
              text: '{l s='An error occurred. Check your server logs for the actual error message.' mod='mailchimp'}'
            });
          }
        });
      }

      function exportProductsNext(idShop, totalProducts, totalChunks, exportRemaining) {
        if (!inProgress) {
          return;
        }

        var jqXhr = $.get(exportUrl + '&ajax&action=exportAllProducts&shops[]=' + idShop + '&next' + (exportRemaining ? '&remaining' : ''), function (response) {
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
        }).fail(function () {
          if (jqXhr.status === 504) {
            swal({
              icon: 'error',
              text: '{l s='Server timed out. Please try again when it is less busy, try the CLI method or upgrade your server to solve the problem.' mod='mailchimp'}'
            });
          } else {
            swal({
              icon: 'error',
              text: '{l s='An error occurred. Check your server logs for the actual error message.' mod='mailchimp'}'
            });
          }
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
          var jqXhr = $.get(exportUrl + '&ajax=true&action=resetProducts&shops[]=' + idShop, function (response) {
            if (response && JSON.parse(response).success) {
              swal({
                icon: 'success',
                text: '{l s='Product sync data has been reset' mod='mailchimp' js=1}',
              });
            } else {

              swal({
                icon: 'error',
                text: '{l s='Unable to reset product sync data' mod='mailchimp' js=1}',
              });
            }
          }).fail(function () {
            if (jqXhr.status === 504) {
              swal({
                icon: 'error',
                text: '{l s='Server timed out. Please try again when it is less busy, try the CLI method or upgrade your server to solve the problem.' mod='mailchimp'}'
              });
            } else {
              swal({
                icon: 'error',
                text: '{l s='An error occurred. Check your server logs for the actual error message.' mod='mailchimp'}'
              });
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
