<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ListShare;
use App\Entity\ShoppingList;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ListShare>
 */
class ListShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ListShare::class);
    }

    public function findByListAndUser(ShoppingList $list, User $user): ?ListShare
    {
        return $this->findOneBy([
            'shoppingList' => $list,
            'sharedWithUser' => $user,
        ]);
    }

    /**
     * @return ListShare[]
     */
    public function findAllByList(ShoppingList $list): array
    {
        return $this->createQueryBuilder('ls')
            ->where('ls.shoppingList = :list')
            ->setParameter('list', $list)
            ->orderBy('ls.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ListShare[]
     */
    public function findAllByUser(User $user): array
    {
        return $this->createQueryBuilder('ls')
            ->where('ls.sharedWithUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function countByList(ShoppingList $list): int
    {
        return (int) $this->createQueryBuilder('ls')
            ->select('COUNT(ls.id)')
            ->where('ls.shoppingList = :list')
            ->setParameter('list', $list)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
