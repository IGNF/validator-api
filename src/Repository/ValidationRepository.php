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
        $statusParameter = "('" . implode("','", Validation::PENDING_STATUSES) . "')";
        $em = $this->getEntityManager();
        $conn = $em->getConnection();

        $conn->beginTransaction();
        $conn->executeQuery('LOCK TABLE validation IN ACCESS EXCLUSIVE MODE;');

        /** @var Validation|null $result */
        $result = $this->createQueryBuilder('v')
            ->where('v.status IN ' . $statusParameter)
            ->andWhere('NOT v.processing = true') //sadge
            ->orderBy('v.dateCreation', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (!is_null($result)) {
            $result->setProcessing(true);
            $result->setDateStart(new DateTime('now'));
            $em->flush();
            $em->refresh($result);
        }

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
            ->getArrayResult()
        ;
    }

    /**
     * Finds all  validations by user.
     *
     * @param string $uid
     * @return array<Validation>
     */
    public function getByUser(string $uid): mixed
    {
        return $this->createQueryBuilder('v')
            //TODO: ->where(user = uid)
            ->getQuery()
            ->getArrayResult()
        ;
    }
}
