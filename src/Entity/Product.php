<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\NumericFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']],
    security: "is_granted('ROLE_USER')"
)]
#[ApiFilter(SearchFilter::class, properties: ['title' => 'partial', 'content' => 'partial'])]
#[ApiFilter(DateFilter::class, properties: ['createdDate'])]
#[ApiFilter(BooleanFilter::class, properties: ['isPublished'])]
#[ApiFilter(NumericFilter::class, properties: ['price'])]
#[ApiFilter(RangeFilter::class, properties: ['price'])]
#[ApiFilter(ExistsFilter::class, properties: ['media'])]
class Product
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    #[Groups(['product:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['product:read','product:write'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['product:read','product:write'])]
    private ?string $content = null;

    #[ORM\Column]
    #[Groups(['product:read','product:write'])]
    private ?float $price = null;

    #[ORM\Column]
    #[Groups(['product:read','product:write'])]
    private ?bool $isPublished = null;

    #[ORM\Column]
    #[Groups(['product:read'])]
    private ?\DateTimeImmutable $createdDate = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[Groups(['product:read','product:write'])]
    private ?Category $category = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[Groups(['product:read','product:write'])]
    private ?Media $media = null;

    public function __construct()
    {
        $this->createdDate = new \DateTimeImmutable();
    }

    // getters/setters identiques
}
