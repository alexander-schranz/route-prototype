# README

```bash
composer bootstrap-test-env

composer test
```

## TODO

 - [x] Childs are update
 - [x] Grand childs are update
 - [x] Multi Localization support
 - [x] Multi Site support
 - [x] Support for non Site connected routes (article)
    - [x] Site A with `/test` and Site B `/test` and article parent Site A `/test/article` should not be updated when Site B `/test` was changed

## Benchmark

There are different kind of queries possible:

## Child Ids

```sql
SELECT child.id FROM route parent
INNER JOIN route child ON child.parent_id = parent.id
WHERE parent.site = 'website'
AND parent.locale = 'en'
AND (parent.slug = '/rezepte-neu' OR parent.slug LIKE '/rezepte/%')
```

Time: 70 - 110ms
Total Ids: 41406

## Parent Ids

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

## Distinct Parents

```sql
SELECT DISTINCT parent.id FROM route parent
INNER JOIN route child ON child.parent_id = parent.id
WHERE parent.site = 'website'
AND parent.locale = 'en'
AND (parent.slug = '/rezepte-neu' OR parent.slug LIKE '/rezepte/%')
```

Time: 115 - 160ms
Total Ids: 8282

## Group By Parents

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
