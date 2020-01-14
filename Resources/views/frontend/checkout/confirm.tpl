{extends file="parent:frontend/checkout/confirm.tpl"}

{block name='frontend_index_header_javascript_tracking'}
    {$smarty.block.parent}
    <script>
        var bcwSrc = '{$billiePayment.widget.src}';
        window.billiePaymentData = {$billiePayment.widget.checkoutData|@json_encode};

        {literal}
        (function(w,d,s,o,f,js,fjs){
            w['BillieCheckoutWidget']=o;w[o]=w[o]||function(){(w[o].q=w[o].q||[]).push(arguments)};
            w.billieSrc=f;js=d.createElement(s);fjs=d.getElementsByTagName(s)[0];js.id=o;
            js.src=f;js.charset='utf-8';js.async=1;fjs.parentNode.insertBefore(js,fjs);bcw('init');
        }(window,document,'script','bcw', bcwSrc));
        {/literal}
    </script>
{/block}

{block name="frontend_checkout_confirm_error_messages"}
    {$smarty.block.parent}
    {if $smarty.get.errorCode}
        {include file="frontend/_includes/messages.tpl" type="error" content=$smarty.get.errorCode|snippet:$errorCode:'frontend/billie_payment/errors'}
    {/if}
{/block}



{block name="frontend_checkout_confirm_submit"}
    {$smarty.block.parent}
    <div id="billie-payment"
         data-billie-payment="true"
         data-checkoutSessionId="{$billiePayment.widget.checkoutSessionId}"
         data-merchantName="{config name=shopName}"
         data-validateAddressUrl="{url module="frontend" controller="BilliePayment" action="validateAddress"}"
         data-defaultErrorMessage="{s namespace="frontend/billie_payment/errors" name="_DefaultErrorMessage"}{/s}"
    ></div>
{/block}
