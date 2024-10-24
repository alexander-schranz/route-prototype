<?php

namespace App\EventListener;

use App\Entity\Route;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Service\ResetInterface;

#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: Route::class)]
#[AsDoctrineListener(event: Events::postFlush)]
#[AsDoctrineListener(event: Events::onClear)]
class RouteChangedUpdater implements ResetInterface
{
    /**
     * @var array<int, array{old: string, new: string, locale: string, site: string}>
     */
    private array $routeChanges = [];

    public function preUpdate(Route $route, PreUpdateEventArgs $args): void
    {
        $oldSlug = $args->getOldValue('slug');
        $newSlug = $args->getNewValue('slug');

        if ($oldSlug === $newSlug) {
            return;
        }

        $this->routeChanges[$route->getId()] = [
            'oldValue' => $oldSlug,
            'newValue' => $newSlug,
            'locale' => $route->getLocale(),
            'site' => $route->getSite(),
        ];
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (\count($this->routeChanges) === 0) {
            return;
        }

        $connection = $args->getObjectManager()->getConnection();

        foreach ($this->routeChanges as $routeChange) {
            $oldSlug = $routeChange['oldValue'];
            $newSlug = $routeChange['newValue'];
            $locale = $routeChange['locale'];
            $site = $routeChange['site'];

            // select all child and grand routes of oldSlug
            $selectQueryBuilder = $connection->createQueryBuilder()
                ->from('route', 'r')
                ->select('r.id')
                ->where('(r.parent_slug = :oldSlug OR r.parent_slug LIKE :oldSlugSlash)')
                ->andWhere('r.locale = :locale')
                ->andWhere('r.site = :site') // TODO site can be nullable how to handle this? parent_site?
                ->setParameter('oldSlug', $oldSlug)
                ->setParameter('oldSlugSlash', $oldSlug . '/%')
                ->setParameter('locale', $locale)
                ->setParameter('site', $site);

            $ids = \array_map(fn($row) => $row['id'], $selectQueryBuilder->executeQuery()->fetchAllAssociative());

            if (\count($ids) === 0) {
                continue;
            }

            // TODO create history for current ids

            // update child and grand routes
            $updateQueryBuilder = $connection->createQueryBuilder()->update('route', 'r')
                ->set('r.slug', 'CONCAT(:newSlug, SUBSTRING(r.slug, LENGTH(:oldSlug) + 1))')
                ->set('r.parent_slug', 'CONCAT(:newSlug, SUBSTRING(r.parent_slug, LENGTH(:oldSlug) + 1))')
                ->setParameter('newSlug', $newSlug)
                ->setParameter('oldSlug', $oldSlug)
                ->where('r.id IN (:ids)')
                ->setParameter('ids', $ids, ArrayParameterType::INTEGER);

            $updateQueryBuilder->executeStatement();
        }
    }

    public function onClear(OnClearEventArgs $args): void
    {
        $this->reset();
    }

    public function reset(): void
    {
        $this->routeChanges = [];
    }
}
