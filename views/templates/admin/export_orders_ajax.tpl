<script type="text/javascript">
  (function() {
    $(document).ready(function() {
      var inProgress = false;

      var ORDER_COMPLETED = 1;
      var ORDER_STOP = 2;
      var ORDER_IN_PROGRESS = 3;

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

      function exportAllOrders(elem, remaining) {
        var idShop = parseInt(elem.attr('data-shop'), 10);
        orderExportStatus(ORDER_IN_PROGRESS);

        $.get(exportUrl + '&ajax=true&action=exportAllOrders&shop=' + idShop +'&start' + (remaining ? '&remaining' : ''), function (response) {
          response = JSON.parse(response);
          $('#export_orders_total').html(response.totalOrders);
          $('#export_orders_progressbar_done').width('0%');
          $('#export_orders_progression_done').html(0);
          $('#export_orders_current').html(0);

          inProgress = true;
          exportAllOrdersNext(idShop, response.totalOrders, response.totalChunks, remaining);
        });
      }

      function exportAllOrdersNext(idShop, totalOrders, totalChunks, remaining) {
        if (!inProgress) {
          return;
        }

        $.get(exportUrl + '&ajax&action=exportAllOrders&shop=' + idShop +'&next' + (remaining ? '&remaining' : ''), function (response) {
          response = JSON.parse(response);
          var remaining = parseInt(response.remaining, 10);
          var processed = (totalChunks - remaining) * 1000;
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
            return exportAllOrdersNext(idShop, totalOrders, totalChunks, remaining);
          }

          // finish
          orderExportStatus(ORDER_COMPLETED);
        });
      }

      for (var i = 0; i < availableShops.length; i++) {
        $('#sync-all-orders-btn-' + availableShops[i]).click(function () {
          $('#exportOrdersProgress').modal('show');
          exportAllOrders($(this), false);
        });

        $('#sync-remaining-orders-btn-' + availableShops[i]).click(function () {
          $('#exportOrdersProgress').modal('show');
          exportAllOrders($(this), true);
        });
      }

      $('#export_orders_stop_button').click(function () {
        inProgress = false;
        orderExportStatus(ORDER_STOP);
      });
    });
  })();
</script>
