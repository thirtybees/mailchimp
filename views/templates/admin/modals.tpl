{foreach $modals as $modal}
	<div class="modal fade bootstrap" id="{$modal.modal_id}" tabindex="-1">
		<div class="modal-dialog {if isset($modal.modal_class)}{$modal.modal_class}{/if}">
			<div class="modal-content">
				{if isset($modal.modal_title)}
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal">&times;</button>
						<h4 class="modal-title">{$modal.modal_title}</h4>
					</div>
				{/if}

				{$modal.modal_content|escape:'UTF-8'}

				{if isset($modal.modal_actions)}
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">{l s='Close'}</button>
						{foreach $modal.modal_actions as $action}
							{if $action.type == 'link'}
								<a href="{$action.href}" class="btn {$action.class}">{$action.label}</a>
							{elseif $action.type == 'button'}
								<button type="button" value="{$action.value}" class="btn {$action.class}">
									{$action.label}
								</button>
							{/if}
						{/foreach}
					</div>
				{/if}
			</div>
		</div>
	</div>
{/foreach}
