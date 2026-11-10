# Database connections

## Why it is not in the usual phpMyAdmin list

NFCheck uses a private Oracle MySQL instance, separate from XAMPP's usual MySQL/MariaDB service.

| Setting | Original local workspace |
| --- | --- |
| Host | 127.0.0.1 |
| Port | 3307 |
| Database | nfcheck |
| Runtime username | nfcheck_app |
| Password | DB_PASSWORD in the local private .env file |
| Physical data files | .tools/mysql-data |
| Server configuration | .tools/mysql/my.ini |

XAMPP phpMyAdmin normally connects to port 3306. Seeing no nfcheck there does not mean the database is missing. Do not copy or edit raw .tools/mysql-data files while MySQL is running.

## Groupmates on different computers

Each computer follows [local setup](development.md), which installs the private engine and creates its own empty nfcheck database from migrations. Your records, passwords, photos and provider keys do not travel through GitHub. No Supabase/TiDB key is needed for ordinary local setup.

An existing coin is registered in the workspace that issued it. A fresh teammate database will not recognize your original coin automatically. Use a separate blank test coin or the isolated simulator; do not overwrite an active coin to share source code. To share an identical dataset, arrange a separately approved private backup/restore with its required key and photos, rather than committing a data dump.

The reference SQL files in database/schema let teammates inspect table definitions in an editor without starting MySQL. They contain structure only. Use the migrations to install or change the real database.

## Inspect with a database client

Connect a MySQL-capable SQL client to the values above. On each teammate's computer, use that computer's .env password. The runtime account permits application operations but intentionally cannot change table structures or rewrite immutable history. For project schema changes, write a migration rather than granting the website a database owner role.

## Optional phpMyAdmin server entry

If XAMPP/phpMyAdmin is installed, back up its config.inc.php and add a separate server entry after its existing entries. Keep the existing server configuration. In that new entry use:

```php
$i++;
$cfg['Servers'][$i]['verbose'] = 'NFCheck local MySQL';
$cfg['Servers'][$i]['host'] = '127.0.0.1';
$cfg['Servers'][$i]['port'] = '3307';
$cfg['Servers'][$i]['auth_type'] = 'cookie';
$cfg['Servers'][$i]['AllowNoPassword'] = false;
```

Open phpMyAdmin, choose NFCheck local MySQL and sign in as nfcheck_app using the local .env password. The server must be running first. Do not paste database passwords into phpMyAdmin configuration, GitHub or chat. This entry is an optional inspection convenience, not a change to NFCheck's database engine.

## Online database

The prepared cloud path uses Supabase PostgreSQL, database `postgres`, private application schema `nfcheck_online`. Supabase's default `public` schema is different. Inspect nfcheck_online through Supabase Table Editor or a PostgreSQL client; phpMyAdmin is for MySQL and cannot manage PostgreSQL.

Online maintenance and restricted application credentials are separate private settings. Local migrations do not automatically update Supabase. A guarded target-specific migration/grant review is required before deploying code that needs newer tables.
