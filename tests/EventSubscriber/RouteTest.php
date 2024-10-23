<?php

namespace App\Tests\EventSubscriber;

use App\Entity\Route;
use App\Repository\RouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @phpstan-type Route array{resourceId: string, slug: string, parentSlug?: string|null}
 */
class RouteTest extends KernelTestCase
{
    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('DELETE FROM route');
    }

    #[DataProvider('provideRoutes')]
    public function testUpdateRoute(
        array $routes,
        string $changeRoute,
        array $expectedRoutes,
    ): void
    {
        $repository = self::getContainer()->get(RouteRepository::class);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $firstRoute = null;
        foreach ($routes as $route) {
            $route = $this->createRoute($route);
            $repository->add($route);

            $firstRoute ??= $route;
        }

        $entityManager->flush();
        $this->assertNotNull($firstRoute);

        $firstRoute->setSlug($changeRoute);
        $entityManager->flush();

        foreach ($expectedRoutes as $expectedRoute) {
            $route = $repository->findOneBy([
                'resourceKey' => 'page',
                'resourceId' => $expectedRoute['resourceId'],
                'locale' => 'en',
            ]);
            $this->assertNotNull($route);

            $this->assertSame($expectedRoute['slug'], $route->getSlug());
            $this->assertSame($expectedRoute['parentSlug'] ?? null, $route->getParentSlug());
        }
    }

    public static function provideRoutes(): iterable
    {
        yield 'single_route_update' => [
            'routes' => [
                [
                    'resourceId' => '1',
                    'slug' => '/test'
                ],
            ],
            'changeRoute' => '/test-article',
            'expectedRoutes' => [
                [
                    'resourceId' => '1',
                    'slug' => '/test',
                ],
            ],
        ];

        yield 'direct_childs_update' => [
                'routes' => [
                    [
                        'resourceId' => '1',
                        'slug' => '/test'
                    ],
                    [
                        'resourceId' => '2',
                        'slug' => '/test/child-a',
                        'parentSlug' => '/test',
                    ],
                    [
                        'resourceId' => '3',
                        'slug' => '/test/child-b',
                        'parentSlug' => '/test',
                    ],
                ],
                    'changeRoute' => '/test-article',
                    'expectedRoutes' => [
                [
                    'resourceId' => '1',
                    'slug' => '/test',
                ],
                [
                    'resourceId' => '2',
                    'slug' => '/test-article/child-a',
                    'parentSlug' => '/test',
                ],
                [
                    'resourceId' => '3',
                    'slug' => '/test-article/child-b',
                    'parentSlug' => '/test',
                ],
            ],
        ];

        yield 'nested_childs_update' => [
                'routes' => [
                    [
                        'resourceId' => '1',
                        'slug' => '/test'
                    ],
                    [
                        'resourceId' => '2',
                        'slug' => '/test/child-a',
                        'parentSlug' => '/test',
                    ],
                    [
                        'resourceId' => '3',
                        'slug' => '/test/child-b',
                        'parentSlug' => '/test',
                    ],
                    [
                        'resourceId' => '4',
                        'slug' => '/test/child-b/grand-child-a',
                        'parentSlug' => '/test/child-b',
                    ],
                    [
                        'resourceId' => '5',
                        'slug' => '/test/child-b/grand-child-b',
                        'parentSlug' => '/test/child-b',
                    ],
                ],
                    'changeRoute' => '/test-article',
                    'expectedRoutes' => [
                [
                    'resourceId' => '1',
                    'slug' => '/test',
                ],
                [
                    'resourceId' => '2',
                    'slug' => '/test-article/child-a',
                    'parentSlug' => '/test',
                ],
                [
                    'resourceId' => '3',
                    'slug' => '/test-article/child-b',
                    'parentSlug' => '/test',
                ],
                [
                    'resourceId' => '4',
                    'slug' => '/test-article/child-b/grand-child-a',
                    'parentSlug' => '/test/child-b',
                ],
                [
                    'resourceId' => '5',
                    'slug' => '/test-article/child-b/grand-child-b',
                    'parentSlug' => '/test/child-b',
                ],
            ],
        ];
    }

    private function createRoute(array $route): Route
    {
        return new Route(
            'page',
            $route['resourceId'],
            'en',
            $route['slug'],
            'website',
            $route['parentSlug'] ?? null
        );
    }
}
