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
 *  @copyright 2017-2021 thirty bees
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}
<a class="list-action-enable action-{if $tr['mailchimp_enabled'] === 'true'}enabled{else}disabled{/if}"
   href="index.php?controller=AdminCartRules&amp;id_cart_rule={$tr['id_cart_rule']|intval}&amp;statusmailchimp_promo&amp;token={$token|escape:'htmlall'}"
   title="{if $tr['mailchimp_enabled'] === 'true'}{l s='Enabled' mod='mailchimp'}{else}{l s='Disabled' mod='mailchimp'}{/if}"
>
  <i class="icon-check{if $tr['mailchimp_enabled'] !== 'true'} hidden{/if}"></i>
  <i class="icon-remove{if $tr['mailchimp_enabled'] === 'true'} hidden{/if}"></i>
</a>
