<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{link file="backend/_resources/css/bootstrap.min.css"}">
</head>
<body role="document" style="padding-top: 80px">

<!-- Fixed navbar -->
<nav class="navbar navbar-inverse navbar-fixed-top">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a id="test" class="navbar-brand" href="{url controller="BillieOverview" action="index" __csrf_token=$csrfToken}">{s name=billiepayment/overview/title}Billie.io Overview{/s}</a>
        </div>
        <div id="navbar" class="navbar-collapse collapse">
            <ul class="nav navbar-nav">
                <li{if {controllerAction} === 'index'} class="active"{/if}>
                    <a href="{url controller="BillieOverview" action="index" __csrf_token=$csrfToken}">Home</a>
                </li>
            </ul>
        </div><!--/.nav-collapse -->
    </div>
</nav>

<div class="container theme-showcase" role="main">
    {block name="content/main"}{/block}
</div> <!-- /container -->

<script type="text/javascript" src="{link file="backend/base/frame/postmessage-api.js"}"></script>
<script type="text/javascript" src="{link file="backend/_resources/js/jquery-2.1.4.min.js"}"></script>
<script type="text/javascript" src="{link file="backend/_resources/js/bootstrap.min.js"}"></script>

{block name="content/layout/javascript"}
<script type="text/javascript">
    var _BILLIE_SNIPPETS_ = {
        confirm_payment: {
            title: "{s name=billiepayment/snippets/confirm_payment/title}Zahlungsbetrag{/s}",
            desc: "{s name=billiepayment/snippets/confirm_payment/description}Bitte geben Sie Zahlungsbetrag an.{/s}",
        },
        cancel_order: {
            title: "{s name=billiepayment/snippets/cancel_order/title}Bestellung abbrechen{/s}",
            desc: "{s name=billiepayment/snippets/cancel_order/description}Sind Sie sicher, dass Sie die Bestellung über Billie.io stornieren möchten?{/s}",
        }
    };
</script>
{/block}
{block name="content/javascript"}
<script type="text/javascript" src="{link file="backend/_resources/js/billie.js"}"></script>
{/block}

</body>
</html>