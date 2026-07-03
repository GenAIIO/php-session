<?php

namespace GenAI\Session;

use GenAI\Session\Bundle\SessionProperty;
use GenAI\Session\Store\FileStore;
use GenAI\Session\Store\PdoStore;

/**
 * Builds a Session from config, picking the storage backend by driver:
 *   default  -> native PHP file sessions (system save_path)
 *   file     -> FileStore in the configured dir (e.g. cache/session)
 *   database -> PdoStore (needs a PDO; pass it in)
 *
 * The app wires one bean with this, deciding whether it has a PDO to give:
 *
 *   #[Bean(\GenAI\Session\Session::class)]
 *   public function session(SessionProperty $cfg, \PDO $pdo) {
 *       return \GenAI\Session\SessionFactory::build($cfg, $pdo);
 *   }
 *
 * Compatible with PHP 5.3.29.
 */
class SessionFactory
{
    /**
     * @param SessionProperty $cfg
     * @param \PDO|null       $pdo required only for the 'database' driver
     * @return Session
     */
    public static function build(SessionProperty $cfg, $pdo = null)
    {
        $options = array('name' => $cfg->getName(), 'lifetime' => $cfg->getLifetime());
        $driver  = $cfg->getDriver();

        if ($driver === 'database') {
            if (!($pdo instanceof \PDO)) {
                throw new \RuntimeException(
                    'Session driver "database" needs a PDO connection — pass one to SessionFactory::build().'
                );
            }
            return new Session(new PdoStore($pdo, $cfg->getTable()), $options);
        }

        if ($driver === 'file') {
            return new Session(new FileStore($cfg->getPath()), $options);
        }

        return new Session(null, $options); // 'default' -> native storage
    }
}
