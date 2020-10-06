<?php

namespace BilliePayment\Subscriber;

use BilliePayment\Components\Payment\Service;
use Enlight\Event\SubscriberInterface;

/**
 * Payment Subscriber to handle duration attribute
 */
class Payment implements SubscriberInterface
{
    /**
     * @var Service
     */
    private $service;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PreDispatch_Backend_AttributeData' => 'onSaveAttributeData',
            'Enlight_Controller_Action_PostDispatchSecure_Backend_AttributeData' => 'onLoadAttributeData',
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Payment' => 'onLoadAttributeData',
        ];
    }

    /**
     * Unset duration if payment means is not of type billie.
     *
     * @return void
     */
    public function onSaveAttributeData(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $request = $controller->Request();
        $query = ['id' => $request->getParam('_foreignKey')];

        if ($request->getActionName() == 'saveData' && !$this->service->isBilliePayment($query)) {
            $view = $controller->View();
            $request->setParam('__attribute_billie_duration', '');
            $view->assign('success', false);
        }
    }

    /**
     * Unset duration if payment means is not of type billie.
     *
     * @return void
     */
    public function onLoadAttributeData(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $request = $controller->Request();
        $view = $controller->View();
        $query = ['id' => $view->data['__attribute_paymentmeanID']];
        $data = $view->data;

        // Unset billie duration on load attribute data
        if ($request->getActionName() == 'loadData' && !$this->service->isBilliePayment($query)) {
            unset($data['__attribute_billie_duration']);
        }

        // Unset billie duration on getting payments
        if ($request->getActionName() == 'getPayments') {
            foreach ($data as $key => $payment) {
                if (!$this->service->isBilliePayment(['name' => $payment['name']])) {
                    unset($data[$key]['attribute']['billieDuration']);
                }
            }
        }

        $view->assign('data', $data);
    }
}
