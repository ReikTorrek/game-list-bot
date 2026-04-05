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

    #[ORM\Column(nullable: true, unique: true)]
    private ?int $steamAppId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $steamName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $steamPrice = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $steamPriceRub = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $steamRecentReviewSummary = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $steamOverallReviewSummary = null;

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

    public function getSteamAppId(): ?int
    {
        return $this->steamAppId;
    }

    public function setSteamAppId(?int $steamAppId): self
    {
        $this->steamAppId = $steamAppId;

        return $this;
    }

    public function getSteamName(): ?string
    {
        return $this->steamName;
    }

    public function setSteamName(?string $steamName): self
    {
        $this->steamName = $steamName;

        return $this;
    }

    public function getSteamPrice(): ?string
    {
        return $this->steamPrice;
    }

    public function setSteamPrice(?string $steamPrice): self
    {
        $this->steamPrice = $steamPrice;

        return $this;
    }

    public function getSteamPriceRub(): ?string
    {
        return $this->steamPriceRub;
    }

    public function setSteamPriceRub(?string $steamPriceRub): self
    {
        $this->steamPriceRub = $steamPriceRub;

        return $this;
    }

    public function getSteamRecentReviewSummary(): ?string
    {
        return $this->steamRecentReviewSummary;
    }

    public function setSteamRecentReviewSummary(?string $steamRecentReviewSummary): self
    {
        $this->steamRecentReviewSummary = $steamRecentReviewSummary;

        return $this;
    }

    public function getSteamOverallReviewSummary(): ?string
    {
        return $this->steamOverallReviewSummary;
    }

    public function setSteamOverallReviewSummary(?string $steamOverallReviewSummary): self
    {
        $this->steamOverallReviewSummary = $steamOverallReviewSummary;

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
