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
{if $context_shop}
  &nbsp;<div class="badge">{l s='Configuring for shop %s' sprintf=[$context_shop]}</div>
{else}
  &nbsp;<div class="badge">{l s='Configuring for all shops' mod='mailchimp'}</div>
{/if}
