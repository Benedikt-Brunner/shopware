<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * TODO: Add class description
 */
class RealFlowDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly FlowDispatcher $flowDispatcher,
        private readonly FlowFactory $flowFactory,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'handleBufferedEvents',
        ];
    }

    public function handleBufferedEvents(): void
    {
        do {
            $events = $this->flowDispatcher->bufferedEvents;
            $this->flowDispatcher->bufferedEvents = [];
            foreach ($events as $event) {
                $storableFlow = $this->flowFactory->create($event);
                $this->flowDispatcher->callFlowExecutor($storableFlow);
            }
        } while (!empty($this->bufferedEvents));
    }
}
