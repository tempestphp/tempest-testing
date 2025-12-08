## Naming conventions

### For assertion methods

Assertion methods should not contain the word `assert`, instead they should read like a fluent sentence. For example:

```php
test('this')->is('that');
test(['a'])->missesKey(2);
```

### For failure messages

Failure message should always start with a lowercase letter. They should be written in the past tense:

```php
"$actual was not expected $expected";
"$actual did not contain expected $expected";
"$array had key $expected while it should not";
```
