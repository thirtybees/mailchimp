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
<div id="feedback" class="minic-container">
	<form id="form-feed" class="" method="post">
        <div class="minic-top">
            <h3>{l s='Feedback' mod='mailchimp'}
                <a href="http://module.minic.ro/how-to-use-feedback-and-bug-report-in-our-modules/" target="_blank" class="help">{l s='help & tips' mod='mailchimp'}</a>
            </h3>
            <a href="#feedback" class="minic-close">x</a>
        </div>
        <div class="minic-content">
            <!-- Response -->
            {include file="{$minic.admin_tpl_path}messages.tpl" id='feedback'}
    		<div class="input-holder">
    			<label>{l s='Your name' mod='mailchimp'}:</label>
    			<input id="feedback-name" class="name" type="text" name="name" placeholder="John Doe" value="" />
    		</div>
    		<div class="input-holder">
    			<label>{l s='Your email' mod='mailchimp'}:</label>
    			<input id="feedback-email" class="" type="text" name="email" placeholder="example@example.com" value="{$minic.info.email}" />
    		</div>
    		<div class="input-holder">
    			<label>{l s='Site address' mod='mailchimp'}:</label>
    			<input id="feedback-domain" class="" type="text" name="domain" placeholder="mydomain.com" value="{$minic.info.domain}" />
    		</div>
            <div class="input-holder">
                <label>{l s='Message' mod='mailchimp'}:</label>
                <textarea id="feedback-message" class="" name="message" placeholder="Your message, request, suggestion, etc." rows="10" cols="49"></textarea>
            </div>
            <div class="minic-comments"> 
                <h3>{l s='Notes' mod='mailchimp'}</h3>
                <p>{l s='Feel free to give us a feedback about our work (we really like to hear few good words) or write down your idea / request and if we think its good we`ll concider to implement into future versions.' mod='mailchimp'}</p>
                <h3>{l s='Important!' mod='mailchimp'}</h3>
                <p>{l s='By clicking to the "Send" button you agree that we will get some basic information. If you do not wish to send your e-mail address uncheck the checkbox.' mod='mailchimp'}</p>
                <ul>
                    <li>{l s='By default this is the shop email address, feel free to modify.'}: <span>{$minic.info.email}</span></li>
                    <li>{l s='Your shops domain' mod='mailchimp'}: <span>{$minic.info.domain}</span></li>
                    <li>{l s='Prestashop version' mod='mailchimp'}: <span>{$minic.info.psVersion}</span></li>
                    <li>{l s='Module version' mod='mailchimp'}: <span>{$minic.info.version}</span></li>
                </ul>
            </div>
        </div>
        <div class="minic-bottom">
            <a href="#feedback" id="send-feedback" class="button-large green">{l s='Send' mod='mailchimp'}</a>
            <a href="#feedback" class="minic-close button-large lgrey">{l s='Close' mod='mailchimp'}</a>
        </div>
	</form>
</div>
