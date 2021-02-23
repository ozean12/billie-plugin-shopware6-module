{namespace name="backend/_base/billie_layout"}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{link file="backend/_resources/css/bootstrap.min.css"}">
    <style>
        [disabled] { cursor: not-allowed; }
    </style>
</head>
<body role="document" style="padding-top: 80px">

<!-- Fixed navbar -->
<nav class="navbar navbar-inverse navbar-fixed-top" style="min-height: 64px;">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="{url controller="BillieOverview" action="index" __csrf_token=$csrfToken}">
                <img class="img-responsive" src="{link file="backend/_resources/images/billie_logo_horizontal.png"}" style="display: inline-block" /> {s name="navbar/title"}Overview{/s}
            </a>
        </div>
    </div>
</nav>

<div class="container theme-showcase" role="main">
    {if $errorCode}
        <div class="alert alert-danger" role="alert">{$errorCode|snippet:$errorCode:'backend/billie_overview/messages'}</div>
    {/if}
    {block name="content/main"}{/block}
</div> <!-- /container -->

<script type="text/javascript" src="{link file="backend/base/frame/postmessage-api.js"}"></script>
<script type="text/javascript" src="{link file="backend/_resources/js/jquery-2.1.4.min.js"}"></script>
<script type="text/javascript" src="{link file="backend/_resources/js/bootstrap.min.js"}"></script>

{block name="content/layout/javascript"}
<script type="text/javascript">
    var _BILLIE_SNIPPETS_ = {
        confirm_payment: {
            title: '{s name="confirm_payment/title"}{/s}',
            desc: '{s name="confirm_payment/description"}{/s}',
            success: '{s name="confirm_payment/success"}{/s}',
        },
        cancel_order: {
            title: '{s name="cancel_order/title"}{/s}',
            desc: '{s name="cancel_order/description"}{/s}',
            success: '{s name="cancel_order/success"}{/s}',
        },
        refund_order: {
            title: '{s name="refund_order/title"}{/s}',
            desc: '{s name="refund_order/description"}{/s}',
            success: '{s name="refund_order/success"}{/s}',
        },
        ship_order: {
            success: '{s name="ship_order/success"}{/s}',
            external_invoice_placeholder: '{s name="ship_order/external_invoice_placeholder"}{/s}',
            external_url_placeholder: '{s name="ship_order/external_url_placeholder"}{/s}',
            add_external_invoice: '{s name="ship_order/add_external_invoice"}{/s}',
            missing_invoice_number: '{s name="ship_order/missing_invoice_number"}{/s}',
        },
        states: {
            created: '{s name="created" namespace="backend/billie/states"}{/s}',
            declined: '{s name="declined" namespace="backend/billie/states"}{/s}',
            shipped: '{s name="shipped" namespace="backend/billie/states"}{/s}',
            paid_out: '{s name="paid_out" namespace="backend/billie/states"}{/s}',
            late: '{s name="late" namespace="backend/billie/states"}{/s}',
            complete: '{s name="complete" namespace="backend/billie/states"}{/s}',
            canceled: '{s name="canceled" namespace="backend/billie/states"}{/s}'
        },
        errorCodes: {
            error: '{s name="error" namespace="backend/billie_overview/messages"}{/s}',
            success: '{s name="success" namespace="backend/billie_overview/messages"}{/s}',
            InvalidCommandException: '{s name="InvalidCommandException" namespace="backend/billie_overview/messages"}{/s}',
            INVALID_REQUEST: '{s name="INVALID_REQUEST" namespace="backend/billie_overview/messages"}{/s}',
            NOT_ALLOWED: '{s name="NOT_ALLOWED" namespace="backend/billie_overview/messages"}{/s}',
            NOT_AUTHORIZED: '{s name="NOT_AUTHORIZED" namespace="backend/billie_overview/messages"}{/s}',
            SERVER_ERROR: '{s name="SERVER_ERROR" namespace="backend/billie_overview/messages"}{/s}',
            ORDER_NOT_CANCELLED: '{s name="ORDER_NOT_CANCELLED" namespace="backend/billie_overview/messages"}{/s}',
            ORDER_CANNOT_BE_SHIPPED: '{s name="ORDER_CANNOT_BE_SHIPPED" namespace="backend/billie_overview/messages"}{/s}',
            MISSING_DOCUMENTS: '{s name="MISSING_DOCUMENTS" namespace="backend/billie_overview/messages"}{/s}'
        }
    };
</script>
{/block}
{block name="content/javascript"}
<script type="text/javascript" src="{link file="backend/_resources/js/billie.js"}"></script>
{/block}

</body>
</html>
