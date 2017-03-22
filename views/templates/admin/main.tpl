<div class="tabs clearfix">
	<div class="sidebar navigation col-md-2">
		{if isset($tab_contents.logo)}
			<img class="tabs-logo" src="{$tab_contents.logo|escape:'htmlall':'UTF-8'}"/>
		{/if}
		<nav class="list-group category-list">
			{foreach from=$tab_contents.contents key=tab_nbr item=content}
				<a class="list-group-item migration-tab"
				   href="#mailchimp_tab_{$tab_nbr + 1|intval}">

					{if isset($content.icon) && $content.icon != false}
						<i class="{$content.icon|escape:"htmlall":"UTF-8"} pstab-icon"></i>
					{/if}

					{$content.name|escape:"htmlall":"UTF-8"}

					{if isset($content.badge) && $content.badge != false}
						<span class="badge-module-tabs pull-right {$content.badge|escape:'htmlall':'UTF-8'}"></span>
					{/if}
				</a>
			{/foreach}
		</nav>
	</div>

	<div class="col-md-10">
		<div class="content-wrap panel">
			{foreach from=$tab_contents.contents key=tab_nbr item=content}
				<section id="section-shape-{$tab_nbr + 1|intval}">{$content.value|escape:'UTF-8'}</section>
			{/foreach}
		</div>
	</div>

</div>
<script type="text/javascript" src="{$new_base_dir|escape:'htmlall':'UTF-8'}views/js/configtabs.js"></script>

{include file='./export_products_ajax.tpl'}
{include file='./export_carts_ajax.tpl'}
{include file='./export_orders_ajax.tpl'}
