<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShoppingList;
use App\Entity\User;
use App\Entity\UserDefaultList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserDefaultList>
 */
class UserDefaultListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserDefaultList::class);
    }

    public function findByUser(User $user): ?UserDefaultList
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function findDefaultListIdForUser(User $user): ?int
    {
        $result = $this->createQueryBuilder('udl')
            ->select('sl.id')
            ->join('udl.shoppingList', 'sl')
            ->where('udl.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['id'] ?? null;
    }

    public function setUserDefaultList(User $user, ShoppingList $list): UserDefaultList
    {
        $userDefault = $this->findByUser($user);

        if (!$userDefault) {
            $userDefault = new UserDefaultList();
            $userDefault->setUser($user);
            $this->getEntityManager()->persist($userDefault);
        }

        $userDefault->setShoppingList($list);
        $userDefault->setUpdatedAt(new \DateTime());

        return $userDefault;
    }

    public function removeUserDefault(User $user): void
    {
        $userDefault = $this->findByUser($user);

        if ($userDefault) {
            $this->getEntityManager()->remove($userDefault);
        }
    }
}
