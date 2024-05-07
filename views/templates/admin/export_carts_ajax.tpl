{*
 * 2018 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 *  @author    thirty bees <modules@thirtybees.com>
 *  @copyright 2017-2024 thirty bees
 *  @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}
<script type="text/javascript">
  (function() {
    $(document).ready(function() {
      var inProgress = false;

      var CART_COMPLETED = 1;
      var CART_STOP = 2;
      var CART_IN_PROGRESS = 3;

      var availableShops = [{foreach $availableShops as $idShop}{$idShop|intval},{/foreach}];
      var exportUrl = '{$exportUrl|escape:'javascript':'UTF-8'}';

      function cartExportStatus(status) {
        switch (status) {
          case CART_COMPLETED:
            $('#export_carts_stop').hide();
            $('#export_carts_progressing').hide();
            $('#export_carts_finished').show();
            $('#export_carts_stop_button').hide();
            $('#export_carts_close_button').show();
            break;
          case CART_STOP:
            $('#export_carts_stop').show();
            $('#export_carts_progressing').hide();
            $('#export_carts_finished').hide();
            $('#export_carts_stop_button').hide();
            $('#export_carts_close_button').show();
            break;
          case CART_IN_PROGRESS:
            $('#export_carts_stop').hide();
            $('#export_carts_progressing').show();
            $('#export_carts_finished').hide();
            $('#export_carts_stop_button').show();
            $('#export_carts_close_button').hide();
            break;
        }
      }

      function exportAllCarts(elem, exportRemaining) {
        var idShop = parseInt(elem.attr('data-shop'), 10);
        cartExportStatus(CART_IN_PROGRESS);

        var jqXhr = $.get(exportUrl + '&ajax=true&action=exportAllCarts&shop' + idShop +'&start' + (exportRemaining ? '&remaining' : ''), function (response) {
          response = JSON.parse(response);
          $('#export_carts_total').html(response.totalCarts);
          $('#export_carts_progressbar_done').width('0%');
          $('#export_carts_progression_done').html(0);
          $('#export_carts_current').html(0);

          inProgress = true;
          exportAllCartsNext(idShop, response.totalCarts, response.totalChunks, exportRemaining, 1);
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

      function exportAllCartsNext(idShop, totalCarts, totalChunks, exportRemaining, count) {
        if (!inProgress) {
          return;
        }

        var jqXhr = $.get(exportUrl + '&ajax&action=exportAllCarts&count=' + count + '&shop' + idShop +'&next' + (exportRemaining ? '&remaining' : ''), function (response) {
          response = JSON.parse(response);
          var processed = parseInt(response.count, 10) * {MailChimp::EXPORT_CHUNK_SIZE|intval};
          var progress = (processed / totalCarts) * 100;

          // check max
          if (processed > totalCarts) {
            processed = totalCarts;


          }
          if (progress > 100) {
            progress = 100;

            inProgress = false;
          }

          $('#export_carts_progressbar_done').width(parseInt(progress, 10) + '%');
          $('#export_carts_progression_done').html(parseInt(progress, 10));
          $('#export_carts_current').html(parseInt(processed, 10));

          if (response.count && inProgress) {
            return exportAllCartsNext(idShop, totalCarts, totalChunks, exportRemaining, response.count);
          }

          // finish
          cartExportStatus(CART_COMPLETED);
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

      $.each(availableShops, function (index, idShop) {
        $('#sync-all-carts-btn-' + idShop).click(function () {
          $('#exportCartsProgress').modal({
            backdrop: 'static',
            keyboard: false
          }).modal('show');
          exportAllCarts($(this), false);
        });

        $('#sync-remaining-carts-btn-' + idShop).click(function () {
          $('#exportCartsProgress').modal({
            backdrop: 'static',
            keyboard: false
          }).modal('show');
          exportAllCarts($(this), true);
        });

        $('#reset-cart-sync-data-btn-' + idShop).click(function () {
          var jqXhr = $.get(exportUrl + '&ajax=true&action=resetCarts&shops[]=' + idShop, function (response) {
            if (response && JSON.parse(response).success) {
              swal({
                icon: 'success',
                text: '{l s='Cart sync data has been reset' mod='mailchimp' js=1}',
              });
            } else {
              swal({
                icon: 'error',
                text: '{l s='Unable to reset cart sync data' mod='mailchimp' js=1}'
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

      $('#export_carts_stop_button').click(function () {
        inProgress = false;
        cartExportStatus(CART_STOP);
      });
    });
  })();
</script>
