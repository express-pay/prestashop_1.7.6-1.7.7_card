{if $status == 'ok'}
	{$success_message}
{else}
	<p class="warning">
		{l s='We have noticed that there is a problem with your order. If you think this is an error, you can contact our' mod='expresspay'}
		<a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer service department.' mod='expresspay'}</a>
	</p>
{/if}