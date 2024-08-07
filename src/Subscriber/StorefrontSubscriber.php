<?php

declare(strict_types=1);

namespace Mrpix\WeRepack\Subscriber;

use Mrpix\WeRepack\Service\PromotionService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\GenericPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StorefrontSubscriber implements EventSubscriberInterface
{
    private SystemConfigService $configService;
    private PromotionService $promotionService;

    public function __construct(SystemConfigService $configService, PromotionService $promotionService)
    {
        $this->configService = $configService;
        $this->promotionService = $promotionService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            GenericPageLoadedEvent::class => 'onPageLoaded',
        ];
    }

    public function onPageLoaded(GenericPageLoadedEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
        if (!$this->configService->get('MrpixWeRepack.config.createPromotionCodes', $salesChannelId) ||
            'cart' !== $this->configService->get('MrpixWeRepack.config.couponSendingType', $salesChannelId) ||
            !$this->configService->get('MrpixWeRepack.config.repackPromotion', $salesChannelId)) {
            return;
        }

        $promotion = $this->promotionService->getPromotion($event->getContext(), $salesChannelId);
        if (null === $promotion) {
            return;
        }

        $page = $event->getPage();
        $page->setExtensions(['repackPromotion' => $promotion]);
    }
}
