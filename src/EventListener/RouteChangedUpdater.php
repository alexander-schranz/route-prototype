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
                ->from('route', 'parent')
                ->select('parent.id as parentId')
                ->innerJoin('parent', 'route', 'child', 'child.parent_id = parent.id')
                ->andWhere('(parent.site = :site)')
                ->andWhere('parent.locale = :locale')
                ->andWhere('(parent.slug = :newSlug OR parent.slug LIKE :oldSlugSlash)') // direct child is using newSlug already updated as we are in PostFlush, grand child use oldSlugWithSlash as not yet updated
                ->setParameter('newSlug', $newSlug)
                ->setParameter('oldSlugSlash', $oldSlug . '/%')
                ->setParameter('locale', $locale)
                ->setParameter('site', $site);

            $parentIds = \array_map(fn($row) => $row['parentId'], $selectQueryBuilder->executeQuery()->fetchAllAssociative());

            if (\count($parentIds) === 0) {
                continue;
            }

            \array_unique($parentIds); // DISTINCT and GROUP BY a lot slower as make it unique in PHP itself

            // TODO create history for current ids

            // update child and grand routes
            $updateQueryBuilder = $connection->createQueryBuilder()->update('route', 'r')
                ->set('r.slug', 'CONCAT(:newSlug, SUBSTRING(r.slug, LENGTH(:oldSlug) + 1))')
                ->setParameter('newSlug', $newSlug)
                ->setParameter('oldSlug', $oldSlug)
                ->where('r.parent_id IN (:parentIds)')
                ->setParameter('parentIds', $parentIds, ArrayParameterType::INTEGER);

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
