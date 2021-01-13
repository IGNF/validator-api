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
     * Finds the next pending validation
     *
     * @return Validation
     */
    public function findNextPending()
    {
        $results = $this->createQueryBuilder('v')
            ->where('v.status = :status')
            ->setParameters(['status' => Validation::STATUS_PENDING])
            ->orderBy('v.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();

        if (empty($results)) {
            return null;
        }

        return $results[0];
    }

    /**
     * Finds all validations created 30 days ago (DeleteOldFilesCommand::EXPIRY_CONDITION)
     *
     * @param string $expiryDate
     * @return array[Validation]
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
