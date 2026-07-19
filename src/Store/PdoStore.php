<?php

namespace GenAI\Session\Store;

use GenAI\Session\SessionStore;

/**
 * Stores sessions in a database table via PDO — so they survive across multiple
 * web servers (which file storage can't). The table is created on first use:
 *
 *   id          VARCHAR(128) PRIMARY KEY
 *   payload     TEXT
 *   expires_at  INTEGER   (unix time; rows past it are dead / collectable)
 *
 * Portable across SQLite/MySQL: writes do UPDATE-then-INSERT rather than a
 * dialect-specific upsert. Lifetime comes from session.gc_maxlifetime.
 *
 * Compatible with PHP 5.3.29.
 */
class PdoStore implements SessionStore
{
    /** @var \PDO */
    private $pdo;

    /** @var string */
    private $table;

    public function __construct(\PDO $pdo, $table = 'sessions')
    {
        $this->pdo   = $pdo;
        $this->table = $table;
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ' . $this->table . ' ('
            . 'id VARCHAR(128) NOT NULL PRIMARY KEY, '
            . 'payload TEXT, '
            . 'expires_at INTEGER NOT NULL)'
        );
    }

    public function open($savePath, $sessionName)
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        $st = $this->pdo->prepare(
            'SELECT payload FROM ' . $this->table . ' WHERE id = ? AND expires_at >= ?'
        );
        $st->execute(array($id, time()));
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        return ($row && isset($row['payload'])) ? (string) $row['payload'] : '';
    }

    public function write($id, $data)
    {
        $expires = time() + $this->lifetime();

        $up = $this->pdo->prepare(
            'UPDATE ' . $this->table . ' SET payload = ?, expires_at = ? WHERE id = ?'
        );
        $up->execute(array($data, $expires, $id));
        if ($up->rowCount() > 0) {
            return true;
        }

        // rowCount() == 0 does NOT reliably mean "no such row": on MySQL/TiDB an
        // UPDATE that matches a row but leaves every column unchanged reports 0
        // affected rows, and two requests sharing one session id can both land
        // here. So the INSERT below may collide on the PRIMARY key even though the
        // row already exists. Treat a duplicate-key violation as success and make
        // sure our payload is the one that persists (re-run the UPDATE) — never let
        // it bubble up as an uncaught PDOException that fatals session_write_close.
        try {
            $ins = $this->pdo->prepare(
                'INSERT INTO ' . $this->table . ' (id, payload, expires_at) VALUES (?, ?, ?)'
            );
            return $ins->execute(array($id, $data, $expires));
        } catch (\PDOException $e) {
            if (!$this->isDuplicateKey($e)) {
                throw $e;
            }
            // The row exists after all (identical-value UPDATE above, or a racing
            // writer inserted it). Persist our payload over it and report success.
            $up->execute(array($data, $expires, $id));
            return true;
        }
    }

    /**
     * A UNIQUE / PRIMARY KEY violation, across PDO drivers. SQLSTATE 23000 covers
     * MySQL/TiDB (driver code 1062) and, via PDO, SQLite unique constraints too.
     */
    private function isDuplicateKey(\PDOException $e)
    {
        if ($e->getCode() === '23000') {
            return true;
        }
        $info = $e->errorInfo;
        if (is_array($info) && isset($info[1])) {
            $driverCode = (int) $info[1];
            // MySQL/TiDB 1062; SQLite 19 (constraint) / 1555 / 2067 (unique).
            return in_array($driverCode, array(1062, 19, 1555, 2067), true);
        }

        return false;
    }

    public function destroy($id)
    {
        $st = $this->pdo->prepare('DELETE FROM ' . $this->table . ' WHERE id = ?');
        return $st->execute(array($id));
    }

    public function gc($maxlifetime)
    {
        $st = $this->pdo->prepare('DELETE FROM ' . $this->table . ' WHERE expires_at < ?');
        return $st->execute(array(time()));
    }

    private function lifetime()
    {
        $ttl = (int) ini_get('session.gc_maxlifetime');
        return $ttl > 0 ? $ttl : 1440;
    }
}
