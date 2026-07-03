<?php

namespace GenAI\Session;

/**
 * A small session API over native PHP sessions. Inject it instead of touching
 * $_SESSION directly — that superglobal is confined to this class.
 *
 *   $session->set('uid', 7);
 *   $uid = $session->get('uid');
 *   $session->flash('msg', 'Saved!');   // available on the NEXT request
 *   $msg = $session->getFlash('msg');
 *
 * Starts lazily on first use (so responses that never touch the session send no
 * cookie). An optional SessionStore decides where data lives — pass null for the
 * native default. With a custom store, a shutdown hook flushes the session before
 * objects are destroyed (needed on PHP 5.3, which writes sessions very late).
 *
 * Compatible with PHP 5.3.29.
 */
class Session
{
    /** @var SessionStore|null null = native default storage */
    private $store;

    /** @var string */
    private $name;

    /** @var int cookie lifetime in seconds (0 = until browser closes) */
    private $lifetime;

    /** @var bool */
    private $started = false;

    /**
     * @param SessionStore|null $store
     * @param array             $options name, lifetime
     */
    public function __construct($store = null, $options = array())
    {
        $this->store    = $store;
        $this->name     = isset($options['name']) ? $options['name'] : 'GENAISESS';
        $this->lifetime = isset($options['lifetime']) ? (int) $options['lifetime'] : 0;
    }

    private function start()
    {
        if ($this->started) {
            return;
        }

        if (session_id() === '') {
            if ($this->store !== null) {
                session_set_save_handler(
                    array($this->store, 'open'),
                    array($this->store, 'close'),
                    array($this->store, 'read'),
                    array($this->store, 'write'),
                    array($this->store, 'destroy'),
                    array($this->store, 'gc')
                );
                // On PHP 5.3 the session is written during shutdown, after objects
                // may be gone; force the write while the store is still alive.
                register_shutdown_function('session_write_close');
            }

            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            session_set_cookie_params($this->lifetime, '/', '', $secure, true);
            session_name($this->name);
            session_start();
        }

        // Rotate flash: last request's pending entries become this request's
        // readable flash; clear the bucket for flashes set during this request.
        $_SESSION['_flash']   = isset($_SESSION['_flash_pending']) ? $_SESSION['_flash_pending'] : array();
        $_SESSION['_flash_pending'] = array();

        $this->started = true;
    }

    public function get($key, $default = null)
    {
        $this->start();
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }

    public function set($key, $value)
    {
        $this->start();
        $_SESSION[$key] = $value;
        return $this;
    }

    public function has($key)
    {
        $this->start();
        return isset($_SESSION[$key]);
    }

    public function remove($key)
    {
        $this->start();
        unset($_SESSION[$key]);
        return $this;
    }

    /** Read and remove a value in the same request. */
    public function pull($key, $default = null)
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }

    /** Stash a value for the NEXT request only. */
    public function flash($key, $value)
    {
        $this->start();
        $_SESSION['_flash_pending'][$key] = $value;
        return $this;
    }

    /** Read a value flashed on the previous request. */
    public function getFlash($key, $default = null)
    {
        $this->start();
        return isset($_SESSION['_flash'][$key]) ? $_SESSION['_flash'][$key] : $default;
    }

    /** New session id, keeping the data (call on login to prevent fixation). */
    public function regenerate()
    {
        $this->start();
        session_regenerate_id(true);
        return $this;
    }

    public function id()
    {
        $this->start();
        return session_id();
    }

    /** Empty the session data (keeps the session). */
    public function clear()
    {
        $this->start();
        $_SESSION = array();
        return $this;
    }

    /** Drop the session entirely (logout). */
    public function destroy()
    {
        // Must be started to destroy it — sessions are lazy, and a logout request
        // may not have touched the session yet, so start it first.
        $this->start();
        $_SESSION = array();

        // Expire the cookie so the browser stops sending the dead session id.
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        $this->started = false;
        return $this;
    }
}
