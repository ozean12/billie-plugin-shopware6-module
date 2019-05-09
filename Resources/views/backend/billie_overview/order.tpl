{extends file="parent:backend/_base/billie_layout.tpl"}

{block name="content/main"}
    <div class="page-header">
        <h1>{s name="billiepayment/order/heading"}Bestellung{/s} <small><code>order_id: {$order_id}</code></small></h1>
        <button
            class="btn btn-primary confirm-payment"
            data-order_id="{$order_id}"
            data-action="{url controller="BillieOverview" action="confirmPayment" __csrf_token=$csrfToken}"
            >
            {s name="billiepayment/order/confirm_payment"}Zahlung bestätigen{/s}
        </button>
        <button
            class="btn btn-primary cancel-order"
            data-order_id="{$order_id}"
            data-action="{url controller="BillieOverview" action="cancelOrder" __csrf_token=$csrfToken}"
            >
            {s name="billiepayment/order/cancel_order"}Bestellung stornieren{/s}
        </button>
    </div>

    <form class="form-horizontal">

        <h3>{s name="billiepayment/order/state/heading"}Zustandsdetails{/s}</h3>
        <div class="form-group">
            <label for="state" class="col-sm-2 control-label">{s name="billiepayment/order/state/state"}Zusantd{/s}</label>
            <div class="col-sm-10">
                <select class="form-control" readonly>
                    <option value="completed" {{if $state == 'created'}} selected{{/if}}>{s name="billiepayment/order/state/created"}erstellt{/s}</option>
                    <option value="completed" {{if $state == 'declined'}} selected{{/if}}>{s name="billiepayment/order/state/declined"}abgelehnt{/s}</option>
                    <option value="completed" {{if $state == 'shipped'}} selected{{/if}}>{s name="billiepayment/order/state/shipped"}verschickt{/s}</option>
                    <option value="completed" {{if $state == 'paid_out'}} selected{{/if}}>{s name="billiepayment/order/state/paid_out"}ausbezahlt{/s}</option>
                    <option value="completed" {{if $state == 'late'}} selected{{/if}}>{s name="billiepayment/order/state/late"}überfällig{/s}</option>
                    <option value="completed" {{if $state == 'complete'}} selected{{/if}}>{s name="billiepayment/order/state/complete"}abgeschlossen{/s}</option>
                    <option value="canceled" {{if $state == 'canceled'}} selected{{/if}}>{s name="billiepayment/order/state/canceled"}storniert{/s}</option>
                </select>
            </div>
        </div>

        <hr />

        <h3>{s name="billiepayment/order/payment/heading"}Zahlungsdetails{/s}</h3>
        <div class="form-group">
            <label for="IBAN" class="col-sm-2 control-label">{s name="billiepayment/order/payment/iban"}IBAN{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="IBAN" value="{$bank_account.iban}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="BIC" class="col-sm-2 control-label">{s name="billiepayment/order/payment/bic"}BIC{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="BIC" value="{$bank_account.bic}" readonly>
            </div>
        </div>

        <hr />

        <h3>{s name="billiepayment/order/debtor/heading"}Schuldner{/s}</h3>
        <div class="form-group">
            <label for="name" class="col-sm-2 control-label">{s name="billiepayment/order/debtor/name"}Name{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="name" value="{$debtor_company.name}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_number" class="col-sm-2 control-label">{s name="billiepayment/order/debtor/house_number"}Hausnummer{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_number" value="{$debtor_company.address_house_number}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_street" class="col-sm-2 control-label">{s name="billiepayment/order/debtor/street"}Straße{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_street" value="{$debtor_company.address_house_street}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_city" class="col-sm-2 control-label">{s name="billiepayment/order/debtor/city"}Stadt{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_city" value="{$debtor_company.address_house_city}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_postal_code" class="col-sm-2 control-label">{s name="billiepayment/order/debtor/postal_code"}PLZ{/s}</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_postal_code" value="{$debtor_company.address_house_postal_code}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_country" class="col-sm-2 control-label">{s name="billiepayment/order/debtor/country"}Land{/s}</label>
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
{/block}
