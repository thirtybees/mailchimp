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
<div class="modal-body">
  <div class="alert alert-warning" id="export_orders_stop" style="display:none;">
    {l s='Aborting. Please wait...' mod='mailchimp'}
  </div>
  <p id="export_orders_details_progressing">
    {l s='Exporting your orders...' mod='mailchimp'} <br/>
    <strong><span id="export_orders_current">0</span> / <span id="export_orders_total">0</span></strong>
  </p>
  <div class="alert alert-success" id="export_orders_finished" style="display:none;">
    {l s='Orders exported!' mod='mailchimp'}
  </div>
  <div id="export_orders_progress_div">
    <div class="pull-right" id="export_orders_progression_details" default-value="{l s='Exporting orders...' mod='mailchimp'}">
      &nbsp;
    </div>
    <div class="progress active progress-striped" style="display: block; width: 100%">
      <div class="progress-bar progress-bar-success" role="progressbar" style="width: 0%" id="export_orders_progressbar_done">
        <span><span id="export_orders_progression_done">0</span>% {l s='Exported' mod='mailchimp'}</span>
      </div>
    </div>
  </div>

  <div class="modal-footer">
    <div class="input-group pull-right">
      <button type="button" class="btn btn-default" tabindex="-1" id="export_orders_stop_button">
        {l s='Abort export' mod='mailchimp'}
      </button>
      &nbsp;
      <button type="button" class="btn btn-success" data-dismiss="modal" tabindex="-1" id="export_orders_close_button" style="display: none;">
        {l s='Close' mod='mailchimp'}
      </button>
    </div>
  </div>
</div>
