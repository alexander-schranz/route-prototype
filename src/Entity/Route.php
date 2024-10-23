<?php

namespace App\Entity;

use App\Repository\RouteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
#[ORM\UniqueConstraint(name: 'route_unique', fields: ['site', 'locale', 'slug'])]
#[ORM\UniqueConstraint(name: 'resource_unique', fields: ['locale', 'resourceKey', 'resourceId'])]
class Route
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $site = null;

    #[ORM\Column(length: 11)]
    private ?string $locale = null;

    #[ORM\Column(type: Types::STRING)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $parentSlug = null;

    #[ORM\Column(length: 32)]
    private ?string $resourceKey = null;

    #[ORM\Column(length: 48)]
    private ?string $resourceId = null;

    public function __construct(string $resourceKey, string $resourceId, string $locale, string $slug, ?string $site = null, ?string $parentSlug = null)
    {
        $this->resourceKey = $resourceKey;
        $this->resourceId = $resourceId;
        $this->locale = $locale;
        $this->slug = $slug;
        $this->site = $site;
        $this->parentSlug = $parentSlug;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): ?string
    {
        return $this->site;
    }

    public function setSite(?string $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getParentSlug(): ?string
    {
        return $this->parentSlug;
    }

    public function setParentSlug(?string $parentSlug): static
    {
        $this->parentSlug = $parentSlug;

        return $this;
    }

    public function getResourceKey(): ?string
    {
        return $this->resourceKey;
    }

    public function setResourceKey(string $resourceKey): static
    {
        $this->resourceKey = $resourceKey;

        return $this;
    }

    public function getResourceId(): ?string
    {
        return $this->resourceId;
    }

    public function setResourceId(string $resourceId): static
    {
        $this->resourceId = $resourceId;

        return $this;
    }
}
