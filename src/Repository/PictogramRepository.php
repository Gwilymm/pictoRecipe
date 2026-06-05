<?php

namespace App\Repository;

use App\Entity\Pictogram;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pictogram>
 */
class PictogramRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, Pictogram::class);
	}

	/**
	 * @return Pictogram[]
	 */
	public function findSearchableByKeyword(string $keyword, int $limit = 50): array
	{
		$keyword = mb_strtolower(trim($keyword));
		if ($keyword === '') {
			return [];
		}

		$qb = $this->createQueryBuilder('p');

		return $qb
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->like('LOWER(p.name)', ':keyword'),
					$qb->expr()->like('LOWER(p.label)', ':keyword')
				)
			)
			->andWhere(
				$qb->expr()->orX(
					'p.source != :wikimediaSource',
					'p.validated = true'
				)
			)
			->setParameter('keyword', '%' . $keyword . '%')
			->setParameter('wikimediaSource', Pictogram::SOURCE_WIKIMEDIA_COMMONS)
			->orderBy('p.name', 'ASC')
			->setMaxResults($limit)
			->getQuery()
			->getResult();
	}

	//    /**
	//     * @return Pictogram[] Returns an array of Pictogram objects
	//     */
	//    public function findByExampleField($value): array
	//    {
	//        return $this->createQueryBuilder('p')
	//            ->andWhere('p.exampleField = :val')
	//            ->setParameter('val', $value)
	//            ->orderBy('p.id', 'ASC')
	//            ->setMaxResults(10)
	//            ->getQuery()
	//            ->getResult()
	//        ;
	//    }

	//    public function findOneBySomeField($value): ?Pictogram
	//    {
	//        return $this->createQueryBuilder('p')
	//            ->andWhere('p.exampleField = :val')
	//            ->setParameter('val', $value)
	//            ->getQuery()
	//            ->getOneOrNullResult()
	//        ;
	//    }
}
