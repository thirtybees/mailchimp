{*
 *
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
 *
*}
{extends file="helpers/form/form.tpl"}

{block name="input"}
  {if $input.type == 'mailchimp_shops'}
    <div class="row">
      <div class="row table-responsive clearfix ">
        <div>
          <table class="table">
            <thead>
              <tr>
                <th>
                  <span class="title_box">{l s='Shop ID' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='Name' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='List' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='Tax rule' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='Synced' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='Subscribers' mod='mailchimp'}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {foreach from=$input['shops'] item=shop}
                <tr>
                  <td>
                    <span>{$shop['id_shop']|intval}</span>
                  </td>
                  <td>
                    <span>{$shop['name']|escape:'htmlall':'UTF-8'}</span>
                  </td>
                  <td>
                    <select name="shop_list_id[{$shop['id_shop']|intval}]" data-default="{$shop['list_id']|escape:'html'}">
                      {foreach $input['lists'] as $value => $name}
                        <option value="{$value|escape:'html'}"{if $value == $shop['list_id']} selected="selected"{/if}>{$name|escape:'html'}</option>
                      {/foreach}
                    </select>
                  </td>
                  <td>
                    {html_options name="shop_tax[{$shop['id_shop']}]" options=$input['taxes'] selected=$shop['id_tax']}
                  </td>
                  <td>
                    {if $shop['synced']}YES{else}NO{/if}
                  </td>
                  <td>
                    {if $shop['list_id']}
                    <div class="btn btn-default" data-shop="{$shop['id_shop']|intval}" id="sync-all-subscribers-btn-{$shop['id_shop']|intval}">
                      <i class="icon icon-upload"></i>
                      {l s='Sync all subscribers'}</div>{else}{l s='N/A' mod='mailchimp'}{/if}
                  </td>
                </tr>
              {/foreach}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  {elseif $input.type == 'mailchimp_products'}
    <div class="row">
      <div class="row table-responsive clearfix ">
        <div>
          <table class="table">
            <thead>
              <tr>
                <th>
                  <span class="title_box">{l s='Shop ID' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='Name' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='List' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='Sync remaining products' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='Sync all products' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='Reset' mod='mailchimp'}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {foreach from=$input['shops'] item=shop}
                <tr>
                  <td>
                    <span>{$shop['id_shop']|intval}</span>
                  </td>
                  <td>
                    <span>{$shop['name']|escape:'htmlall':'UTF-8'}</span>
                  </td>
                  <td>
                    <span>{if $shop['list_id']}{$shop['list_id']|escape:'htmlall':'UTF-8'}{else}{l s='N/A' mod='mailchimp'}{/if}</span>
                  </td>
                  <td>
                    {if $shop['list_id']}
                    <div class="btn btn-default" data-shop="{$shop['id_shop']|intval}" id="sync-remaining-products-btn-{$shop['id_shop']|intval}">
                      <i class="icon icon-upload"></i>
                      {l s='Upload remaining products'}</div>{else}{l s='N/A' mod='mailchimp'}{/if}
                  </td>
                  <td>
                    {if $shop['list_id']}
                    <div class="btn btn-default" data-shop="{$shop['id_shop']|intval}" id="sync-all-products-btn-{$shop['id_shop']|intval}">
                      <i class="icon icon-upload"></i>
                      {l s='Upload all products'}</div>{else}{l s='N/A' mod='mailchimp'}{/if}
                  </td>
                  <td>
                    {if $shop['list_id']}
                    <div class="btn btn-default" data-shop="{$shop['id_shop']|intval}" id="reset-product-sync-data-btn-{$shop['id_shop']|intval}">
                      <i class="icon icon-refresh"></i>
                      {l s='Reset product sync data' mod='mailchimp'}</div>{else}{l s='N/A' mod='mailchimp'}{/if}
                  </td>
                </tr>
              {/foreach}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  {elseif $input.type == 'mailchimp_carts'}
    <div class="row">
      <div class="row table-responsive clearfix ">
        <div>
          <table class="table">
            <thead>
              <tr>
                <th>
                  <span class="title_box">{l s='Shop ID' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='Name' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='List' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='Sync remaining carts' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='Sync all carts' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='Reset' mod='mailchimp'}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {foreach from=$input['shops'] item=shop}
                <tr>
                  <td>
                    <span>{$shop['id_shop']|intval}</span>
                  </td>
                  <td>
                    <span>{$shop['name']|escape:'htmlall':'UTF-8'}</span>
                  </td>
                  <td>
                    <span>{if $shop['list_id']}{$shop['list_id']|escape:'htmlall':'UTF-8'}{else}{l s='N/A' mod='mailchimp'}{/if}</span>
                  </td>
                  <td>
                    {if $shop['list_id']}
                    <div class="btn btn-default" data-shop="{$shop['id_shop']|intval}" id="sync-remaining-carts-btn-{$shop['id_shop']|intval}">
                      <i class="icon icon-upload"></i>
                      {l s='Upload remaining carts' mod='mailchimp'}</div>{else}{l s='N/A' mod='mailchimp'}{/if}
                  </td>
                  <td>
                    {if $shop['list_id']}
                      <div class="btn btn-default" data-shop="{$shop['id_shop']|intval}" id="sync-all-carts-btn-{$shop['id_shop']|intval}">
                        <i class="icon icon-upload"></i>
                        {l s='Upload all carts' mod='mailchimp'}</div>
                    {else}
                      {l s='N/A' mod='mailchimp'}
                    {/if}
                  </td>
                  <td>
                    {if $shop['list_id']}
                    <div class="btn btn-default" data-shop="{$shop['id_shop']|intval}" id="reset-cart-sync-data-btn-{$shop['id_shop']|intval}">
                      <i class="icon icon-refresh"></i>
                      {l s='Reset cart sync data' mod='mailchimp'}</div>{else}{l s='N/A' mod='mailchimp'}{/if}
                  </td>
                </tr>
              {/foreach}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  {elseif $input.type == 'mailchimp_orders'}
    <div class="row">
      <div class="row table-responsive clearfix ">
        <div>
          <table class="table">
            <thead>
              <tr>
                <th>
                  <span class="title_box">{l s='Shop ID' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='Name' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='List' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='Sync remaining orders' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='Sync all orders' mod='mailchimp'}</span>
                </th>
                <th>
                  <span class="title_box">{l s='Reset' mod='mailchimp'}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {foreach from=$input['shops'] item=shop}
                <tr>
                  <td>
                    <span>{$shop['id_shop']|intval}</span>
                  </td>
                  <td>
                    <span>{$shop['name']|escape:'htmlall':'UTF-8'}</span>
                  </td>
                  <td>
                    <span>{if $shop['list_id']}{$shop['list_id']|escape:'htmlall':'UTF-8'}{else}{l s='N/A' mod='mailchimp'}{/if}</span>
                  </td>
                  <td>
                    {if $shop['list_id']}
                    <div class="btn btn-default" data-shop="{$shop['id_shop']|intval}" id="sync-remaining-orders-btn-{$shop['id_shop']|intval}">
                      <i class="icon icon-upload"></i>
                      {l s='Upload remaining orders' mod='mailchimp'}</div>{else}{l s='N/A' mod='mailchimp'}{/if}
                  </td>
                  <td>
                    {if $shop['list_id']}
                    <div class="btn btn-default" data-shop="{$shop['id_shop']|intval}" id="sync-all-orders-btn-{$shop['id_shop']|intval}">
                      <i class="icon icon-upload"></i>
                      {l s='Upload all orders' mod='mailchimp'}</div>{else}{l s='N/A' mod='mailchimp'}{/if}
                  </td>
                  <td>
                    {if $shop['list_id']}
                    <div class="btn btn-default" data-shop="{$shop['id_shop']|intval}" id="reset-order-sync-data-btn-{$shop['id_shop']|intval}">
                      <i class="icon icon-refresh"></i>
                      {l s='Reset order sync data' mod='mailchimp'}</div>{else}{l s='N/A' mod='mailchimp'}{/if}
                  </td>
                </tr>
              {/foreach}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  {else}
    {$smarty.block.parent}
  {/if}
{/block}
