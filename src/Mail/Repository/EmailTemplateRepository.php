<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Mail\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use VanDerSangen\ProjectTemplateBundle\Mail\Entity\EmailTemplate;

/**
 * @extends ServiceEntityRepository<EmailTemplate>
 */
class EmailTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailTemplate::class);
    }

    public function save(EmailTemplate $template, bool $flush = false): void
    {
        $this->getEntityManager()->persist($template);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByOwnerAndKey(string $ownerKey, string $templateKey): ?EmailTemplate
    {
        return $this->findOneBy(['ownerKey' => $ownerKey, 'templateKey' => $templateKey]);
    }

    /**
     * @return EmailTemplate[]
     */
    public function findByOwnerKey(string $ownerKey): array
    {
        return $this->findBy(['ownerKey' => $ownerKey]);
    }
}
