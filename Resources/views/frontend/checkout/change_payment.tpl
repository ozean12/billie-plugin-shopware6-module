{extends file="parent:frontend/checkout/change_payment.tpl"}

{* Method Description *}
{block name="frontend_checkout_payment_fieldset_description"}
    <div class="method--description is--last">
        {block name="frontend_checkout_payment_fieldset_description_billieicon"}
            {if $payment_mean.name|strpos:"billie_payment_" === 0 && $billiePayment.showPaymentIcon}
                <img src="https://static.billie.io/badges/Billie_Checkout_Default.svg" width="60" alt="Billie Payment" style="display: inline-block;float: right;margin: 0 0 0 1rem;" />
            {/if}
        {/block}
        {include file="string:{$payment_mean.additionaldescription}"}
    </div>
{/block}
