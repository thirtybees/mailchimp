{if $context_shop}
  &nbsp;<div class="badge">{l s='Configuring for shop %s' sprintf=[$context_shop]}</div>
{else}
  &nbsp;<div class="badge">{l s='Configuring for all shops' mod='mailchimp'}</div>
{/if}
