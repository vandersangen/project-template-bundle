<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Cron\Service;

use DateTimeImmutable;
use VanDerSangen\ProjectTemplateBundle\Cron\Entity\Cron;
use VanDerSangen\ProjectTemplateBundle\Cron\Repository\CronRepository;

class CronService
{
    public function __construct(
        private readonly CronRepository $cronRepository,
        private readonly CronScheduleResolver $cronScheduleResolver,
    ) {
    }

    /**
     * @return Cron[]
     */
    public function findAll(): array
    {
        return $this->cronRepository->findBy([], ['name' => 'ASC']);
    }

    public function findById(int $id): ?Cron
    {
        return $this->cronRepository->find($id);
    }

    public function create(array $data): Cron
    {
        $cron = new Cron();
        $cron->setName($data['name']);
        $cron->setCommand($data['command']);
        $cron->setCommandArguments($data['commandArguments'] ?? null);
        $cron->setSchedule($data['schedule']);
        $cron->setEnabled($data['enabled'] ?? true);
        $cron->setTimezone($data['timezone'] ?? 'UTC');
        $cron->setNextRunAt($this->cronScheduleResolver->getNextRunAt($cron, new DateTimeImmutable()));
        $this->cronRepository->save($cron, true);
        return $cron;
    }

    public function update(Cron $cron, array $data): Cron
    {
        $cron->setName($data['name']);
        $cron->setCommand($data['command']);
        $cron->setCommandArguments($data['commandArguments'] ?? null);
        $cron->setSchedule($data['schedule']);
        $cron->setEnabled($data['enabled'] ?? true);
        $cron->setTimezone($data['timezone'] ?? 'UTC');
        $cron->setNextRunAt($this->cronScheduleResolver->getNextRunAt($cron, new DateTimeImmutable()));
        $this->cronRepository->save($cron, true);
        return $cron;
    }

    public function delete(Cron $cron): void
    {
        $this->cronRepository->remove($cron, true);
    }
}
