# This is my package SqlDumper

[![Latest Version on Packagist](https://img.shields.io/packagist/v/furkifor/sql_dumper.svg?style=flat-square)](https://packagist.org/packages/furkifor/sql_dumper)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/furkifor/sql_dumper/run-tests?label=tests)](https://github.com/furkifor/sql_dumper/actions?query=workflow%3ATests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/furkifor/sql_dumper/Check%20&%20fix%20styling?label=code%20style)](https://github.com/furkifor/sql_dumper/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/furkifor/sql_dumper.svg?style=flat-square)](https://packagist.org/packages/furkifor/sql_dumper)

```bash
composer require furkifor/sql_dumper
```

## Usage

```php
$sql_dumper = new Furkifor\SqlDumper("TABLE_NAME");
echo $sql_dumper->select('*')->get();
```

- [furkan](https://github.com/FurkiFor)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
