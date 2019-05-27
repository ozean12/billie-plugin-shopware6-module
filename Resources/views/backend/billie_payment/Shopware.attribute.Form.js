//{block name="backend/base/attribute/form"}

//{$smarty.block.parent}

Ext.define('BilliePayment.FieldHandler', {
    extend: 'Shopware.attribute.FieldHandlerInterface',

    /**
     * @override
     * @param { Shopware.model.AttributeConfig } attribute
     * @returns { boolean }
     */
    supports: function (attribute) {
        var name = attribute.get('columnName');

        if (attribute.get('tableName') !== 's_user_attributes') {
            return false;
        }

        return (name === 'billie_bic' || name === 'billie_iban');
    },

    /**
     * Change BIC and IBAN to ReadOnly
     * @override
     * @param { Object } field
     * @param { Shopware.model.AttributeConfig } attribute
     * @returns { object }
     */
    create: function (field, attribute) {
        return Ext.apply(field, {
            xtype: 'textfield',
            // disabled: true,
            readOnly: true
        });
    }
});

Ext.define('Shopware.attribute.Form-BilliePayment', {
    override: 'Shopware.attribute.Form',

    registerTypeHandlers: function () {
        var handlers = this.callParent(arguments);
        return Ext.Array.insert(handlers, 0, [Ext.create('BilliePayment.FieldHandler')]);
    }
});

//{/block}