# Billie

## Displaying Attributes in Documents
To display the billie in the invoice document etc, they can be accessed like

~~~html
IBAN {$Order._order.attributes.billie_iban}
~~~

Available Attributes are:
* `billie_iban`
* `billie_bic`
* `billie_state`