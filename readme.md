# Git.php

## Description

A PHP git repository control library. Allows the running of any git command from a PHP class. Runs git commands using `proc_open`, not `exec` or the type, therefore it can run in PHP safe mode.

## Requirements

A system with [git](http://git-scm.com/) installed

## Basic Use

```php
require_once('Git.php');

$repo = Git::open('/path/to/repo');  // -or- Git::create('/path/to/repo')

$repo->add('.');
$repo->commit('Some commit message');
$repo->push('origin', 'master');
```

---

Like my work?

[![ko-fi](https://www.ko-fi.com/img/donate_sm.png)](https://ko-fi.com/U7U8MIC8)

