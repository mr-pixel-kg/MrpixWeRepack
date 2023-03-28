<?php

namespace Mrpix\WeRepack\Subscriber;

use Mrpix\WeRepack\Components\WeRepackSession;
use Mrpix\WeRepack\Service\OrderService;
use Mrpix\WeRepack\Service\PromotionService;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckoutConfirmSubscriber implements EventSubscriberInterface
{
    private WeRepackSession $session;
    private OrderService $orderService;
    private PromotionService $promotionService;

    public function __construct(OrderService $orderService, PromotionService $promotionService)
    {
        $this->session = new WeRepackSession();
        $this->orderService = $orderService;
        $this->promotionService = $promotionService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirmPageLoad',
            CheckoutOrderPlacedEvent::class => 'onCheckoutOrderPlaced',
            SalesChannelContextSwitchEvent::class => 'onSalesChannelContextSwitch',
            'state_machine.order_transaction.state_changed' => 'onOrderStateChanged',
        ];
    }

    public function onCheckoutConfirmPageLoad(CheckoutConfirmPageLoadedEvent $event)
    {
        $event->getPage()->addArrayExtension('MrpixWeRepack', [
            'weRepackEnabled' => $this->session->isWeRepackEnabled()
        ]);
    }

    public function onCheckoutOrderPlaced(CheckoutOrderPlacedEvent $event)
    {
        // Write WeRepack data to database
        $this->orderService->writeWeRepackOrder($event->getOrder(), $this->session->isWeRepackEnabled(), $event->getContext());

        // Clear session
        $this->session->clear();
    }

    public function onOrderStateChanged(StateMachineStateChangeEvent $event)
    {
        $name = $event->getNextState()->getTechnicalName();
        if ($name !== 'paid') {
            return;
        }

        $order = $this->orderService->getOrderByTransition($event->getTransition(), $event->getContext());
        if ($order === null) {
            return;
        }

        // if customer selected WeRepack option and WeRepack is enabled for next order, create promotion code
        if(!$this->configService->get('createPromotionCodes')
            || $this->configService->get('couponSendingType') != 'order'
            || !$order->getExtension('repackOrder')->isRepack()) {
            return;
        }

        // event can be triggered multiple times, but only create promotion code one time
        if($order->getExtension('repackOrder')->getPromotionIndividualCodeId() != null){
            return;
        }

        // create promotion code
        $this->promotionService->createPromotionIndividualCode($order, $event->getContext());

        // send promotion code to customer

        dump('Todo: Send mail', $order);
        /*$this->mailerService->sendVoucherToCustomer(
            $order,
            $order->getSalesChannelId(),
            $event->getContext()
        );*/
    }

    public function onSalesChannelContextSwitch(SalesChannelContextSwitchEvent $event)
    {
        // Toggle WeRepack checkbox only if event is triggered by checkbox
        if($event->getRequestDataBag()->get('mrpixWeRepackToggle') == 1) {
            $this->session->setWeRepackEnabled(!$this->session->isWeRepackEnabled());
        }

        /*
         * Alternatively can be checked if the WeRepack option is enabled:
         * $weRepackEnabled= $event->getRequestDataBag()->get('mrpixWeRepack');
         *
         * because the form field 'mrpixWeRepack' is only sent when the checkbox is enabled
         */
    }
}