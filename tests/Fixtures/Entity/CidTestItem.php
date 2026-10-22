<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Test-only entity with a cid field, used to test master data loading by cid.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cid_test_items')]
class CidTestItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private string $cid = '';

    #[ORM\Column(type: 'string', length: 100)]
    private string $name = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCid(): string
    {
        return $this->cid;
    }

    public function setCid(string $cid): void
    {
        $this->cid = $cid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
