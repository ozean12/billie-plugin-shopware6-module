{block name='billie_payment'}
    <h2>Hello {$firstName} {$lastName}</h2>
    <p>Do you want to pay {$amount} {$currency} with this example payment provider?</p>

    <hr />
    <h3>API Settings</h3>
    <pre><code>{$config|@json_encode}</code></pre>

    <hr />
    <a href="{$returnUrl}" title="pay {$amount} {$currency}">pay {$amount} {$currency}</a>
    <br/>
    <a href="{$cancelUrl}" title="cancel payment">cancel payment</a>
{/block}