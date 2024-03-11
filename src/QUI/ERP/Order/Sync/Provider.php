<?php

namespace QUI\ERP\Order\Sync;

use QUI\Sync\Entity\SyncTarget;
use QUI\Sync\Provider\SyncProviderInterface;

/**
 * quiqqer/sync provider for orders.
 */
class Provider implements SyncProviderInterface
{
    /**
     * Validate if syncing to the given system is possible.
     *
     * @param SyncTarget $SyncTarget
     * @return void
     */
    public function validateSync(SyncTarget $SyncTarget): void
    {
        // TODO: Implement validateSync() method.
    }

    /**
     * Push data to target system.
     *
     * @param SyncTarget $SyncTarget
     * @return void
     */
    public function syncToTarget(SyncTarget $SyncTarget): void
    {
        // TODO: Implement syncToTarget() method.
    }
}
