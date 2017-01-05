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
<div id="import" class="minic-container">
	<form id="form-feed" class="" method="post" action="{$minic.form_action}">
        <div class="minic-top">
            <h3>{l s='Import customers' mod='mailchimp'}
                <!-- <a href="#" target="_blank" class="help">{l s='help & tips' mod='mailchimp'}</a> -->
            </h3>
            <a href="#import" class="minic-close">x</a>
        </div>
        <div class="minic-content">
	        <div class="input-holder">
                <label>{l s='Choose where you want to import' mod='mailchimp'}:</label>
                <select id="list-selector-import" data-list="import" name="list" class="list-selector">
                    <option value="0"> - </option>
                    {if $mailchimp_list}
                    {foreach from=$mailchimp_list item=list}
                    <option value="{$list.id}">{$list.name}</option>
                    {/foreach}
                    {/if}
                </select>
                <p>{l s='These are your Mailchimp lists, choose where do you want to import the subscribers.' mod='mailchimp'}</p>
            </div>
            <div class="input-holder">
                {foreach from=$mailchimp_list key=k item=list}
                {if isset($list.fields)}
                    <div id="import-{$list.id}" class="fields-holder">
                        <label>{l s='Attach fields' mod='mailchimp'}:</label>
                        {foreach from=$list.fields item=field}
                            {if $field.tag != 'EMAIL'}
                            <div style="clear:both;">
                                <span>{$field.name}</span>
                                <select name="fields[{$list.id}][{$field.tag}]">
                                    <option value="0"> - </option>
                                    <option value="firstname">{l s='First Name' mod='mailchimp'}</option>
                                    <option value="lastname">{l s='Last Name' mod='mailchimp'}</option>
                                </select>
                                {if $field.req}<b>{l s='required' mod='mailchimp'}</b>{/if}
                                {if $field.show == false}<b>{l s='hidden' mod='mailchimp'}</b>{/if}
                            </div>
                            {/if}
                        {/foreach}
                    </div>
                {/if}
                {/foreach}
            </div>
            <div class="switch-holder inline">
                <label for="">{l s='Confirmation email'}:</label>
                <div class="switch small inactive">
                    <input type="radio" class="" name="optin"  value="0" checked="true" />
                </div>
                <p style="clear:both;">{l s='Mailchimp can send a confirmation email after import to the customers, turn on if you wish to inform your customers about the subscription.' mod='mailchimp'}</p>
            </div>
            <div class="switch-holder inline">
                <label for="">{l s='Update if exists'}:</label>
                <div class="switch small inactive">
                    <input type="radio" class="" name="update_users"  value="0" checked="true" />
                </div>
                <p style="clear:both;">{l s='Do you wish to update the subscribers details if they alredy exists?' mod='mailchimp'}</p>
            </div>
            <div class="switch-holder inline">
                <label for="">{l s='Import all Customers'}:</label>
                <div class="switch small inactive">
                    <input type="radio" class="" name="all-user"  value="0" checked="true" />
                </div>
                <p style="clear:both;">{l s='Turn this on if you wish to import all of the users. This means that the module ignores the customers newsletter option.' mod='mailchimp'}</p>
            </div>
        </div>
        <div class="minic-bottom">
            <input type="submit" name="submitImport" class="button-large green" value="{l s='Import' mod='mailchimp'}" />
            <a href="#import" class="minic-close button-large lgrey">{l s='Close' mod='mailchimp'}</a>
        </div>
	</form>
</div>
