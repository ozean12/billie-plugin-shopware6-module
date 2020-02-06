{extends file="parent:documents/index.tpl"}

{namespace name="documents/billie"}

{block name="document_index_css"}
    {$smarty.block.parent}
    {block name="document_billie_css"}
        .billie-instructions {
            margin-bottom: 0;
        }
        .billie-table td {
            padding: 0;
        }
        .billie-table th {
            text-align: left;
            padding: 0 20px 0 0;
        }
    {/block}
{/block}

{block name="document_index_info_net"}
    {$smarty.block.parent}
    {if $Document.key == 'invoice' && $Order._payment.name|strstr:'billie_payment_after_delivery'}
        {block name="document_billie_instructions"}
            <p class="billie-instructions">{s name="paymentInstructions"}{/s}</p>
            <table class="billie-table">
                <tr>
                    <th>{s name="account_holder"}{/s}:</th>
                    <td>{config name="shopName"}</td>
                </tr>
                <tr>
                    <th>{s name="iban"}{/s}:</th>
                    <td>{$Order._order.attributes.billie_iban}</td>
                </tr>
                <tr>
                    <th>{s name="bic"}{/s}:</th>
                    <td>{$Order._order.attributes.billie_bic}</td>
                </tr>
                <tr>
                    <th>{s name="bank_name"}{/s}:</th>
                    <td>{$Order._order.attributes.billie_bank}</td>
                </tr>
                <tr>
                    <th>{s name="due_date"}{/s}:</th>
                    <td>{$Order._order.attributes.billie_duration_date}</td>
                </tr>
                <tr>
                    <th>{s name="reference_number"}{/s}:</th>
                    <td>{$Document.id}</td>
                </tr>
            </table>
        {/block}
    {/if}
{/block}
