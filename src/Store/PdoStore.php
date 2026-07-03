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

        $ins = $this->pdo->prepare(
            'INSERT INTO ' . $this->table . ' (id, payload, expires_at) VALUES (?, ?, ?)'
        );
        return $ins->execute(array($id, $data, $expires));
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
