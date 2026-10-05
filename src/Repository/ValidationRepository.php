<?php

namespace App\Repository;

use App\Entity\Validation;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @method Validation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Validation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Validation|null findOneByUid(string $uid)
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
     * Update next "pending" validation to "processing" and returns it.
     *
     * @return Validation|null
     */
    public function popNextPending()
    {
        $em = $this->getEntityManager();
        $conn = $em->getConnection();
        $conn->setNestTransactionsWithSavepoints(true);

        $conn->beginTransaction();
        try {
            $conn->executeQuery('LOCK TABLE validation IN ACCESS EXCLUSIVE MODE;');

            /** @var Validation|null $result */
            $result = $this->createQueryBuilder('v')
                ->where('v.status = :status')
                ->setParameter('status', Validation::STATUS_PENDING)
                ->orderBy('v.dateCreation', SortDirection::Ascending)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!is_null($result)) {
                $result->setStatus(Validation::STATUS_PROCESSING);
                $result->setDateStart(new DateTime('now'));
                $em->flush();
                $em->refresh($result);
            }

            $conn->commit();
        } catch (\Throwable $th) {
            // release the table lock
            $conn->rollBack();
            throw $th;
        }

        return $result;
    }

    /**
     * Finds all archivable validations older than expiryDate
     * (validations being processed by a worker are ignored).
     *
     * @return array<Validation>
     */
    public function findAllToBeArchived(DateTime $expiryDate)
    {
        return $this->createQueryBuilder('v')
            ->where('v.dateCreation < :expiryDate')
            ->andWhere('v.status NOT IN (:ignoredStatus)')
            ->setParameter('expiryDate', $expiryDate)
            ->setParameter('ignoredStatus', [Validation::STATUS_ARCHIVED, Validation::STATUS_PROCESSING])
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Finds validations still "processing" after maxDateStart (worker killed, OOM...).
     *
     * @return array<Validation>
     */
    public function findAllInterrupted(DateTime $maxDateStart)
    {
        return $this->createQueryBuilder('v')
            ->where('v.status = :status')
            ->andWhere('v.dateStart < :maxDateStart')
            ->setParameter('status', Validation::STATUS_PROCESSING)
            ->setParameter('maxDateStart', $maxDateStart)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Finds a page of validations, most recent first (see ValidationsController::listValidations).
     *
     * @return array{0: array<Validation>, 1: int} the validations and the total number of matching validations
     */
    public function findPage(int $offset, int $limit, ?string $status = null, ?string $owner = null): array
    {
        $qb = $this->createQueryBuilder('v')
            ->orderBy('v.dateCreation', SortDirection::Descending)
            ->addOrderBy('v.uid', SortDirection::Ascending)
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        if (null !== $status) {
            $qb->andWhere('v.status = :status')->setParameter('status', $status);
        }
        if (null !== $owner) {
            $qb->andWhere('v.owner = :owner')->setParameter('owner', $owner);
        }
        $paginator = new Paginator($qb->getQuery(), false);

        return [iterator_to_array($paginator), count($paginator)];
    }

    /**
     * Drop schema corresponding to input validation.
     *
     * @return bool
     */
    public function dropSchema(Validation $validation)
    {
        $sql = sprintf('DROP SCHEMA IF EXISTS "validation%s" CASCADE', $validation->getUid());
        $reponse = $this->getEntityManager()->getConnection()->executeQuery($sql);

        return 0 != $reponse->rowCount();
    }
}
