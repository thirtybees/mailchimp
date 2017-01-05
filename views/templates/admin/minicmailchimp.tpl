{*
 * 2017 Thirty Bees
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
 *  @author    Thirty Bees <modules@thirtybees.com>
 *  @copyright 2017 Thirty Bees
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}
{include file="{$minic.admin_tpl_path}javascript.tpl"}
<div id="minic">
	<div class="header">
		<div id="menu-top">
			<a href="http://module.minic.ro" id="minic-studio" class="social" title="Minic studio module site" target="_blank">minic studio</a>
			<a href="https://plus.google.com/114549918965076938738" class="social" title="Minic studio Google+ page" target="_blank"><i class="icon-googleplus"></i></a>
			<a href="https://github.com/minicstudio" class="social" title="Minic studio Github page" target="_blank"><i class="icon-github"></i></a>
			<a href="https://twitter.com/minicstudio" class="social" title="Minic studio Twitter page" target="_blank"><i class="icon-twitter"></i></a>
			<a href="https://www.facebook.com/minicmodule" class="social" title="Minic studio Facebook page" target="_blank"><i class="icon-facebook"></i></a>
			<div id="more-module">
				<span>Top Modules</span>
				<ul id="module-list">
					<li>{l s='No data available' mod='minicskeletonpro'}</li>
				</ul>
			</div>
			<a href="#newsletter" id="open-newsletter" class="open-popup" data-popup="#newsletter">{l s='Newsletter' mod='mailchimp'}</a>
            <a href="#bug" id="open-bug" class="minic-open">{l s='Bug Report' mod='mailchimp'}</a>
            <a href="#feedback" id="open-feedback" class="minic-open">{l s='Feedback' mod='mailchimp'}</a>
		</div>
		<div id="banner"></div>
		<div id="navigation">
			<a href="#mailchimp" class="minic-open mailchimp" style="float: right;"><i class="icon-cog"></i>{l s='Mailchimp API' mod='mailchimp'}</a>
			{if $minic.mailchimp.apikey}
				<a href="#import" class="minic-open import"><i class="icon-arrow-up"></i>{l s='Import' mod='mailchimp'}</a>
				<a href="#form" class="minic-open form"><i class="icon-user"></i>{l s='Signup form' mod='mailchimp'}</a>
			{/if}
		</div>
	</div>
	<!-- Messages -->
	{include file="{$minic.admin_tpl_path}messages.tpl" id="global" text=$minic.message.text class=$minic.message.type}
	<!-- Settings -->
	{include file="{$minic.admin_tpl_path}mailchimp.tpl"}
	{if $minic.mailchimp.apikey}
		<!-- Import -->
		{include file="{$minic.admin_tpl_path}import.tpl"}
		<!-- Form -->
		{include file="{$minic.admin_tpl_path}form.tpl"}
	{/if}
	<!-- feedback -->
	{include file="{$minic.admin_tpl_path}feedback.tpl"}
	<!-- bug report -->
	{include file="{$minic.admin_tpl_path}bug.tpl"}
	<!-- newsletter popup -->
	{include file="{$minic.admin_tpl_path}popup.tpl" newsletter='1'}
</div>
