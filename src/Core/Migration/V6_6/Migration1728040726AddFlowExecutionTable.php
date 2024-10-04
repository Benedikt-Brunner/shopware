<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1728040726AddFlowExecutionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1728040726;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                CREATE TABLE `flow_execution` (
                    `id` binary(16) NOT NULL,
                    `flow_id` binary(16) NOT NULL,
                    `created_at` datetime(3) NOT NULL,
                    `updated_At` datetime(3) NOT NULL,
                    `trigger_context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`trigger_context`)),
                    `successful` tinyint(1) NOT NULL,
                    `error_message` text NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // TODO: implement
    }
}
