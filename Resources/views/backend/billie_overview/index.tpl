{extends file="parent:backend/_base/billie_layout.tpl"}

{block name="content/main"}
    <div class="page-header">
        <h1>{s name="billiepayment/listing/heading"}Bestellungen{/s}</h1>
    </div>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>{s name="billiepayment/listing/column/order_time"}Bestell-Zeit{/s}</th>
                    <th>{s name="billiepayment/listing/column/order_numer"}Bestellnummer{/s}</th>
                    <th>{s name="billiepayment/listing/column/amount"}Betrag{/s}</th>
                    <th>{s name="billiepayment/listing/column/transaction"}Transaktion{/s}</th>
                    <th>{s name="billiepayment/listing/column/current_state"}Aktueller Status{/s}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                {foreach $orders as $order}
                    {$state = $order.attribute.billieState}
                    <tr class="wrapper {$statusClasses.$state}">
                        <td>{$order.orderTime|date:DATE_SHORT}</td>
                        <td>{$order.number}</td>
                        <td>{$order.invoiceAmount|currency:use_shortname:right}</td>
                        <td>{$order.transactionId}</td>
                        <td class="state">
                            {if $order.attribute.billieState == 'created'}
                                {s name="billiepayment/order/state/created"}erstellt{/s}
                            {elseif $order.attribute.billieState == 'declined'}
                                {s name="billiepayment/order/state/declined"}abgelehnt{/s}
                            {elseif $order.attribute.billieState == 'shipped'}
                                {s name="billiepayment/order/state/shipped"}verschickt{/s}
                            {elseif $order.attribute.billieState == 'paid_out'}
                                {s name="billiepayment/order/state/paid_out"}ausbezahlt{/s}
                            {elseif $order.attribute.billieState == 'late'}
                                {s name="billiepayment/order/state/late"}überfällig{/s}
                            {elseif $order.attribute.billieState == 'complete'}
                                {s name="billiepayment/order/state/complete"}abgeschlossen{/s}
                            {elseif $order.attribute.billieState == 'canceled'}
                                {s name="billiepayment/order/state/canceled"}storniert{/s}
                            {/if}
                        </td>
                        <td>
                            <a href="{url controller="BillieOverview" action="order" order_id="{$order.id}"}" class="btn btn-primary">
                                <i class="glyphicon glyphicon-pencil"></i>
                            </a>
                            <button
                                class="btn btn-danger cancel-order"
                                data-order_id="{$order.id}"
                                data-action="{url controller="BillieOverview" action="cancelOrder" __csrf_token=$csrfToken}"
                            >
                                <i class="glyphicon glyphicon-remove"></i>
                            </button>
                        </td>
                    </tr>
                {/foreach}
            </tbody>
            <tfoot>
                <tr class="active">
                    <td colspan="2">
                        <strong>{s name="billiepayment/listing/column/entires"}Einträge:{/s}</strong> {$total}
                    </td>
                    <td colspan="4">
                        <nav aria-label="{s name="billiepayment/listing/navigation/label"}Navigation{/s}" class="pull-right">
                            <ul class="pagination">
                                {if $page > 1}
                                    <li>
                                        <a href="{url controller="BillieOverview" action="index" page=($page -1)}" aria-label="{s name="billiepayment/listing/navigation/prev"}Zurück{/s}">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                {/if}
                                {for $curr=1 to $totalPages step 1}
                                    <li {if $curr == $page}class="active"{/if}>
                                        <a href="{url controller="BillieOverview" action="index" page=$curr}">
                                            {$curr}
                                        </a>
                                    </li>
                                {/for}
                                {if $page < $totalPages}
                                    <li>
                                        <a href="{url controller="BillieOverview" action="index" page=($page + 1)}" aria-label="{s name="billiepayment/listing/navigation/next"}Weiter{/s}">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                {/if}
                            </ul>
                        </nav>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
{/block}
