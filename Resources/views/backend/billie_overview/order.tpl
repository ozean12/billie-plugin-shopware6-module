{namespace name="backend/billie_overview/order"}
{extends file="parent:backend/_base/billie_layout.tpl"}

{block name="content/main"}
<div class="wrapper">
    <div class="page-header">
        <h1>{s name="order/heading"}{/s} <small><code>order_id: {$shopwareOrder->getNumber()}</code></small></h1>
        {if $billieOrder.state eq 'shipped'}
            <button
                class="btn btn-primary confirm-payment"
                data-order_id="{$shopwareOrder->getId()}"
                data-action="{url controller="BillieOverview" action="confirmPayment"}"
                >
                {s name="order/confirm_payment"}{/s}
            </button>
        {/if}
        <!--button
            class="btn btn-primary cancel-order"
            data-order_id="{$shopwareOrder->getId()}"
            data-action="{url controller="BillieOverview" action="cancelOrder"}"
            >
            {s name="order/cancel_order"}{/s}
        </button-->
        {if $billieOrder.state != 'canceled'}
            <button
                class="btn btn-primary refund-order"
                data-order_id="{$shopwareOrder->getId()}"
                data-amount-net="{$billieOrder.amount.net}"
                data-amount-gross="{$billieOrder.amount.gross}"
                data-amount-tax="{$billieOrder.amount.tax}"
                data-action="{url controller="BillieOverview" action="refundOrder"}"
                >
                {s name="order/refund_order"}{/s}
            </button>
        {/if}
        {if $billieOrder.state eq 'created'}
            <button
                class="btn btn-primary ship-order"
                data-order_id="{$shopwareOrder->getId()}"
                data-action="{url controller="BillieOverview" action="shipOrder"}"
            >
                {s name="order/ship_order"}{/s}
            </button>
        {/if}
        <a class="btn btn-primary pull-right" href="{url controller="BillieOverview" action="index"}">
            {s name="back"}{/s}
        </a>
    </div>

    <form class="form-horizontal">

        <h3>{s name="order/state/heading"}{/s}</h3>
        <div class="form-group">
            <label for="state" class="col-sm-2 control-label">{s name="order/state/state"}{/s}</label>
            <div class="col-sm-10">
                <span class="state">
                    {if $billieOrder.state == 'created'}
                        {s namespace="backend/billie/states" name="created"}{/s}
                    {elseif $billieOrder.state == 'declined'}
                        {s namespace="backend/billie/states" name="declined"}{/s}
                    {elseif $billieOrder.state == 'shipped'}
                        {s namespace="backend/billie/states" name="shipped"}{/s}
                    {elseif $billieOrder.state == 'paid_out'}
                        {s namespace="backend/billie/states" name="paid_out"}{/s}
                    {elseif $billieOrder.state == 'late'}
                        {s namespace="backend/billie/states" name="late"}{/s}
                    {elseif $billieOrder.state == 'complete'}
                        {s namespace="backend/billie/states" name="complete"}{/s}
                    {elseif $billieOrder.state == 'canceled'}
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
                <span>{$shopwareOrder->getInvoiceAmountNet()|string_format:"%.2f"} {s name="order/amount/net"}{/s}</span><br>
                <span>{$shopwareOrder->getInvoiceAmount()|string_format:"%.2f"} {s name="order/amount/gross"}{/s}</span>
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-2 control-label">{s name="order/amount/billie"}{/s}</label>
            <div class="col-sm-10">
                <span>{$billieOrder.amount.net|string_format:"%.2f"} {s name="order/amount/net"}{/s}</span><br>
                <span>{$billieOrder.amount.gross|string_format:"%.2f"} {s name="order/amount/gross"}{/s}</span>
            </div>
        </div>

        <hr />

        <h3>{s name="order/payment/heading"}Zahlungsdetails{/s}</h3>
        <div class="form-group">
            <label for="IBAN" class="col-sm-2 control-label">{s name="order/payment/iban"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="IBAN" value="{$billieOrder.bankAccount.iban}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="BIC" class="col-sm-2 control-label">{s name="order/payment/bic"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="BIC" value="{$billieOrder.bankAccount.bic}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="BIC" class="col-sm-2 control-label">{s name="order/payment/bank"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="BIC" value="{$shopwareOrder->getAttribute()->getBillieBank()}" readonly>
            </div>
        </div>

        <hr />

        <h3>{s name="order/debtor/heading"}Schuldner{/s}</h3>
        <div class="form-group">
            <label for="name" class="col-sm-2 control-label">{s name="order/debtor/name"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="name" value="{$billieOrder.company.name}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_number" class="col-sm-2 control-label">{s name="order/debtor/house_number"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_number" value="{$billieOrder.company.address_house_number}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_street" class="col-sm-2 control-label">{s name="order/debtor/street"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_street" value="{$billieOrder.company.address_street}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_city" class="col-sm-2 control-label">{s name="order/debtor/city"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_city" value="{$billieOrder.company.address_city}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_postal_code" class="col-sm-2 control-label">{s name="order/debtor/postal_code"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_postal_code" value="{$billieOrder.company.address_postal_code}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_country" class="col-sm-2 control-label">{s name="order/debtor/country"}{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_country" value="{$billieOrder.company.address_country}" readonly>
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
