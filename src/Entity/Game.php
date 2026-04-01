<?php

namespace App\Entity;

use App\Enum\GameStatus;
use App\Repository\GameRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 2048)]
    private string $url;

    #[ORM\Column(type: Types::TEXT)]
    private string $descriptionText;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $descriptionEntities = null;

    #[ORM\Column(length: 20, enumType: GameStatus::class)]
    private GameStatus $status = GameStatus::NEW;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getDescriptionText(): string
    {
        return $this->descriptionText;
    }

    public function setDescriptionText(string $descriptionText): self
    {
        $this->descriptionText = $descriptionText;

        return $this;
    }

    public function getDescriptionEntities(): ?array
    {
        return $this->descriptionEntities;
    }

    public function setDescriptionEntities(?array $descriptionEntities): self
    {
        $this->descriptionEntities = $descriptionEntities;

        return $this;
    }

    public function getStatus(): GameStatus
    {
        return $this->status;
    }

    public function setStatus(GameStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
