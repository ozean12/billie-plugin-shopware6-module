{extends file="parent:frontend/address/form.tpl"}
{namespace name="frontend/address/index"}

{block name='frontend_address_form_input_vatid' append}
    <div class="address--regNumber">
        <input name="{$inputPrefix}[attribute][billieRegistrationnumber]"
            type="text"
            id="register_billing_registration_numer"
            value="{$formData.attribute.billieRegistrationnumber|escape}"
            placeholder="{s name="RegNumber/placeholder" namespace="frontend/address/form"}{/s}"
            class="address--field{if $error_flags.billieRegistrationnumber} has--error{/if}"/>
    </div>
    <div class="address--legalform field--select select-field">
        <select name="{$inputPrefix}[attribute][billieLegalform]"
            id="legalform"
            required="required"
            aria-required="true"
            class="is--required{if $error_flags.billieLegalform} has--error{/if}">
            <option value="" disabled="disabled"{if $formData.attribute.billieLegalform eq ""} selected="selected"{/if}>
                {s name='LegalForm/select' namespace="frontend/address/form"}{/s}
                {s name="RequiredField" namespace="frontend/register/index"}{/s}
            </option>

            {foreach $legalForms as $legal}
                <option value="{$legal.code}"{if $formData.attribute.billieLegalform eq $legal.code} selected="selected"{/if}>
                    {$legal.label}
                </option>
            {/foreach}
        </select>
    </div>
{/block}