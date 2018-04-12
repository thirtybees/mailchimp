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

      var ORDER_COMPLETED = 1;
      var ORDER_STOP = 2;
      var ORDER_IN_PROGRESS = 3;

      var availableShops = [{foreach $availableShops as $idShop}{$idShop|intval},{/foreach}];
      var exportUrl = '{$exportUrl|escape:'javascript':'UTF-8'}';

      function orderExportStatus(status) {
        switch (status) {
          case ORDER_COMPLETED:
            $('#export_orders_stop').hide();
            $('#export_orders_progressing').hide();
            $('#export_orders_finished').show();
            $('#export_orders_stop_button').hide();
            $('#export_orders_close_button').show();
            break;
          case ORDER_STOP:
            $('#export_orders_stop').show();
            $('#export_orders_progressing').hide();
            $('#export_orders_finished').hide();
            $('#export_orders_stop_button').hide();
            $('#export_orders_close_button').show();
            break;
          case ORDER_IN_PROGRESS:
            $('#export_orders_stop').hide();
            $('#export_orders_progressing').show();
            $('#export_orders_finished').hide();
            $('#export_orders_stop_button').show();
            $('#export_orders_close_button').hide();
            break;
        }
      }

      function exportAllOrders(elem, exportRemaining) {
        var idShop = parseInt(elem.attr('data-shop'), 10);
        orderExportStatus(ORDER_IN_PROGRESS);

        $.get(exportUrl + '&ajax=true&action=exportAllOrders&shop=' + idShop +'&start' + (exportRemaining ? '&remaining' : ''), function (response) {
          response = JSON.parse(response);
          $('#export_orders_total').html(response.totalOrders);
          $('#export_orders_progressbar_done').width('0%');
          $('#export_orders_progression_done').html(0);
          $('#export_orders_current').html(0);

          inProgress = true;
          exportAllOrdersNext(idShop, response.totalOrders, response.totalChunks, exportRemaining);
        });
      }

      function exportAllOrdersNext(idShop, totalOrders, totalChunks, exportRemaining) {
        if (!inProgress) {
          return;
        }

        $.get(exportUrl + '&ajax&action=exportAllOrders&shop=' + idShop +'&next' + (exportRemaining ? '&remaining' : ''), function (response) {
          response = JSON.parse(response);
          var remaining = parseInt(response.remaining, 10);
          var processed = (totalChunks - remaining) * {MailChimp::EXPORT_CHUNK_SIZE|intval};
          var progress = (processed / totalOrders) * 100;

          // check max
          if (processed > totalOrders) {
            processed = totalOrders;


          }
          if (progress > 100) {
            progress = 100;

            inProgress = false;
          }

          $('#export_orders_progressbar_done').width(parseInt(progress, 10) + '%');
          $('#export_orders_progression_done').html(parseInt(progress, 10));
          $('#export_orders_current').html(parseInt(processed, 10));

          if (response.remaining && inProgress) {
            return exportAllOrdersNext(idShop, totalOrders, totalChunks, exportRemaining);
          }

          // finish
          orderExportStatus(ORDER_COMPLETED);
        });
      }

      $.each(availableShops, function (index, idShop) {
        $('#sync-all-orders-btn-' + idShop).click(function () {
          $('#exportOrdersProgress').modal({
            backdrop: 'static',
            keyboard: false
          }).modal('show');

          exportAllOrders($(this), false);
        });

        $('#sync-remaining-orders-btn-' + idShop).click(function () {
          $('#exportOrdersProgress').modal({
            backdrop: 'static',
            keyboard: false
          }).modal('show');
          exportAllOrders($(this), true);
        });

        $('#reset-order-sync-data-btn-' + idShop).click(function () {
          $.get(exportUrl + '&ajax=true&action=resetOrders&shop=' + idShop, function (response) {
            if (response && JSON.parse(response).success) {
              swal({
                icon: 'success',
                text: '{l s='Order sync data has been reset' mod='mailchimp' js=1}',
              });
            } else {
              swal({
                icon: 'error',
                text: '{l s='Unable to reset order sync data' mod='mailchimp' js=1}',
              });
            }
          });
        });
      });

      $('#export_orders_stop_button').click(function () {
        inProgress = false;
        orderExportStatus(ORDER_STOP);
      });
    });
  })();
</script>
