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
 - [ ] Support for non Site connected routes (article)
    - [ ] Site A with `/test` and Site B `/test` and article parent Site A `/test/article` should not be updated when Site B `/test` was changed
