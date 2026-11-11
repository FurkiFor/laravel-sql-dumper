# Laravel SQL Dumper — Eloquent sorgularının gerçek SQL çıktısını görün

[English](README.md) | **Türkçe**

**Laravel SQL Dumper**, kodunuzun veritabanına gönderdiği gerçek SQL'i gösterir: Eloquent veya query builder
sorgusunun parametreleri (binding) yerine yazılmış ham SQL çıktısını alın, çalışan tüm sorguları kaydedin, yavaş
sorguları ve N+1 problemlerini bulun. Ayrıca Laravel olmadan da çalışan, Eloquent mantığında bir **PHP SQL sorgu
oluşturucu** ve **migration (CREATE TABLE) oluşturucu** içerir.

```php
User::where('age', '>', 18)->where('role', 'admin')->rawSql();
// select * from "users" where "age" > 18 and "role" = 'admin'
```

## Kurulum

```bash
composer require furkifor/sql_dumper
```

**Gereksinimler:** PHP 8.2+. Laravel özellikleri Laravel 11, 12 ve 13 ile çalışır. Servis sağlayıcı otomatik
tanınır, ek ayar gerekmez.

## Laravel'de SQL sorgusunu görme

`toSql()` soru işaretli çıktı verir. `rawSql()` ise veritabanı istemcinize doğrudan yapıştırabileceğiniz sorguyu verir:

```php
$query = User::where('age', '>', 18)->where('name', "O'Brien");

$query->toSql();   // select * from "users" where "age" > ? and "name" = ?
$query->rawSql();  // select * from "users" where "age" > 18 and "name" = 'O''Brien'
```

Eloquent (global scope'lar dahil), ilişkiler ve `DB::table()` ile çalışır. Zinciri bozmadan yazdırmak için:

```php
$users = User::where('active', true)
    ->dumpSql()          // ham SQL'i yazdırır
    ->orderBy('name')
    ->get();
```

Elinizde hazır bir SQL metni ve binding dizisi varsa:

```php
use Furkifor\SqlDumper\SqlDumper;

SqlDumper::interpolate('select * from users where id = ? and role = :role', [5, 'role' => 'admin']);
// select * from users where id = 5 and role = 'admin'
```

## Çalışan sorguları kaydetme ve N+1 tespiti

```php
use Furkifor\SqlDumper\QueryLog;

$log = QueryLog::start();

$posts = Post::all();
foreach ($posts as $post) {
    $post->author->name;        // N+1
}

$log->count();        // çalışan sorgu sayısı
$log->totalTime();    // toplam süre (ms)
$log->toRawSql();     // ham SQL listesi
$log->duplicates();   // tekrar eden sorgular => N+1 işareti
$log->slow(100);      // 100 ms ve üzeri süren sorgular
$log->dump();         // okunabilir rapor
```

Testlerde sayfa başına sorgu sayısını sınırlamak için de kullanılabilir:

```php
$log = QueryLog::start();
$this->get('/dashboard');
$this->assertLessThanOrEqual(10, $log->count());
```

## Tüm sorguları Laravel log dosyasına yazma

Geliştirme ortamında `.env` dosyasına ekleyin:

```dotenv
SQL_DUMPER_LOG=true
# isteğe bağlı: yalnızca 50 ms'den yavaş sorgular ve ayrı bir log kanalı
SQL_DUMPER_SLOW_MS=50
SQL_DUMPER_CHANNEL=daily
```

Ham SQL kullanıcı verisi içerdiği için canlı ortamda kapalı tutun. Ayar dosyasını yayınlamak için:

```bash
php artisan vendor:publish --tag=sql-dumper-config
```

## Laravel'siz PHP SQL sorgu oluşturucu

```php
use Furkifor\SqlDumper\SqlDumper;

$query = SqlDumper::table('users')
    ->select('name', 'email')
    ->where('age', '>', 18)
    ->where('role', 'admin')
    ->latest()
    ->limit(10);

$query->get();          // SELECT name, email FROM users WHERE age > ? AND role = ? ORDER BY created_at DESC LIMIT 10
$query->getBindings();  // [18, 'admin']
$query->toRawSql();     // ... WHERE age > 18 AND role = 'admin' ...
$query->toPrettySql();  // her ifade ayrı satırda
```

Desteklenenler: `where`, `orWhere`, `whereIn`, `whereNotIn`, `whereNull`, `whereNotNull`, `whereBetween`,
`whereLike`, `whereColumn`, `whereExists`, iç içe koşullar, alt sorgular, `when`, `join` / `leftJoin` / `rightJoin`,
`groupBy`, `having`, `orderBy`, `latest`, `limit`, `offset`, `forPage`, `count` / `sum` / `avg` / `min` / `max`,
`insert`, `update`, `increment`, `decrement`, `delete`.

```php
SqlDumper::table('users')->where('id', 5)->update(['name' => 'Yeni'])->toRawSql();
// UPDATE users SET name = 'Yeni' WHERE id = 5

$pdo = new PDO('mysql:host=localhost;dbname=app', 'user', 'secret');
$rows = SqlDumper::table('users', $pdo)->where('age', '>', 18)->execute();
```

Değerler sorgu çalıştırılırken her zaman parametre olarak bağlanır. Tablo ve sütun adları SQL'e olduğu gibi yazılır;
bunları kullanıcı girdisinden üretmeyin.

## Migration oluşturucu (CREATE TABLE)

MySQL / MariaDB, PostgreSQL, SQLite ve SQL Server için `CREATE TABLE` üretir:

```php
use Furkifor\SqlDumper\MigrateClass;

$table = new MigrateClass('mysql');           // mysql, pgsql, sqlite veya sqlsrv

echo $table->name('posts')->ifNotExists()
    ->string('title')->notNull()
    ->text('content')
    ->int('user_id')->notNull()
    ->foreignKey('user_id', 'users', 'id', 'CASCADE')
    ->boolean('is_published')->default(false)
    ->timestamps()
    ->createTable();
```

Bağlantı verilmezse `createTable()` yalnızca SQL'i döndürür. `new MigrateClass('mysql', $pdo)` şeklinde PDO
verirseniz sorguyu çalıştırır.

## Sık sorulan sorular

**Laravel'de çalışan SQL sorgusunu nasıl görürüm?**
Sorgunun sonuna `->rawSql()` ekleyin; parametreler yerine yazılmış SQL döner.

**Laravel'de N+1 sorgu problemini nasıl bulurum?**
Kodu `QueryLog::start()` ile sarın ve `$log->duplicates()` çıktısına bakın. Çok kez tekrar eden `SELECT`, N+1
demektir; `with()` ile eager loading yapın.

**Laravel'in kendi `toRawSql()` metodundan farkı ne?**
Yerine geçmez, üzerine ekler: süreli sorgu günlüğü, yavaş sorgu ve N+1 tespiti, `.env` ile SQL loglama, zinciri
bozmayan `dumpSql()`, okunaklı çıktı ve Laravel dışında da çalışan sorgu oluşturucu.

**Laravel olmadan kullanılabilir mi?**
Evet. `SqlDumper`, `QueryLog::record()`, `SqlDumper::interpolate()` ve `MigrateClass` bağımlılık gerektirmez.

## Test

```bash
composer test       # PHPUnit
composer analyse    # PHPStan
```

## Lisans

MIT. Ayrıntılar için [LICENSE.md](LICENSE.md).
