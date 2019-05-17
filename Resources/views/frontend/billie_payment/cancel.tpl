{extends file='frontend/index/index.tpl'}
{namespace name="frontend/billie_payment/cancel"}


{* Main content *}
{block name='frontend_index_content'}
    <div class="cancel-content content custom-page--content">
        <div class="cancel-content--actions">
            <h3 class="heading">{s name="something_went_wrong"}Something went wrong!{/s}</h3>
            <a class="btn"
               href="{url controller=checkout action=cart}"
               title="change cart">{s name="change/cart"}Change Cart{/s}
            </a>
            <a class="btn is--primary right"
               href="{url controller=checkout action=shippingPayment sTarget=checkout}"
               title="change payment method">{s name="change/payment"}Change Payment Method[{/s}
            </a>
        </div>
    </div>
{/block}

{block name='frontend_index_actions'}{/block}
