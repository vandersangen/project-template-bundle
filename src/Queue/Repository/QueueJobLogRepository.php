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
     * Writes the output so far straight to the line, outside the unit of work: called while a handler runs, at most
     * every second.
     *
     * @param int    $queueJobLogId The line.
     * @param string $stdout        All output so far.
     *
     * @return void
     */
    public function saveOutputSoFar(int $queueJobLogId, string $stdout): void
    {
        $this->getEntityManager()->getConnection()->update(
            'queue_job_logs',
            ['stdout' => $stdout],
            ['id' => $queueJobLogId],
        );
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
