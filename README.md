# genai/session

A session component with **pluggable storage**. A small `Session` API over native
PHP sessions; a `SessionStore` save-handler decides *where* the data lives:

| driver     | store        | use it for |
|------------|--------------|------------|
| `default`  | native files | single server, simplest |
| `file`     | `FileStore`  | project-local files (`cache/session/`) |
| `database` | `PdoStore`   | multiple servers (shared `sessions` table) |

The cookie, session id, and GC stay native — only the read/write target changes.

## API
```php
$session->set('uid', 7);
$uid = $session->get('uid', 0);
$session->has('uid'); $session->remove('uid'); $session->pull('once');
$session->flash('msg', 'Saved!');       // available on the NEXT request
$msg = $session->getFlash('msg');
$session->regenerate();                  // on login (anti-fixation)
$session->destroy();                     // on logout
```
Starts **lazily** on first use, so responses that never touch the session set no cookie.

## Config (`[session]` group, optional)
```ini
[session]
driver   = file          ; default | file | database
name     = KIDSAFE
lifetime = 0             ; cookie seconds (0 = until browser close)
path     = cache/session ; driver=file
table    = sessions      ; driver=database
```

## Wiring (one bean)
The factory picks the store from config; the app decides whether it has a PDO to pass:
```php
#[Configuration]
class SessionConfig
{
    #[Bean(\GenAI\Session\Session::class)]
    public function session(\GenAI\Session\Bundle\SessionProperty $cfg, \PDO $pdo)
    {
        return \GenAI\Session\SessionFactory::build($cfg, $pdo);   // omit $pdo if no DB
    }
}
```
Then inject `GenAI\Session\Session` anywhere and drop manual `session_start()`.

## Notes
- `SessionProperty` auto-registers (the package declares `extra.genai.scan`); just `require genai/session`.
- PHP 5.3 has no `SessionHandlerInterface`, so this uses the 6-callback save-handler form plus a `session_write_close` shutdown hook (so custom stores flush before objects are destroyed).
- SameSite isn't set on the cookie (needs PHP 7.3+); httponly is always on, secure is auto on HTTPS.
- `database` needs `ext-pdo`. The table is created on first use; `UPDATE`-then-`INSERT` keeps it SQLite/MySQL-portable.
