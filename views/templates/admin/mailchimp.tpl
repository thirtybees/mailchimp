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
<div id="mailchimp" class="minic-container" {if !$minic.mailchimp.apikey}style="display:block;"{/if}>
	<form id="form-feed" class="" method="post" action="{$minic.form_action}">
        <div class="minic-top">
            <h3>{l s='Mailchimp API configuration' mod='mailchimp'}
                <!-- <a href="#" target="_blank" class="help">{l s='help & tips' mod='mailchimp'}</a> -->
            </h3>
            <a href="#mailchimp" class="minic-close">x</a>
        </div>
        <div class="minic-content">
    		<div class="input-holder">
    			<label>{l s='Mailchimp API Key' mod='mailchimp'}:</label>
    			<input id="mailchimp-key" class="apikey" type="text" name="apikey" value="{if $minic.mailchimp.apikey}{$minic.mailchimp.apikey}{/if}" />
                <p>{l s='The API key provided by Mailchimp' mod='mailchimp'}</p>
    		</div>
            <div class="switch-holder inline">
                <label>{l s='Use SSL'}: </label>
                <div class="switch small {if isset($minic.mailchimp.ssl) && $minic.mailchimp.ssl}active{else}inactive{/if}">
                    <input type="radio" class="" name="ssl"  value="{if isset($minic.mailchimp.ssl) && $minic.mailchimp.ssl}1{else}0{/if}" checked="true" />
                </div>
                <p>{l s='Turn on for secure (SSL) connection' mod='mailchimp'}</p>
            </div>
            <div class="minic-comments"> 
                <h3>{l s='How to get your API key' mod='mailchimp'}</h3>
                <p>{l s='If you alredy have an API key click'} <a href="https://us6.admin.mailchimp.com/account/api-key-popup/" target="_blank">{l s='here' mod='mailchimp'}</a>, {l s='otherwise you can get a new one from'} <a href="https://us6.admin.mailchimp.com/account/api/" target="_blank">{l s='here' mod='mailchimp'}</a></p>
                <h3>{l s='Tutorial to get an API key' mod='mailchimp'}</h3>
                <p>{l s='You can find more info'} <a href="http://kb.mailchimp.com/article/where-can-i-find-my-api-key" target="_blank">{l s='here' mod='mailchimp'}</a></p>
            </div>
        </div>
        <div class="minic-bottom">
            <input type="submit" name="submitMailchimp" class="button-large green" value="{l s='Save' mod='mailchimp'}" />
            <a href="#mailchimp" class="minic-close button-large lgrey">{l s='Close' mod='mailchimp'}</a>
        </div>
	</form>
</div>
