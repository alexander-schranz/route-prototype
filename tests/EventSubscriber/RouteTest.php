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
        $entityManager->getConnection()->executeStatement('DELETE FROM route WHERE 1 = 1');
    }

    #[DataProvider('provideRoutes')]
    public function testUpdateRoute(
        array $routes,
        string $changeRoute,
        array $expectedRoutes,
    ): void {
        $repository = self::getContainer()->get(RouteRepository::class);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $firstRoute = null;
        $createdRoutes = [];
        $count = 0;
        foreach ($routes as $routeData) {
            $route = $this->createRoute($routeData);
            $uniqueKey = ($route->getSite() ?? '') . $route->getLocale() . $route->getSlug();
            $parentUniqueKey = ($route->getSite() ?? '') . $route->getLocale() . $routeData['parentSlug'];
            $parentRoute = $createdRoutes[$parentUniqueKey] ?? null;
            if ($parentRoute?->getId()) {
                $parentRoute = $entityManager->getReference(Route::class, $createdRoutes[$parentUniqueKey]->getId());
            }

            $route->setParentRoute($parentRoute);
            $repository->add($route);
            $createdRoutes[$uniqueKey] = $route;

            $firstRoute ??= $route;

            ++$count;

            if ($count % 1000 === 0) {
                $entityManager->flush();
                $entityManager->clear();
                \gc_collect_cycles();
            }
        }

        $entityManager->flush();
        $entityManager->clear();
        $this->assertNotNull($firstRoute);
        $firstRoute = $entityManager->getReference(Route::class, $firstRoute->getId());
        $firstRoute->setSlug($changeRoute);
        $entityManager->flush();
        $entityManager->clear();

        foreach ($expectedRoutes as $expectedRoute) {
            $route = $repository->findOneBy([
                'resourceKey' => 'page',
                'resourceId' => $expectedRoute['resourceId'],
                'locale' => 'en',
                'site' => 'website',
            ]);
            $this->assertNotNull($route);

            $this->assertSame($expectedRoute['slug'], $route->getSlug());
            $this->assertSame($expectedRoute['parentSlug'] ?? null, $route->getParentRoute()?->getSlug());

            ++$count;
            if ($count % 100 === 0) {
                $entityManager->clear();
            }
            if ($count % 1000 === 0) {
                \gc_collect_cycles();
            }
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
                    'slug' => '/test-article',
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
                    'slug' => '/test-article',
                ],
                [
                    'resourceId' => '2',
                    'slug' => '/test-article/child-a',
                    'parentSlug' => '/test-article',
                ],
                [
                    'resourceId' => '3',
                    'slug' => '/test-article/child-b',
                    'parentSlug' => '/test-article',
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
                    'slug' => '/test-article',
                ],
                [
                    'resourceId' => '2',
                    'slug' => '/test-article/child-a',
                    'parentSlug' => '/test-article',
                ],
                [
                    'resourceId' => '3',
                    'slug' => '/test-article/child-b',
                    'parentSlug' => '/test-article',
                ],
                [
                    'resourceId' => '4',
                    'slug' => '/test-article/child-b/grand-child-a',
                    'parentSlug' => '/test-article/child-b',
                ],
                [
                    'resourceId' => '5',
                    'slug' => '/test-article/child-b/grand-child-b',
                    'parentSlug' => '/test-article/child-b',
                ],
            ],
        ];

        yield 'heavy_load' => static::generateNestedRoutes('/rezepte', '/rezepte-neu', 10, 100_000);
    }

    private static function generateNestedRoutes($baseSlug, $newSlug, $depth = 10, $totalUrls = 100000) {
        $routes = [];
        $expectedRoutes = [];
        $queue = [
            [
                'resourceId' => 1,
                'slug' => $baseSlug,
                'parentSlug' => null,
                'depth' => 1,
                'uniqueSuffix' => null, // No suffix for the baseSlug
            ]
        ];
        $resourceId = 2;

        // Add independent routes to mix with the main route tree
        $independentRoots = ['/independent-route-1', '/independent-route-2', '/independent-route-3'];
        foreach ($independentRoots as $index => $independentSlug) {
            $queue[] = [
                'resourceId' => $resourceId++,
                'slug' => $independentSlug,
                'parentSlug' => null,
                'depth' => 1,
                'uniqueSuffix' => $index + 1, // Ensuring unique suffix for independent roots
            ];
        }

        while (count($routes) < $totalUrls && $queue) {
            $node = array_shift($queue);
            // Only apply unique suffix if it is not the baseSlug
            $uniqueSlug = $node['uniqueSuffix'] === null ? $node['slug'] : $node['slug'] . '-' . $node['uniqueSuffix'];
            $routes[] = [
                'resourceId' => $node['resourceId'],
                'slug' => $uniqueSlug,
                'parentSlug' => $node['parentSlug'],
            ];

            // Modify slug to expected new route
            $expectedSlug = str_replace($baseSlug, $newSlug, $uniqueSlug);
            $expectedRoutes[] = [
                'resourceId' => $node['resourceId'],
                'slug' => $expectedSlug,
                'parentSlug' => $node['parentSlug'] ? str_replace($baseSlug, $newSlug, $node['parentSlug']) : null,
            ];

            if ($node['depth'] < $depth) {
                for ($i = 1; $i <= 5; $i++) { // Adjust branching factor to reach ~100k URLs
                    if (count($routes) >= $totalUrls) {
                        break 2;
                    }
                    $childSlug = $node['slug'] . '/child-' . $i;
                    $queue[] = [
                        'resourceId' => $resourceId++,
                        'slug' => $childSlug,
                        'parentSlug' => $uniqueSlug,
                        'depth' => $node['depth'] + 1,
                        'uniqueSuffix' => $resourceId, // Use resourceId for unique suffix
                    ];
                }
            }
        }

        return [
            'routes' => $routes,
            'changeRoute' => $newSlug,
            'expectedRoutes' => $expectedRoutes,
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
        );
    }
}
