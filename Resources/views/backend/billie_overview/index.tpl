{extends file="parent:backend/_base/billie_layout.tpl"}

{block name="content/main"}
    <div class="page-header">
        <h1>Bestellungen</h1>
    </div>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Bestell-Zeit</th>
                    <th>Bestellnummer</th>
                    <th>Betrag</th>
                    <th>Transaktion</th>
                    <th>Aktueller Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                {foreach $orders as $order}
                    <tr>
                        <td>{$order.orderTime|date:DATE_SHORT}</td>
                        <td>{$order.number}</td>
                        <td>{$order.invoiceAmount|currency:use_shortname:right}</td>
                        <td>{$order.transactionId}</td>
                        <td>@TODO: Billie Status -> {$order.attribute.ordermod}</td>
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
                        <strong>Einträge:</strong> {$total}
                    </td>
                    <td colspan="4">
                        <nav aria-label="Page navigation" class="pull-right">
                            <ul class="pagination">
                                {if $page > 1}
                                    <li>
                                        <a href="{url controller="BillieOverview" action="index" page=($page -1)}" aria-label="Previous">
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
                                        <a href="{url controller="BillieOverview" action="index" page=($page + 1)}" aria-label="Next">
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
