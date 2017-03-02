<h2>{l s='The following cron URLs are available:' mod='mailchimp'}</h2>
<ul>
	<li>{l s='Export all products:' mod='mailchimp'} <a href="{$cron_all_products|escape:'htmlall':'UTF-8'}">{$cron_all_products|escape:'htmlall':'UTF-8'}</a></li>
	<li>{l s='Export remaining products:' mod='mailchimp'} <a href="{$cron_remaining_products|escape:'htmlall':'UTF-8'}">{$cron_remaining_products|escape:'htmlall':'UTF-8'}</a></li>
	<li>{l s='Export all carts:' mod='mailchimp'} <a href="{$cron_all_carts|escape:'htmlall':'UTF-8'}">{$cron_all_carts|escape:'htmlall':'UTF-8'}</a></li>
	<li>{l s='Export remaining carts:' mod='mailchimp'} <a href="{$cron_remaining_carts|escape:'htmlall':'UTF-8'}">{$cron_remaining_carts|escape:'htmlall':'UTF-8'}</a></li>
	<li>{l s='Export all orders:' mod='mailchimp'} <a href="{$cron_all_orders|escape:'htmlall':'UTF-8'}">{$cron_all_orders|escape:'htmlall':'UTF-8'}</a></li>
	<li>{l s='Export remaining orders:' mod='mailchimp'} <a href="{$cron_remaining_orders|escape:'htmlall':'UTF-8'}">{$cron_remaining_orders|escape:'htmlall':'UTF-8'}</a></li>
</ul>
