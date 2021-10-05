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

## Release notes

### Version 2.0.0 - Released on 2021-10-05

- Replaced outdated SDK for api request with the new SDK (Version 2.x)
- Compatiblity to Shopware 5.7
- Compatiblity with PHP 8
- Fixed a few bugs

**Please note:** This is the first public release. There has been a few non-public releases before. You can safly update to this version.
