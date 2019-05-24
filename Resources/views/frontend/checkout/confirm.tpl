{extends file="parent:frontend/checkout/confirm.tpl"}

{* Error Messages *}
{block name="frontend_checkout_confirm_error_messages" prepend}
    {if $errorCode}
        {include file="frontend/_includes/messages.tpl" type="error" content=$errorCode|snippet:$errorCode:'frontend/checkout/errors'}
    {/if}
{/block}

{* Append Invalid Invoice Addresses Error Messages *}
{block name="frontend_checkout_confirm_information_addresses_equal_panel_billing_invalid_data" append}
    {if $invalidInvoiceAddress}
        {include file='frontend/_includes/messages.tpl' type="warning" content=$errorCode|snippet:$invalidInvoiceAddressSnippet:'frontend/checkout/errors'}
    {/if}
{/block}

{* Set invalidBillingAddress Flag, so that the theme/shop can disable the buy button *}
{block name='frontend_checkout_confirm_confirm_table_actions' prepend}
    {if $invalidInvoiceAddress}
        {$invalidBillingAddress = true}
    {/if}
{/block}
