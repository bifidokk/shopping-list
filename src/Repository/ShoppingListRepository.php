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

    /**
     * Find all lists accessible by user (owned or shared with them).
     *
     * @return ShoppingList[]
     */
    public function findAllAccessibleByUser(User $user): array
    {
        return $this->createQueryBuilder('sl')
            ->leftJoin('sl.shares', 's')
            ->where('sl.owner = :user')
            ->orWhere('s.sharedWithUser = :user')
            ->setParameter('user', $user)
            ->groupBy('sl.id')
            ->orderBy('sl.isDefault', 'DESC')
            ->addOrderBy('sl.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function hasAccess(int $listId, User $user): bool
    {
        $result = $this->createQueryBuilder('sl')
            ->select('COUNT(sl.id)')
            ->leftJoin('sl.shares', 's')
            ->where('sl.id = :listId')
            ->andWhere('(sl.owner = :user OR s.sharedWithUser = :user)')
            ->setParameter('listId', $listId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    public function isOwner(int $listId, User $user): bool
    {
        $result = $this->createQueryBuilder('sl')
            ->select('COUNT(sl.id)')
            ->where('sl.id = :listId')
            ->andWhere('sl.owner = :user')
            ->setParameter('listId', $listId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }
}
