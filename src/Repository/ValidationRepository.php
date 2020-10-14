<?php

namespace App\Repository;

use App\Entity\Validation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Validation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Validation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Validation[]    findAll()
 * @method Validation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ValidationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Validation::class);
    }

    /**
     * Finds all validations created 30 days ago (DeleteOldFilesCommand::EXPIRY_CONDITION)
     *
     * @param string $expiryDate
     * @return array
     */
    public function findAllToBeArchived($expiryDate)
    {
        return $this->createQueryBuilder('v')
            ->where('v.dateCreation < :expiryDate')
            ->setParameters([
                'expiryDate' => $expiryDate,
            ])
            ->getQuery()
            ->getResult();
    }
}
