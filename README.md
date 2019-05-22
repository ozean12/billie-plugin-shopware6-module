# Billie: Payment After Delivery

![Screenshot Backend Dashboard](./screenshot.png)

## Configuration
### Street & Housenumber
* Use `additional_address_line1` as housenumber! *(Has to be actived in shopware)*
* Set the values for the following snippets under `frontend/register/billing_fieldset` and `frontend/register/shipping_fieldset` accordingly
  * `RegisterLabelAdditionalAddressLine1` -> House Number
  * `RegisterBillingPlaceholderStreet` -> Street

## Displaying Attributes in Documents
To display the billie in the invoice document etc, they can be accessed in the document template files like so:

~~~html
IBAN {$Order._order.attributes.billie_iban}
~~~

Available Attributes are:
* `billie_iban`
* `billie_bic`
* `billie_state`

## API Endpoint for getting Invoice PDFs

`POST` `https://example.com/BillieInvoice/invoice/hash/[HASH]`

Data:

  * `[HASH]`: Document Hash -> Full URL with hash send to billie on shipping event
  * `apikey`: Billie API Key *(secret, only billie and user knows this)*
  * `invoiceNumber`: Used to validate hash, send to billie on shipping event. Additional security measure to reduce possible collision attacks

Return:      
  * `application/pdf` *(Statuscode 200)*, if authenticated and document was found
  * `Statuscode 401`: unauthorized -> wrong api key or invalid `invoiceNumber` and `hash` combination
  * `Statuscode 404`: Document was not found on the server

Example Call to get an invoice pdf:
```bash
curl -d "invoiceNumber=20007&apikey=test-ralph" -X POST http://billie.test/BillieInvoice/invoice/hash/8b192ddef8ef1a32f9cf44c871712b30 > test.pdf
```
