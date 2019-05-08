//{block name="backend/order/view/detail/overview"}
// {$smarty.block.parent}
Ext.define('Shopware.apps.BilliePayment.view.detail.Overview', {
    /**
     * Defines an override applied to a class.
     * @string
     */
    override: 'Shopware.apps.Order.view.detail.Overview',

    // initComponent: function () {
    //     var me = this;

    //     me.registerEvents();

    //     if (me.record.data.id >= 1) {
    //         Ext.Ajax.request({
    //             url: '{url controller=AttributeData action=loadData}',
    //             params: {
    //                 _foreignKey: me.record.data.id,
    //                 _table: 's_order_attributes'
    //             },
    //             success: function (responseData, request) {
    //                 var response = Ext.JSON.decode(responseData.responseText);

    //                 console.log(response)
    //             }
    //         });
    //     }

    //     me.callParent(arguments);
    // },

    // createEditElements: function () {
    //     var me = this;

    //     var fields = me.callParent(arguments);

    //     var testField = {
    //         fieldLabel: 'Test Label',
    //         name: 'attribute[ordermod]',
    //         xtype: 'textfield',
    //     };

    //     fields = Ext.Array.insert(fields, 1, [testField]);
    //     return fields;
    // },

    /**
     * Add a Button to display a window with billie.io informations.
     * 
     * @returns Array - Contains the cancel button and the save button
     */
    getEditFormButtons: function () {
        var me = this,
            buttons = me.callParent(arguments);

        if (me.record.raw.payment.action == 'BilliePayment') {
            var billieBtn = Ext.create('Ext.button.Button', {
                text: '{s name=billiepayment/overview/button}Billie.io Übersicht{/s}',
                scope: me,
                cls: 'secondary',
                onClick: function() {
                    Shopware.ModuleManager.createSimplifiedModule("BillieOverview/?order_id=" + me.record.data.id, { "order_id": me.record.id, "title": "{s name=billiepayment/overview/title}Billie.io Übersicht{/s}" })
                },
            });

            buttons.unshift(billieBtn);
        }

        return buttons;
    },
});
//{/block}