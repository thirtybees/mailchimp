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

      var SUBSCRIBER_COMPLETED = 1;
      var SUBSCRIBER_STOP = 2;
      var SUBSCRIBER_IN_PROGRESS = 3;

      var availableShops = [{foreach $availableShops as $idShop}{$idShop|intval},{/foreach}];
      var exportUrl = '{$exportUrl|escape:'javascript':'UTF-8'}';

      function subscriberExportStatus(status) {
        switch (status) {
          case SUBSCRIBER_COMPLETED:
            $('#export_subscribers_stop').hide();
            $('#export_subscribers_progressing').hide();
            $('#export_subscribers_finished').show();
            $('#export_subscribers_stop_button').hide();
            $('#export_subscribers_close_button').show();
            break;
          case SUBSCRIBER_STOP:
            $('#export_subscribers_stop').show();
            $('#export_subscribers_progressing').hide();
            $('#export_subscribers_finished').hide();
            $('#export_subscribers_stop_button').hide();
            $('#export_subscribers_close_button').show();
            break;
          case SUBSCRIBER_IN_PROGRESS:
            $('#export_subscribers_stop').hide();
            $('#export_subscribers_progressing').show();
            $('#export_subscribers_finished').hide();
            $('#export_subscribers_stop_button').show();
            $('#export_subscribers_close_button').hide();
            break;
        }
      }

      function exportSubscribers(elem, exportRemaining) {
        var idShop = parseInt(elem.attr('data-shop'), 10);
        subscriberExportStatus(SUBSCRIBER_IN_PROGRESS);

        $.get(exportUrl + '&ajax=true&action=exportAllSubscribers&shop=' + idShop +'&start' + (exportRemaining ? '&remaining' : ''), function (response) {
          response = JSON.parse(response);
          $('#export_subscribers_total').html(response.totalSubscribers);
          $('#export_subscribers_progressbar_done').width('0%');
          $('#export_subscribers_progression_done').html(0);
          $('#export_subscribers_current').html(0);

          inProgress = true;
          exportSubscribersNext(idShop, response.totalSubscribers, response.totalChunks, exportRemaining);
        });
      }

      function exportSubscribersNext(idShop, totalSubscribers, totalChunks, exportRemaining) {
        if (!inProgress) {
          return;
        }

        $.get(exportUrl + '&ajax&action=exportAllSubscribers&shop=' + idShop + '&next' + (exportRemaining ? '&remaining' : ''), function (response) {
          response = JSON.parse(response);
          var remaining = parseInt(response.remaining, 10);
          var processed = (totalChunks - remaining) * {MailChimp::EXPORT_CHUNK_SIZE|intval};
          var progress = (processed / totalSubscribers) * 100;

          // check max
          if (processed > totalSubscribers) {
            processed = totalSubscribers;


          }
          if (progress > 100) {
            progress = 100;

            inProgress = false;
          }

          $('#export_subscribers_progressbar_done').width(parseInt(progress, 10) + '%');
          $('#export_subscribers_progression_done').html(parseInt(progress, 10));
          $('#export_subscribers_current').html(parseInt(processed, 10));

          if (response.remaining && inProgress) {
            return exportSubscribersNext(idShop, totalSubscribers, totalChunks, exportRemaining);
          }

          // finish
          subscriberExportStatus(SUBSCRIBER_COMPLETED);
        });
      }

      [].slice.call(document.querySelectorAll('.tabs')).forEach(function (el) {
        new ConfigTabs(el);
      });

      $.each(availableShops, function(index, idShop) {
        $('#sync-all-subscribers-btn-' + idShop).click(function () {
          $('#exportSubscribersProgress').modal({
            backdrop: 'static',
            keyboard: false
          }).modal('show');
          exportSubscribers($(this), false);
        });

        $('#sync-remaining-subscribers-btn-' + idShop).click(function () {
          $('#exportSubscribersProgress').modal({
            backdrop: 'static',
            keyboard: false
          }).modal('show');
          exportSubscribers($(this), true);
        });

        $('#reset-subscriber-sync-data-btn-' + idShop).click(function () {
          $.get(exportUrl + '&ajax=true&action=resetSubscribers&shop=' + idShop, function (response) {
            if (response && JSON.parse(response).success) {
              swal({
                icon: 'success',
                text: '{l s='Subscriber sync data has been reset' mod='mailchimp' js=1}',
              });
            } else {
              swal({
                icon: 'error',
                text: '{l s='Unable to reset subscriber sync data' mod='mailchimp' js=1}',
              });
            }
          });
        });
      });

      $('#export_subscribers_stop_button').click(function () {
        inProgress = false;
        subscriberExportStatus(SUBSCRIBER_STOP);
      });
    });
  })();
</script>
