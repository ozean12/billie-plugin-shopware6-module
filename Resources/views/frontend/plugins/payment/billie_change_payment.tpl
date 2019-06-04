{namespace name='frontend/plugins/payment/billie'}

{* Only show form inputs if payment is selected to not mess with different billie payment options *}
{if $payment_mean.id == $form_data.payment}
<div class="payment--form-group">
    <input name="sBillieRegistrationnumber"
        type="text"
        id="registration_number"
        placeholder="{s name="RegNumber/placeholder" namespace="frontend/address/form"}{/s}"
        value="{$form_data.sBillieRegistrationnumber|escape}"
        class="payment--field{if $error_flags.sBillieRegistrationnumber} has--error{/if}" />

    <div class="field--select select-field">
        <select name="sBillieLegalForm"
            id="legalform"
            required="required"
            aria-required="true"
            class="is--required{if $error_flags.sBillieLegalForm} has--error{/if}">
            <option value="" disabled="disabled"{if $form_data.sBillieLegalForm eq ""} selected="selected"{/if}>
                {s name='LegalForm/select' namespace="frontend/address/form"}{/s}
                {s name="RequiredField" namespace="frontend/register/index"}{/s}
            </option>

            {foreach $legalForms as $legal}
                <option value="{$legal.code}"{if $form_data.sBillieLegalForm eq $legal.code} selected="selected"{/if}>
                    {$legal.label}
                </option>
            {/foreach}
        </select>
    </div>

    {block name='frontend_checkout_payment_required'}
        {* Required fields hint *}
        <div class="register--required-info">
            {s name='RegisterPersonalRequiredText' namespace='frontend/register/personal_fieldset'}{/s}
        </div>
    {/block}
</div>
{/if}