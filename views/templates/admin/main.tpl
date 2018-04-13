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
<div class="tabs clearfix">
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
  (function () {
    function init() {
      if (typeof $ === 'undefined') {
        setTimeout(init, 100);
        return;
      }

      $('#mailchimp_shop_form').submit(function () {
        var losingData = false;
        $('#mailchimp_shop_form').find('[name^=shop_list_id]').each(function (index, item) {
          var $item = $(item);
          if ($item.data('default') != '0' && $item.data('default') != $item.val()) {
            losingData = true;
            return false;
          }
        });
        if (!losingData) {
          return true;
        }

        var self = this;
        swal({
          title: '{l s='Are you sure?' mod='mailchimp' js=1}',
          text: '{l s='When you switch lists, you might lose store data!' mod='mailchimp' js=1}',
          icon: 'warning',
          buttons: ['{l s='Cancel' mod='mailchimp' js=1}', '{l s='OK' mod='mailchimp' js=1}'],
          dangerMode: true,
        })
          .then(function (confirm) {
            if (confirm) {
              self.submit();
            }
          });

        return false;
      });
    }
    init();
  }());
</script>

{include file='./export_subscribers_ajax.tpl'}
{include file='./export_products_ajax.tpl'}
{include file='./export_carts_ajax.tpl'}
{include file='./export_orders_ajax.tpl'}
