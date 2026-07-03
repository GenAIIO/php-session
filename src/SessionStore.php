<?php

namespace GenAI\Session;

/**
 * Where session data is stored. Implementations are registered with PHP's native
 * session machinery via session_set_save_handler() — so the cookie, the session
 * id and garbage collection stay native, and only the read/write target changes.
 *
 * The six methods mirror the save-handler callbacks (PHP 5.3 has no
 * SessionHandlerInterface, so this is our own contract, wired by Session as
 * array($store, 'open'), ... ).
 *
 * Compatible with PHP 5.3.29.
 */
interface SessionStore
{
    /**
     * @param string $savePath
     * @param string $sessionName
     * @return bool
     */
    public function open($savePath, $sessionName);

    /** @return bool */
    public function close();

    /**
     * @param string $id
     * @return string serialized session data ('' when none)
     */
    public function read($id);

    /**
     * @param string $id
     * @param string $data
     * @return bool
     */
    public function write($id, $data);

    /**
     * @param string $id
     * @return bool
     */
    public function destroy($id);

    /**
     * @param int $maxlifetime seconds
     * @return bool
     */
    public function gc($maxlifetime);
}
