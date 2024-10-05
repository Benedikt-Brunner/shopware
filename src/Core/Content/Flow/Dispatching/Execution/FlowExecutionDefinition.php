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

use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceDefinition;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class FlowExecutionDefinition extends EntityDefinition
{

    public const ENTITY_NAME = 'flow_execution';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return FlowExecutionCollection::class;
    }

    public function getEntityClass(): string
    {
        return FlowExecutionEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new BoolField('successful', 'successful'))->addFlags(new Required()),
            (new StringField('error_message', 'errorMessage', 65535))->addFlags(new Required()),
            (new JsonField('event_data', 'eventData'))->addFlags(new Required()),
            (new FkField('flow_id', 'flowId', FlowDefinition::class, 'id'))->addFlags(new Required()),
            new OneToOneAssociationField('flow', 'flow_id', 'id', FlowDefinition::class),
            new FkField('failed_flow_sequence_id', 'failedFlowSequenceId', FlowSequenceDefinition::class, 'id'),
            new OneToOneAssociationField('failedFlowSequence', 'failed_flow_sequence_id', 'id', FlowSequenceDefinition::class),
        ]);
    }
}
