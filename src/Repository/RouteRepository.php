<?php

namespace App\Repository;

use App\Entity\Route;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class RouteRepository
{
    /**
     * @var EntityRepository<Route
     */
    private readonly EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->repository = $this->entityManager->getRepository(Route::class);
    }

    public function add(Route $route): void
    {
        $this->entityManager->persist($route);
    }
}
