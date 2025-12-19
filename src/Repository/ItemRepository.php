<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Item;
use App\Entity\ShoppingList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Item>
 */
class ItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    /**
     * Get item counts for multiple shopping lists.
     *
     * @param ShoppingList[] $shoppingLists
     *
     * @return array<int, array{total: int, completed: int}>
     */
    public function getItemCountsForLists(array $shoppingLists): array
    {
        if (empty($shoppingLists)) {
            return [];
        }

        $listIds = array_map(fn (ShoppingList $list) => $list->getId(), $shoppingLists);

        $result = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.shoppingList) as list_id')
            ->addSelect('COUNT(i.id) as total')
            ->addSelect('SUM(CASE WHEN i.isDone = true THEN 1 ELSE 0 END) as completed')
            ->where('i.shoppingList IN (:listIds)')
            ->setParameter('listIds', $listIds)
            ->groupBy('i.shoppingList')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[(int) $row['list_id']] = [
                'total' => (int) $row['total'],
                'completed' => (int) $row['completed'],
            ];
        }

        return $counts;
    }

    public function countItemsForList(ShoppingList $shoppingList): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.shoppingList = :list')
            ->setParameter('list', $shoppingList)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countCompletedItemsForList(ShoppingList $shoppingList): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.shoppingList = :list')
            ->andWhere('i.isDone = true')
            ->setParameter('list', $shoppingList)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
