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
	<fieldset>
		<legend>{l s='Stripe' mod='mdstripe'}</legend>
		<strong>{l s='Accept payments with Stripe' mod='mdstripe'}</strong>
		<p>
			{l s='Thank you for using this module!' mod='mdstripe'}
		</p>
		<strong>{l s='Quick start' mod='mdstripe'}</strong>
		<ol>
			<li>{l s='Visit' mod='mdstripe'} <a href="https://stripe.com/">https://stripe.com/</a> {l s='and find your API keys.' mod='mdstripe'}</li>
			<li>{l s='Enter your keys on this page' mod='mdstripe'}</li>
			<li>{l s='Optionally configure the webhooks and repeat this process for every store if you have multistore enabled' mod='mdstripe'}</li>
			<li>
				{l s='You are good to go! Should you find any problems, please check out the' mod='mdstripe'}
				<a href="https://github.com/firstred/mdstripe/wiki">wiki</a>
			</li>
			<li>
				{l s='If you have found a bug or the wiki didn\'t solve your problem, please open an issue on GitHub:' mod='mdstripe'}
				<a href="https://github.com/firstred/mdstripe/issues">https://github.com/firstred/mdstripe/issues</a>
			</li>
		</ol>
	</fieldset>
	<br />

	<fieldset>
		<legend>{l s='Webhooks' mod='mdstripe'}</legend>
		<p>{l s='This module supports procesing refunds through webhooks' mod='mdstripe'}</p>
		<p>{l s='You can use the following URL:' mod='mdstripe'}<br/>
			<a href="{$stripe_webhook_url|escape:'htmlall':'UTF-8'}">{$stripe_webhook_url|escape:'htmlall':'UTF-8'}</a>
		</p>
	</fieldset>
	<br />
{else}
	<div class="panel">
		<h3><i class="icon icon-puzzle-piece"></i> {l s='Stripe' mod='mdstripe'}</h3>
		<strong>{l s='Accept payments with Stripe' mod='mdstripe'}</strong>
		<p>
			{l s='Thank you for using this module!' mod='mdstripe'}
		</p>
		<strong>{l s='Quick start' mod='mdstripe'}</strong>
		<ol>
			<li>{l s='Visit' mod='mdstripe'} <a href="https://stripe.com/">https://stripe.com/</a> {l s='and find your API keys.' mod='mdstripe'}</li>
			<li>{l s='Enter your keys on this page' mod='mdstripe'}</li>
			<li>{l s='Optionally configure the webhooks and repeat this process for every store if you have multistore enabled' mod='mdstripe'}</li>
			<li>
				{l s='You are good to go! Should you find any problems, please check out the' mod='mdstripe'}
				<a href="https://github.com/firstred/mdstripe/wiki">wiki</a>
			</li>
			<li>
				{l s='If you have found a bug or the wiki didn\'t solve your problem, please open an issue on GitHub:' mod='mdstripe'}
				<a href="https://github.com/firstred/mdstripe/issues">https://github.com/firstred/mdstripe/issues</a>
			</li>
		</ol>
	</div>

	<div class="panel">
		<h3><i class="icon icon-anchor"></i> {l s='Webhooks' mod='mdstripe'}</h3>
		<p>{l s='This module supports procesing refunds through webhooks' mod='mdstripe'}</p>
		<p>{l s='You can use the following URL:' mod='mdstripe'}<br/>
			<a href="{$stripe_webhook_url|escape:'htmlall':'UTF-8'}">{$stripe_webhook_url|escape:'htmlall':'UTF-8'}</a>
		</p>
	</div>
{/if}
