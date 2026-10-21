<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Stores logged REST calls
 */
class LogEntry extends AbstractDb
{
    public const TABLE = 'hryvinskyi_api_log_entry';
    public const ID_FIELD = 'entity_id';

    /**
     * Characters the endpoint path column holds
     */
    public const PATH_MAX_LENGTH = 512;

    /**
     * Bind the resource to its table
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE, self::ID_FIELD);
    }
}
