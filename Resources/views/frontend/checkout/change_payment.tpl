{extends file="parent:frontend/checkout/change_payment.tpl"}

{* Method Description *}
{block name="frontend_checkout_payment_fieldset_description"}
    <div class="method--description is--last">
        {block name="frontend_checkout_payment_fieldset_description_billieicon"}
            {if $payment_mean.name|strpos:"billie_payment_" === 0 && $billiePayment.showPaymentIcon}
                <img src="https://www.billie.io/assets/images/favicons/favicon-16x16.png" width="16" height="16" style="display: inline-block;" />
            {/if}
        {/block}
        {include file="string:{$payment_mean.additionaldescription}"}
    </div>
{/block}
