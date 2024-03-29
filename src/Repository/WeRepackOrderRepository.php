<?php

namespace Mrpix\WeRepack\Repository;

use DateTimeImmutable;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class WeRepackOrderRepository
{
    public function __construct(private readonly EntityRepository $werepackOrderRepository)
    {
    }

    public function getWeRepackOrderCount(Context $context): int
    {
        return $this->werepackOrderRepository->search(new Criteria(), $context)->count();
    }

    public function getWeRepackRatio(Context $context): float
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isRepack', true));
        $repackOrders = $this->werepackOrderRepository->search($criteria, $context)->count();
        $totalOrders = $this->werepackOrderRepository->search(new Criteria(), $context)->count();
        if ($totalOrders === 0) {
            return 0;
        }
        return round($repackOrders * 100 / $totalOrders);
    }

    public function getWeRepackStart(Context $context): DateTimeImmutable
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $entityResult = $this->werepackOrderRepository->search($criteria, $context)->first();
        return $entityResult->getCreatedAt();
    }
}