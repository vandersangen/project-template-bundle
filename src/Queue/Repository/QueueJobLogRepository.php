<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue\Repository;

use VanDerSangen\ProjectTemplateBundle\Queue\Entity\QueueJobLog;
use VanDerSangen\ProjectTemplateBundle\Queue\Enum\QueueJobLogStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QueueJobLog>
 */
class QueueJobLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QueueJobLog::class);
    }

    public function save(QueueJobLog $queueJobLog, bool $flush = false): void
    {
        $this->getEntityManager()->persist($queueJobLog);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return QueueJobLog[]
     */
    public function findByStatus(QueueJobLogStatus $status): array
    {
        return $this->findBy(['status' => $status->value]);
    }

    /**
     * @return QueueJobLog[]
     */
    public function findByMessageClass(string $messageClass): array
    {
        return $this->findBy(['messageClass' => $messageClass]);
    }
}
