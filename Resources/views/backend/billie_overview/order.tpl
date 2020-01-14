{namespace name="backend/billie_overview/order"}
{extends file="parent:backend/_base/billie_layout.tpl"}

{block name="content/main"}
<div class="wrapper">
    <div class="page-header">
        <h1>{s name="order/heading"}Bestellung{/s} <small><code>order_id: {$order_number}</code></small></h1>
        <button
            class="btn btn-primary confirm-payment"
            data-order_id="{$order_id}"
            data-action="{url controller="BillieOverview" action="confirmPayment" __csrf_token=$csrfToken}"
            >
            {s name="order/confirm_payment"}Zahlung bestätigen{/s}
        </button>
        <button
            class="btn btn-primary cancel-order"
            data-order_id="{$order_id}"
            data-action="{url controller="BillieOverview" action="cancelOrder" __csrf_token=$csrfToken}"
            >
            {s name="order/cancel_order"}Bestellung stornieren{/s}
        </button>
        {if $state eq 'created'}
            <button
                class="btn btn-primary ship-order"
                data-order_id="{$order_id}"
                data-action="{url controller="BillieOverview" action="shipOrder" __csrf_token=$csrfToken}"
            >
                {s name="order/ship_order"}Bestellung als verschickt markieren{/s}
            </button>
        {/if}
        <a class="btn btn-primary pull-right" href="{url controller="BillieOverview" action="index" __csrf_token=$csrfToken}">
            {s name="back"}Back to Overview{/s}
        </a>
    </div>

    <form class="form-horizontal">

        <h3>{s name="order/state/heading"}Zustandsdetails{/s}</h3>
        <div class="form-group">
            <label for="state" class="col-sm-2 control-label">{s name="order/state/state"}Zustand{/s}</label>
            <div class="col-sm-10">
                <span class="state">
                    {if $state == 'created'}
                        {s name="order/state/created"}erstellt{/s}
                    {elseif $state == 'declined'}
                        {s name="order/state/declined"}abgelehnt{/s}
                    {elseif $state == 'shipped'}
                        {s name="order/state/shipped"}verschickt{/s}
                    {elseif $state == 'paid_out'}
                        {s name="order/state/paid_out"}ausbezahlt{/s}
                    {elseif $state == 'late'}
                        {s name="order/state/late"}überfällig{/s}
                    {elseif $state == 'complete'}
                        {s name="order/state/complete"}abgeschlossen{/s}
                    {elseif $state == 'canceled'}
                        {s name="order/state/canceled"}storniert{/s}
                    {/if}
                </span>
            </div>
        </div>

        <hr />

        <h3>{s name="order/amount/heading"}{/s}</h3>
        <div class="form-group">
            <label class="col-sm-2 control-label">{s name="order/amount/shopware"}{/s}</label>
            <div class="col-sm-10">
                <span>{$shopwareOrder.invoiceAmountNet} {s name="order/amount/net"}{/s}</span><br>
                <span>{$shopwareOrder.invoiceAmount} {s name="order/amount/gross"}{/s}</span>
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-2 control-label">{s name="order/amount/billie"}{/s}</label>
            <div class="col-sm-10">
                <span>{$amountNet} {s name="order/amount/net"}{/s}</span><br>
                <span>{$amount} {s name="order/amount/gross"}{/s}</span>
            </div>
        </div>

        <hr />

        <h3>{s name="order/payment/heading"}Zahlungsdetails{/s}</h3>
        <div class="form-group">
            <label for="IBAN" class="col-sm-2 control-label">{s name="order/payment/iban"}IBAN{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="IBAN" value="{$bank_account.iban}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="BIC" class="col-sm-2 control-label">{s name="order/payment/bic"}BIC{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="BIC" value="{$bank_account.bic}" readonly>
            </div>
        </div>

        <hr />

        <h3>{s name="order/debtor/heading"}Schuldner{/s}</h3>
        <div class="form-group">
            <label for="name" class="col-sm-2 control-label">{s name="order/debtor/name"}Name{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="name" value="{$debtor_company.name}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_number" class="col-sm-2 control-label">{s name="order/debtor/house_number"}Hausnummer{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_number" value="{$debtor_company.address_house_number}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_street" class="col-sm-2 control-label">{s name="order/debtor/street"}Straße{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_street" value="{$debtor_company.address_house_street}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_city" class="col-sm-2 control-label">{s name="order/debtor/city"}Stadt{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_city" value="{$debtor_company.address_house_city}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_postal_code" class="col-sm-2 control-label">{s name="order/debtor/postal_code"}PLZ{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_postal_code" value="{$debtor_company.address_house_postal_code}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_country" class="col-sm-2 control-label">{s name="order/debtor/country"}Land{/s}</label>
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
            <small>{s name="back"}Back to Overview{/s}</small>
        </a>
    </p>
</div>
{/block}
