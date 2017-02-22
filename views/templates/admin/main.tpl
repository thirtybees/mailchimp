<div class="tabs">
	<div class="sidebar navigation col-md-2">
		{if isset($tab_contents.logo)}
			<img class="tabs-logo" src="{$tab_contents.logo|escape:'htmlall':'UTF-8'}"/>
		{/if}
		<nav class="list-group category-list">
			{foreach from=$tab_contents.contents key=tab_nbr item=content}
				<a class="list-group-item migration-tab"
				   href="#mailchimp_tab_{$tab_nbr + 1|intval}">

					{if isset($content.icon) && $content.icon != false}
						<i class="{$content.icon|escape:"htmlall":"UTF-8"} pstab-icon"></i>
					{/if}

					{$content.name|escape:"htmlall":"UTF-8"}

					{if isset($content.badge) && $content.badge != false}
						<span class="badge-module-tabs pull-right {$content.badge|escape:'htmlall':'UTF-8'}"></span>
					{/if}
				</a>
			{/foreach}
		</nav>
	</div>

	<div class="col-md-10">
		<div class="content-wrap panel">
			{foreach from=$tab_contents.contents key=tab_nbr item=content}
				<section id="section-shape-{$tab_nbr + 1|intval}">{$content.value|escape:'UTF-8'}</section>
			{/foreach}
		</div>
	</div>

</div>
<script type="text/javascript" src="{$new_base_dir|escape:'htmlall':'UTF-8'}views/js/configtabs.js"></script>
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

      function exportAllProducts(idShop) {
        productExportStatus(PRODUCT_IN_PROGRESS);

        $.get(exportUrl + '&ajax=true&action=exportAllProducts&start', function (response) {
          response = JSON.parse(response);
          $('#export_products_total').html(response.totalProducts);
          $('#export_products_progressbar_done').width('0%');
          $('#export_products_progression_done').html(0);
          $('#export_products_current').html(0);

          inProgress = true;
          exportAllProductsNext(response.totalProducts, response.totalChunks);
        });
      }

      function exportAllProductsNext(totalProducts, totalChunks) {
        if (!inProgress) {
          return;
        }

        $.get(exportUrl + '&ajax&action=exportAllProducts&next', function (response) {
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
            return exportAllProductsNext(totalProducts, totalChunks);
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
          var idShop = availableShops[i];
          exportAllProducts(idShop);
        });
      }

      $('#export_products_stop_button').click(function () {
        inProgress = false;
        productExportStatus(PRODUCT_STOP);
      });
    });
  })();
</script>

<script type="text/javascript">
  (function() {
    $(document).ready(function() {
      var inProgress = false;

      var CART_COMPLETED = 1;
      var CART_STOP = 2;
      var CART_IN_PROGRESS = 3;

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

      function exportAllCarts(idShop) {
        cartExportStatus(CART_IN_PROGRESS);

        $.get(exportUrl + '&ajax=true&action=exportAllCarts&start', function (response) {
          response = JSON.parse(response);
          $('#export_carts_total').html(response.totalCarts);
          $('#export_carts_progressbar_done').width('0%');
          $('#export_carts_progression_done').html(0);
          $('#export_carts_current').html(0);

          inProgress = true;
          exportAllCartsNext(response.totalCarts, response.totalChunks);
        });
      }

      function exportAllCartsNext(totalCarts, totalChunks) {
        if (!inProgress) {
          return;
        }

        $.get(exportUrl + '&ajax&action=exportAllCarts&next', function (response) {
          response = JSON.parse(response);
          var remaining = parseInt(response.remaining, 10);
          var processed = (totalChunks - remaining) * 1000;
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

          if (response.remaining && inProgress) {
            return exportAllCartsNext(totalCarts, totalChunks);
          }

          // finish
          cartExportStatus(CART_COMPLETED);
        });
      }

      for (var i = 0; i < availableShops.length; i++) {
        $('#sync-all-carts-btn-' + availableShops[i]).click(function () {
          $('#exportCartsProgress').modal('show');
          var idShop = availableShops[i];
          exportAllCarts(idShop);
        });
      }

      $('#export_carts_stop_button').click(function () {
        inProgress = false;
        cartExportStatus(CART_STOP);
      });
    });
  })();
</script>


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

      function exportAllOrders(idShop) {
        orderExportStatus(ORDER_IN_PROGRESS);

        $.get(exportUrl + '&ajax=true&action=exportAllOrders&start', function (response) {
          response = JSON.parse(response);
          $('#export_orders_total').html(response.totalOrders);
          $('#export_orders_progressbar_done').width('0%');
          $('#export_orders_progression_done').html(0);
          $('#export_orders_current').html(0);

          inProgress = true;
          exportAllOrdersNext(response.totalOrders, response.totalChunks);
        });
      }

      function exportAllOrdersNext(totalOrders, totalChunks) {
        if (!inProgress) {
          return;
        }

        $.get(exportUrl + '&ajax&action=exportAllOrders&next', function (response) {
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
            return exportAllOrdersNext(totalOrders, totalChunks);
          }

          // finish
          orderExportStatus(ORDER_COMPLETED);
        });
      }

      for (var i = 0; i < availableShops.length; i++) {
        $('#sync-all-orders-btn-' + availableShops[i]).click(function () {
          $('#exportOrdersProgress').modal('show');
          var idShop = availableShops[i];
          exportAllOrders(idShop);
        });
      }

      $('#export_orders_stop_button').click(function () {
        inProgress = false;
        orderExportStatus(ORDER_STOP);
      });
    });
  })();
</script>
