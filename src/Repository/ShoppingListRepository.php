<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShoppingList;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShoppingList>
 */
class ShoppingListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShoppingList::class);
    }

    public function findUserDefaultList(User $user): ?ShoppingList
    {
        return $this->findOneBy([
            'user' => $user,
            'isDefault' => true,
        ]);
    }

    public function countUserLists(User $user): int
    {
        return (int) $this->createQueryBuilder('sl')
            ->select('COUNT(sl.id)')
            ->where('sl.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findFirstNonDefaultList(User $user): ?ShoppingList
    {
        return $this->createQueryBuilder('sl')
            ->where('sl.user = :user')
            ->andWhere('sl.isDefault = false')
            ->setParameter('user', $user)
            ->orderBy('sl.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
