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
<div id="form" class="minic-container">
	<form id="form-feed" class="" method="post" action="{$minic.form_action}">
        <div class="minic-top">
            <h3>{l s='Signup form settings' mod='mailchimp'}
                <!-- <a href="#" target="_blank" class="help">{l s='help & tips' mod='mailchimp'}</a> -->
            </h3>
            <a href="#form" class="minic-close">x</a>
        </div>
        <div class="minic-content">
            <div class="input-holder">
                <label for="">{l s='Block title' mod='mailchimp'}:</label>
                {foreach from=$minic.languages item=language}
                    <div id="title_{$language.id_lang}" style="display: {if $language.id_lang == $minic.default_lang}block{else}none{/if};">
                        <input type="text" name="title_{$language.id_lang}" value="{if $minic.form.data.{$language.id_lang}.title}{$minic.form.data.{$language.id_lang}.title}{/if}">
                    </div>
                {/foreach}
                {$minic.flags.title}
                <p style="float: left; clear: both;">{l s='Leave it empty if you do not want to appear on the front of the site.' mod='mailchimp'}</p>
            </div>
            <div class="input-holder">
                <label>{l s='Choose where to show the form' mod='mailchimp'}:</label>
                <select multiple name="hooks[]" id="">
                    {foreach from=$minic.hooks item=hook}
                    <option value="{$hook}" {if $minic.form.hooks && in_array($hook, $minic.form.hooks)}selected{/if}>{$hook}</option>
                    {/foreach}
                </select>
                <p>{l s='Hold down the CTRL to select multiple.' mod='mailchimp'}</p>
            </div>
            <div class="input-holder">
                <label>{l s='Insert the form code here' mod='mailchimp'}:</label>
                {foreach from=$minic.languages item=language}
                    <div id="form_{$language.id_lang}" style="display: {if $language.id_lang == $minic.default_lang}block{else}none{/if};">
                        <textarea name="form_{$language.id_lang}">{if $minic.form.data.{$language.id_lang}.form}{$minic.form.data.{$language.id_lang}.form}{/if}</textarea>
                    </div>
                {/foreach}
                {$minic.flags.form}
            </div>
            <div class="minic-comments"> 
                <h3>{l s='How to get the form code' mod='mailchimp'}</h3>
                <ol style="list-style: decimal;">
                    <li>{l s='Log in into' mod='mailchimp'} <a href="https://login.mailchimp.com/" target="_blank">Mailchimp</a>.</li>
                    <li>{l s='Go to the lists, and select a list where you want the subscribers to subscribe.' mod='mailchimp'}</li>
                    <li>{l s='Select the "Signup Forms".' mod='mailchimp'}</li>
                    <li>{l s='Select the "Embedded forms".' mod='mailchimp'}</li>
                    <li>{l s='Configure your form to serve your needs.' mod='mailchimp'}</li>
                    <li>{l s='Copy the HTML code at the bottom of the page.' mod='mailchimp'}</li>
                    <li>{l s='Paste the code into the textfield here.' mod='mailchimp'}</li>
                </ol>
                <h3>{l s='Important' mod='mailchimp'}</h3>
                <p>{l s='If you wish to use the module properly and you have multiple languages enabled, then do not forget to change the titles and other texts in the form code (if you do not understand the HTML code you can do it when you configure the form on the Mailchimp website, just repeat the steps above and change the titles).' mod='mailchimp'}</p>
            </div>
        </div>
        <div class="minic-bottom">
            <input type="submit" name="submitForm" class="button-large green" value="{l s='Save' mod='mailchimp'}" />
            <a href="#form" class="minic-close button-large lgrey">{l s='Close' mod='mailchimp'}</a>
        </div>
	</form>
</div>
