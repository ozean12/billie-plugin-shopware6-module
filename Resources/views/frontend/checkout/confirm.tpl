{extends file="parent:frontend/checkout/confirm.tpl"}

{block name="frontend_checkout_confirm_error_messages" prepend}
    {if $errorCode}
        {include file="frontend/_includes/messages.tpl" type="error" content=$errorCode|snippet:$errorCode:'frontend/checkout/errors'}
    {/if}
    {if $apiErrorMessages}
        {include file="frontend/_includes/messages.tpl" type="error" content=$apiErrorMessages[0]}
    {/if}
{/block}