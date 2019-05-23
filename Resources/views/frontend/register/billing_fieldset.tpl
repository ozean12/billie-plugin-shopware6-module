{extends file="parent:frontend/register/billing_fieldset.tpl"}
{namespace name="frontend/address/index"}

{block name='frontend_register_billing_fieldset_input_vatId' append}
    <div class="register--regNumber">
        <input name="register[personal][address][attribute][billieRegistrationnumber]"
            type="text"
            id="register_billing_registration_numer"
            value="{$form_data.personal.attribute.billieRegistrationnumber|escape}"
            placeholder="{s name="RegNumber/placeholder" namespace="frontend/address/form"}{/s}"
            class="register--field{if $error_flags.billieRegistrationnumber} has--error{/if}"/>
    </div>
    <div class="register--legalform field--select select-field">
        <select name="register[personal][address][attribute][billieLegalform]"
            id="legalform"
            required="required"
            aria-required="true"
            class="is--required{if $error_flags.billieLegalform} has--error{/if}">
            <option value="" disabled="disabled"{if $form_data.personal.attribute.billieLegalform eq ""} selected="selected"{/if}>
                {s name='LegalForm/select' namespace="frontend/address/form"}{/s}
                {s name="RequiredField" namespace="frontend/register/index"}{/s}
            </option>
            {foreach $legalForms as $legal}
                <option value="{$legal.code}"{if $form_data.personal.attribute.billieLegalform eq $legal.code} selected="selected"{/if}>
                    {$legal.label}
                </option>
            {/foreach}
        </select>
    </div>
{/block}