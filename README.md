# Billie: Payment After Delivery

![Screenshot Backend Dashboard](./screenshot.png)

## Installation

### Installation via Shopware Store

1. Search for the extension in the Shopware Store: [https://store.shopware.com/](https://store.shopware.com/)
2. Order the extension
3. Download it via the Plugin Manager in your store
4. Click Install
5. Click Activate
6. Clear all Caches (you will be prompted for it)

### Installation via Composer (recommend, for experts)

1. Open the CLI and navigation to the Shopware root
2. Run the following Commands to install the extension via composer:

```bash 
composer req billie/shopware5-payment-module
```

3. Open the Plugin Manager in your store
4. Search for the extension `Billie Payment After Delivery`
5. Click Install
6. Click Activate
7. Clear all Caches (you will be prompted for it)

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

### Version 2.0.2 - Released on 2023-05-08

- remove filter attribute component which does not have any effect anymore (fixes an issue, that the
  attributes-management does not load properly)

### Version 2.0.1 - Released on 2022-06-09

- fix response sending of ajax call on saving updated address (Widget)

### Version 2.0.0 - Released on 2021-11-11

- Replaced outdated SDK for api request with the new SDK (Version 2.x)
- Compatibility to Shopware 5.7
- Compatibility with PHP 8
- Fixed a few bugs

**Please note:** This is the first public release. There has been a few non-public releases before. You can safely
update to this version.
