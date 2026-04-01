<?php

namespace App\Repository;

use App\Entity\Game;
use App\Enum\GameStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /**
     * @return list<Game>
     */
    public function findByStatus(GameStatus $status): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.status = :status')
            ->setParameter('status', $status)
            ->orderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
