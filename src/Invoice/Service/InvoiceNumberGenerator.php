<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Service;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use VanDerSangen\ProjectTemplateBundle\Invoice\Entity\InvoiceNumberSequence;

/**
 * Hands out gap-free, sequential invoice numbers per owner and year. The
 * sequence row is locked pessimistically so concurrent generation cannot
 * produce duplicate numbers.
 */
class InvoiceNumberGenerator
{
    private const int PAD_LENGTH = 5;
    private const int MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function next(string $ownerKey, int $year, string $prefix = ''): string
    {
        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                $counter = $this->em->wrapInTransaction(
                    fn(): int => $this->incrementLocked($ownerKey, $year),
                );

                return sprintf(
                    '%s%d-%s',
                    $prefix,
                    $year,
                    str_pad((string) $counter, self::PAD_LENGTH, '0', STR_PAD_LEFT),
                );
            } catch (UniqueConstraintViolationException $e) {
                // Lost the race creating the first row for this owner/year; retry.
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw new RuntimeException('Could not allocate an invoice number', 0, $e);
                }
            }
        }
    }

    private function incrementLocked(string $ownerKey, int $year): int
    {
        $sequence = $this->em->createQuery(
            'SELECT s FROM ' . InvoiceNumberSequence::class . ' s'
            . ' WHERE s.ownerKey = :ownerKey AND s.year = :year',
        )
            ->setParameter('ownerKey', $ownerKey)
            ->setParameter('year', $year)
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        if (!$sequence instanceof InvoiceNumberSequence) {
            $sequence = new InvoiceNumberSequence();
            $sequence->setOwnerKey($ownerKey);
            $sequence->setYear($year);
            $this->em->persist($sequence);
        }

        $counter = $sequence->increment();
        $this->em->flush();

        return $counter;
    }
}
