<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\EventSubscriber;

use AlliancePay\Logger\AllianceLogger;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;

/**
 * Class TablePrefixSubscriber.
 */
class TablePrefixSubscriber implements EventSubscriber
{
    /**
     * @var string
     */
    private $dbPrefix;

    /**
     * @var AllianceLogger
     */
    private $logger;

    /**
     * @param string $dbPrefix
     */
    public function __construct(string $dbPrefix, AllianceLogger $logger)
    {
        $this->dbPrefix = $dbPrefix;
        $this->logger = $logger;
    }

    /**
     * @return string[]
     */
    public function getSubscribedEvents()
    {
        return [Events::loadClassMetadata];
    }

    /**
     * @param LoadClassMetadataEventArgs $eventArgs
     * @return void
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        $classMetadata = $eventArgs->getClassMetadata();
        $className = $classMetadata->getName();

        if (
            in_array(
                $className,
                [
                    'AlliancePay\Entity\AllianceOrder',
                    'AlliancePay\Entity\RefundOrder'
                ]
            )
        ) {
            $tableName = $classMetadata->getTableName();
            $this->logger->debug("Loading table {$tableName}", ['class' => $className]);

            if (strpos($tableName, $this->dbPrefix) !== 0) {
                $classMetadata->setPrimaryTable([
                    'name' => $this->dbPrefix . $tableName
                ]);
                $eventArgs->getClassMetadata()->setPrimaryTable(['name' => $this->dbPrefix . $tableName]);
                $this->logger->debug(
                    "Loading table {$eventArgs->getClassMetadata()->getTableName()}",
                    ['class' => $eventArgs->getClassMetadata()->getName()]);
            }
        }
    }
}