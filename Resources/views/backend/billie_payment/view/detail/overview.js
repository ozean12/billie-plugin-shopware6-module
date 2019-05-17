//{block name="backend/order/view/detail/overview"}
// {$smarty.block.parent}
Ext.define('Shopware.apps.BilliePayment.view.detail.Overview', {
    /**
     * Defines an override applied to a class.
     * @string
     */
    override: 'Shopware.apps.Order.view.detail.Overview',

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
                text: '{s name="overview/title" namespace="backend/billie_overview/index"}Billie.io Übersicht{/s}',
                scope: me,
                cls: 'secondary',
                onClick: function() {
                    Shopware.ModuleManager.createSimplifiedModule("BillieOverview/order/?order_id=" + me.record.data.id, { "order_id": me.record.id, "title": "{s name='overview/title' namespace='backend/billie_overview/index'}Billie.io Übersicht{/s}" })
                },
            });

            buttons.unshift(billieBtn);
        }

        return buttons;
    },
});
//{/block}