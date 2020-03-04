{namespace name="backend/billie_overview/order"}
{extends file="parent:backend/_base/billie_layout.tpl"}

{block name="content/main"}
<div class="wrapper">
    <div class="page-header">
        <h1>{s name="order/heading"}{/s} <small><code>order_id: {$order_number}</code></small></h1>
        <button
            class="btn btn-primary confirm-payment"
            data-order_id="{$order_id}"
            data-action="{url controller="BillieOverview" action="confirmPayment" __csrf_token=$csrfToken}"
            >
            {s name="order/confirm_payment"}{/s}
        </button>
        <!--button
            class="btn btn-primary cancel-order"
            data-order_id="{$order_id}"
            data-action="{url controller="BillieOverview" action="cancelOrder" __csrf_token=$csrfToken}"
            >
            {s name="order/cancel_order"}{/s}
        </button-->
        {if $state != 'canceled'}
            <button
                class="btn btn-primary refund-order"
                data-order_id="{$order_id}"
                data-amount-net="{$amountNet}"
                data-amount-gross="{$amount}"
                data-amount-tax="{$amountTax}"
                data-action="{url controller="BillieOverview" action="refundOrder" __csrf_token=$csrfToken}"
                >
                {s name="order/refund_order"}{/s}
            </button>
        {/if}
        {if $state eq 'created'}
            <button
                class="btn btn-primary ship-order"
                data-order_id="{$order_id}"
                data-action="{url controller="BillieOverview" action="shipOrder" __csrf_token=$csrfToken}"
            >
                {s name="order/ship_order"}{/s}
            </button>
        {/if}
        <a class="btn btn-primary pull-right" href="{url controller="BillieOverview" action="index" __csrf_token=$csrfToken}">
            {s name="back"}{/s}
        </a>
    </div>

    <form class="form-horizontal">

        <h3>{s name="order/state/heading"}{/s}</h3>
        <div class="form-group">
            <label for="state" class="col-sm-2 control-label">{s name="order/state/state"}{/s}</label>
            <div class="col-sm-10">
                <span class="state">
                    {if $state == 'created'}
                        {s namespace="backend/billie/states" name="created"}{/s}
                    {elseif $state == 'declined'}
                        {s namespace="backend/billie/states" name="declined"}{/s}
                    {elseif $state == 'shipped'}
                        {s namespace="backend/billie/states" name="shipped"}{/s}
                    {elseif $state == 'paid_out'}
                        {s namespace="backend/billie/states" name="paid_out"}{/s}
                    {elseif $state == 'late'}
                        {s namespace="backend/billie/states" name="late"}{/s}
                    {elseif $state == 'complete'}
                        {s namespace="backend/billie/states" name="complete"}{/s}
                    {elseif $state == 'canceled'}
                        {s namespace="backend/billie/states" name="canceled"}{/s}
                    {/if}
                </span>
            </div>
        </div>

        <hr />

        <h3>{s name="order/amount/heading"}{/s}</h3>
        <div class="form-group">
            <label class="col-sm-2 control-label">{s name="order/amount/shopware"}{/s}</label>
            <div class="col-sm-10">
                <span>{$shopwareOrder.invoiceAmountNet|string_format:"%.2f"} {s name="order/amount/net"}{/s}</span><br>
                <span>{$shopwareOrder.invoiceAmount|string_format:"%.2f"} {s name="order/amount/gross"}{/s}</span>
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-2 control-label">{s name="order/amount/billie"}{/s}</label>
            <div class="col-sm-10">
                <span>{$amountNet|string_format:"%.2f"} {s name="order/amount/net"}{/s}</span><br>
                <span>{$amount|string_format:"%.2f"} {s name="order/amount/gross"}{/s}</span>
            </div>
        </div>

        <hr />

        <h3>{s name="order/payment/heading"}Zahlungsdetails{/s}</h3>
        <div class="form-group">
            <label for="IBAN" class="col-sm-2 control-label">{s name="order/payment/iban"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="IBAN" value="{$bank_account.iban}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="BIC" class="col-sm-2 control-label">{s name="order/payment/bic"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="BIC" value="{$bank_account.bic}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="BIC" class="col-sm-2 control-label">{s name="order/payment/bank"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="BIC" value="{$bank_account.bank}" readonly>
            </div>
        </div>

        <hr />

        <h3>{s name="order/debtor/heading"}Schuldner{/s}</h3>
        <div class="form-group">
            <label for="name" class="col-sm-2 control-label">{s name="order/debtor/name"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="name" value="{$debtor_company.name}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_number" class="col-sm-2 control-label">{s name="order/debtor/house_number"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_number" value="{$debtor_company.address_house_number}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_street" class="col-sm-2 control-label">{s name="order/debtor/street"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_street" value="{$debtor_company.address_house_street}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_city" class="col-sm-2 control-label">{s name="order/debtor/city"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_city" value="{$debtor_company.address_house_city}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_postal_code" class="col-sm-2 control-label">{s name="order/debtor/postal_code"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_postal_code" value="{$debtor_company.address_house_postal_code}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_country" class="col-sm-2 control-label">{s name="order/debtor/country"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_country" value="{$debtor_company.address_house_country}" readonly>
            </div>
        </div>

        {* <div class="form-group">
            <div class="col-sm-offset-2 col-sm-10">
            <button type="submit" class="btn btn-default">Sign in</button>
            </div>
        </div> *}
    </form>

    <p>
        <a class="btn btn-link pull-right" href="{url controller="BillieOverview" action="index" __csrf_token=$csrfToken}">
            <small>{s name="back"}{/s}</small>
        </a>
    </p>
</div>
{/block}
