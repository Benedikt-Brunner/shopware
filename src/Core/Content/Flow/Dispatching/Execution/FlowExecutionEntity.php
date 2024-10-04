<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Execution;

use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Contract\IdAware;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class FlowExecutionEntity extends Entity implements IdAware
{
    use EntityIdTrait;

    protected string $flowId;

    protected ?FlowEntity $flow = null;

    protected bool $successful;

    protected string $errorMessage;

    protected array $triggerContext;

    public function getFlowId(): string
    {
        return $this->flowId;
    }

    public function setFlowId(string $flowId): void
    {
        $this->flowId = $flowId;
    }

    public function getFlow(): ?FlowEntity
    {
        return $this->flow;
    }

    public function setFlow(FlowEntity $flow): void
    {
        $this->flow = $flow;
    }

    public function getSuccessful(): bool
    {
        return $this->successful;
    }

    public function setSuccessful(bool $successful): void
    {
        $this->successful = $successful;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getTriggerContext(): array
    {
        return $this->triggerContext;
    }

    public function setTriggerContext(array $triggerContext): void
    {
        $this->triggerContext = $triggerContext;
    }
}
