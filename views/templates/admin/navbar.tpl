{*
 * 2016 Michael Dekker
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@michaeldekker.com so we can send you a copy immediately.
 *
 *  @author    Michael Dekker <prestashop@michaeldekker.com>
 *  @copyright 2016 Michael Dekker
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}
{if $smarty.const._PS_VERSION_|@addcslashes:'\'' < '1.6'}
	<table class="table" cellpadding="0" cellspacing="0" style="margin:auto;text-align:center">
		<tbody>
			<tr>
				{if isset($menutabs)}
					{foreach from=$menutabs item=tab}
						<th>
							<a id="{$tab.short|escape:'htmlall':'UTF-8'}" href="{$tab.href|escape:'htmlall':'UTF-8'}" {if $tab.active}style="color:black"{else}style="color:grey"{/if}>
								{$tab.desc|escape:'htmlall':'UTF-8'}
							</a>
						</th>
					{/foreach}
				{/if}
			</tr>
		</tbody>
	</table>
	<br />
{else}
	<nav class="navbar navbar-default" role="navigation">
		<ul class="nav navbar-nav">
			{if isset($menutabs)}
				{foreach from=$menutabs item=tab}
					<li class="{if $tab.active}active{/if}">
						<a id="{$tab.short|escape:'htmlall':'UTF-8'}" href="{$tab.href|escape:'htmlall':'UTF-8'}">
							<span class="icon {$tab.icon|escape:'htmlall':'UTF-8'}"></span>
							{$tab.desc|escape:'htmlall':'UTF-8'}
						</a>
					</li>
				{/foreach}
			{/if}
		</ul>
	</nav>
{/if}
