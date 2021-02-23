{namespace name="backend/billie_overview/index"}
{extends file="parent:backend/_base/billie_layout.tpl"}

{block name="content/main"}
    <div class="page-header">
        <h1>{s name="listing/heading"}{/s}</h1>
    </div>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>{s name="listing/column/order_time"}{/s}</th>
                    <th>{s name="listing/column/order_numer"}{/s}</th>
                    <th>{s name="listing/column/amount"}{/s}</th>
                    <!--th>{s name="listing/column/transaction"}{/s}</th-->
                    <th>{s name="listing/column/company"}{/s}</th>
                    <th>{s name="listing/column/current_state"}{/s}</th>
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
                        <!--td>{$order.transactionId}</td-->
                        <td>{$order.billing.company}</td>
                        <td class="state">
                            {if $order.attribute.billieState == 'created'}
                                {s namespace="backend/billie/states" name="created"}{/s}
                            {elseif $order.attribute.billieState == 'declined'}
                                {s namespace="backend/billie/states" name="declined"}{/s}
                            {elseif $order.attribute.billieState == 'shipped'}
                                {s namespace="backend/billie/states" name="shipped"}{/s}
                            {elseif $order.attribute.billieState == 'paid_out'}
                                {s namespace="backend/billie/states" name="paid_out"}{/s}
                            {elseif $order.attribute.billieState == 'late'}
                                {s namespace="backend/billie/states" name="late"}{/s}
                            {elseif $order.attribute.billieState == 'complete'}
                                {s namespace="backend/billie/states" name="complete"}{/s}
                            {elseif $order.attribute.billieState == 'canceled'}
                                {s namespace="backend/billie/states" name="canceled"}{/s}
                            {/if}
                        </td>
                        <td>
                            <a href="{url controller="BillieOverview" action="order" order_id="{$order.id}"}" class="btn btn-primary">
                                <i class="glyphicon glyphicon-pencil"></i>
                            </a>
                            <button
                                class="btn btn-success ship-order"
                                {if $order.attribute.billieState neq 'created'}disabled="disabled"{/if}
                                data-order_id="{$order.id}"
                                data-action="{url controller="BillieOverview" action="shipOrder"}"
                            >
                                <i class="glyphicon glyphicon-send"></i>
                            </button>
                            <button
                                class="btn btn-danger cancel-order"
                                {if $order.attribute.billieState eq 'canceled' or $order.attribute.billieState eq 'complete'}disabled="disabled"{/if}
                                data-order_id="{$order.id}"
                                data-action="{url controller="BillieOverview" action="cancelOrder"}"
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
                        <strong>{s name="listing/column/entries"}{/s}</strong> {$total}
                    </td>
                    <td colspan="4">
                        <nav aria-label="{s name="listing/navigation/label"}{/s}" class="pull-right">
                            <ul class="pagination">
                                {if $page > 1}
                                    <li>
                                        <a href="{url controller="BillieOverview" action="index" page=($page -1)}" aria-label="{s name="listing/navigation/prev"}{/s}">
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
                                        <a href="{url controller="BillieOverview" action="index" page=($page + 1)}" aria-label="{s name="listing/navigation/next"}{/s}">
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
