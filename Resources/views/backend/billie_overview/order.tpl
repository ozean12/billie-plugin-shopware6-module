{extends file="parent:backend/_base/billie_layout.tpl"}

{block name="content/main"}
    <div class="page-header">
        <h1>Bestellung <small><code>order_id: {$order_id}</code></small></h1>
        <button
            class="btn btn-primary confirm-payment"
            data-order_id="{$order_id}"
            data-action="{url controller="BillieOverview" action="confirmPayment" __csrf_token=$csrfToken}"
            >
            Confirm Payment
        </button>
        <button
            class="btn btn-primary cancel-order"
            data-order_id="{$order_id}"
            data-action="{url controller="BillieOverview" action="cancelOrder" __csrf_token=$csrfToken}"
            >
            Cancel Order
        </button>
    </div>

    <form class="form-horizontal">

        <h3>Zustandsdetails</h3>
        <div class="form-group">
            <label for="state" class="col-sm-2 control-label">Zustand</label>
            <div class="col-sm-10">
                <select class="form-control" readonly>
                    <option value="completed" {{if $state == 'completed'}} selected{{/if}}>completed</option>
                    <option value="canceled" {{if $state == 'canceled'}} selected{{/if}}>canceled</option>
                </select>
            </div>
        </div>

        <hr />

        <h3>Zahlungsdetails</h3>
        <div class="form-group">
            <label for="IBAN" class="col-sm-2 control-label">IBAN</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="IBAN" value="{$bank_account.iban}" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="BIC" class="col-sm-2 control-label">BIC</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="BIC" value="{$bank_account.bic}" readonly>
            </div>
        </div>

        <hr />

        <h3>Schuldner</h3>
        <div class="form-group">
            <label for="name" class="col-sm-2 control-label">Name</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="name" value="{$debtor_company.name}"" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_number" class="col-sm-2 control-label">Hausnummer</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_number" value="{$debtor_company.address_house_number}"" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_street" class="col-sm-2 control-label">Straße</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_street" value="{$debtor_company.address_house_street}"" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_city" class="col-sm-2 control-label">Stadt</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_city" value="{$debtor_company.address_house_city}"" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_postal_code" class="col-sm-2 control-label">PLZ</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_postal_code" value="{$debtor_company.address_house_postal_code}"" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="address_house_country" class="col-sm-2 control-label">Land</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="address_house_country" value="{$debtor_company.address_house_country}"" readonly>
            </div>
        </div>
        
        {* <div class="form-group">
            <div class="col-sm-offset-2 col-sm-10">
            <button type="submit" class="btn btn-default">Sign in</button>
            </div>
        </div> *}
    </form>
{/block}
