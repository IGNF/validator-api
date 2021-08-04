<?php

namespace App\Repository;

use App\Entity\Validation;
use DateTime;
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
     * Update next "pending" validation to "processing" and returns it
     *
     * @return Validation|null
     */
    public function popNextPending()
    {
        $em = $this->getEntityManager();
        $conn = $em->getConnection();

        $conn->beginTransaction();
        $conn->executeQuery('LOCK TABLE validation IN ACCESS EXCLUSIVE MODE;');

        /** @var Validation|null $result */
        $result = $this->createQueryBuilder('v')
            ->where('v.status = :status')
            ->setParameters(['status' => Validation::STATUS_PENDING])
            ->orderBy('v.dateCreation', 'ASC')
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (is_null($result)) {
            return $result;
        }

        $result->setStatus(Validation::STATUS_PROCESSING);
        $em->flush($result);

        $conn->commit();
        return $result;
    }

    /**
     * Finds all archivable validations older than expiryDate.
     *
     * @param DateTime $expiryDate
     * @return array<Validation>
     */
    public function findAllToBeArchived(DateTime $expiryDate)
    {
        return $this->createQueryBuilder('v')
            ->where('v.dateCreation < :expiryDate')
            ->andWhere('v.status != :ignoredStatus')
            ->setParameters([
                'expiryDate' => $expiryDate,
                'ignoredStatus' => Validation::STATUS_ARCHIVED,
            ])
            ->getQuery()
            ->getResult()
        ;
    }
}
