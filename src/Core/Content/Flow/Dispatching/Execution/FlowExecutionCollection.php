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

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<FlowExecutionEntity>
 */
class FlowExecutionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'flow_execution_collection';
    }

    protected function getExpectedClass(): string
    {
        return FlowExecutionEntity::class;
    }
}
