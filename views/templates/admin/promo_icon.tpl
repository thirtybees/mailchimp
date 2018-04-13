<a class="list-action-enable action-{if $tr['mailchimp_enabled'] === 'true'}enabled{else}disabled{/if}"
   href="index.php?controller=AdminCartRules&amp;id_cart_rule={$tr['id_cart_rule']|intval}&amp;statusmailchimp_promo&amp;token={$token|escape:'htmlall'}"
   title="{if $tr['mailchimp_enabled'] === 'true'}{l s='Enabled' mod='mailchimp'}{else}{l s='Disbled' mod='mailchimp'}{/if}"
>
  <i class="icon-check{if $tr['mailchimp_enabled'] !== 'true'} hidden{/if}"></i>
  <i class="icon-remove{if $tr['mailchimp_enabled'] === 'true'} hidden{/if}"></i>
</a>
