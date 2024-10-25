# README

This package include a prototype for a new `Route` entity for [Sulu CMS](https://github.com/sulu/sulu).
The route entity supports multi-site, multi localization and a parent child relation.

The goal is to simplify existing route handling which requires calling a `Manager` service.
Instead, in future following should be enough to create a new route:

```php
$route = new Route('page', 1, 'en', '/test', 'intranet');
$routeRepository->add($route);
$entityManager->flush();
```

Update a route is just a call to `setSlug`:

```php
$route->setSlug('/new-slug');
$entityManager->flush();
```

All updating of child routes is done automatically via doctrine listeners as postFlush listener,
no need to call a `Manager` service or other services.

## Running Tests

```bash
composer bootstrap-test-env

composer test
```

> Skip the `heavy_load` tests if you don't want to wait for a long time.

## TODO

 - [x] Childs are update
 - [x] Grand childs are update
 - [x] Multi Localization support
 - [x] Multi Site support
 - [x] Support for non Site connected routes (article)
    - [x] Site A with `/test` and Site B `/test` and article parent Site A `/test/article` should not be updated when Site B `/test` was changed
 - [ ] Add Multi Site tests
 - [ ] Add Multi Localization tests

## Benchmark Select queries

There are different kind of queries possible. The example uses ~100.000 routes and about ~40.000 need to be updated:

### Child Ids

```sql
SELECT child.id FROM route parent
INNER JOIN route child ON child.parent_id = parent.id
WHERE parent.site = 'website'
AND parent.locale = 'en'
AND (parent.slug = '/rezepte-neu' OR parent.slug LIKE '/rezepte/%')
```

Time: 70 - 110ms  
Total Ids: 41406  

### Parent Ids (👈 Looks like the best one)

```sql
SELECT parent.id FROM route parent
INNER JOIN route child ON child.parent_id = parent.id
WHERE parent.site = 'website'
AND parent.locale = 'en'
AND (parent.slug = '/rezepte-neu' OR parent.slug LIKE '/rezepte/%')
```

Time: 60 - 110ms  
Total Ids: 41406 (with duplicates array_unique in PHP to ~8124 parentIds)  
Total Time include Update: 1.85 seconds  

### Distinct Parents

```sql
SELECT DISTINCT parent.id FROM route parent
INNER JOIN route child ON child.parent_id = parent.id
WHERE parent.site = 'website'
AND parent.locale = 'en'
AND (parent.slug = '/rezepte-neu' OR parent.slug LIKE '/rezepte/%')
```

Time: 115 - 160ms  
Total Ids: 8282  

### Group By Parents

```sql
SELECT parent.id FROM route parent
INNER JOIN route child ON child.parent_id = parent.id
WHERE parent.site = 'website'
AND parent.locale = 'en'
AND (parent.slug = '/rezepte-neu' OR parent.slug LIKE '/rezepte/%')
GROUP BY parent.id
```

Time: 115 - 160ms  
Total Ids: 8282

## Benchmark Update queries

### Update by Child Ids

```sql
UPDATE route r
SET slug = CONCAT('/rezepte-neu', SUBSTRING(r.slug, LENGTH('/rezepte') + 1))
WHERE r.id IN (:childIds)
```

TODO benchmark

### Update by Parent Ids

```sql
UPDATE route r
SET slug = CONCAT('/rezepte-neu', SUBSTRING(r.slug, LENGTH('/rezepte') + 1))
WHERE r.parent_id IN (:parentIds)
```

TODO benchmark
