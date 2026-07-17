<?php

namespace GenAI\Session\Bundle;

use GenAI\Property\AbstractProperty;
use GenAI\Property\Attribute\Property;
use GenAI\Property\Util\Map;

/**
 * Session config — the [session] group of the app's config (optional; sensible
 * defaults when absent):
 *
 *   [session]
 *   driver   = file          ; default | file | database
 *   name     = KIDSAFE
 *   lifetime = 0             ; cookie lifetime in seconds (0 = until browser close)
 *   path     = cache/session ; storage dir for driver=file
 *   table    = sessions      ; for driver=database
 *   ; --- cookie params (all optional; sensible defaults) ---
 *   cookie_path     = /        ; cookie path
 *   cookie_domain   =          ; '' = host-only; '.example.com' = shared across subdomains
 *   cookie_secure   =          ; '' = auto (HTTPS) | 1 = force secure | 0 = never
 *   cookie_httponly = 1        ; 1 = httponly (default) | 0 = readable by JS
 *
 * Fed to SessionFactory to build the Session bean. Runtime class (PHP 5.3-safe).
 */
#[Property(group: 'session', optional: true)]
class SessionProperty extends AbstractProperty
{
    private $driver;
    private $name;
    private $lifetime;
    private $path;
    private $table;
    private $cookiePath;
    private $cookieDomain;
    private $cookieSecure;
    private $cookieHttpOnly;

    public function bindData(Map $data)
    {
        $this->driver         = $data->get('driver');
        $this->name           = $data->get('name');
        $this->lifetime       = $data->get('lifetime');
        $this->path           = $data->get('path');
        $this->table          = $data->get('table');
        $this->cookiePath     = $data->get('cookie_path');
        $this->cookieDomain   = $data->get('cookie_domain');
        $this->cookieSecure   = $data->get('cookie_secure');
        $this->cookieHttpOnly = $data->get('cookie_httponly');
    }

    /** Cookie path. Default '/'. */
    public function getCookiePath()
    {
        return ($this->cookiePath !== null && $this->cookiePath !== '') ? (string) $this->cookiePath : '/';
    }

    /**
     * Cookie domain. '' (default) = host-only. Set a leading-dot domain like
     * '.genai.io.vn' to share the session cookie across all subdomains (SSO).
     */
    public function getCookieDomain()
    {
        return ($this->cookieDomain !== null) ? (string) $this->cookieDomain : '';
    }

    /**
     * Secure flag. null (default) = auto (secure only over HTTPS); '1'/'0' force on/off.
     * @return bool|null
     */
    public function getCookieSecure()
    {
        if ($this->cookieSecure === null || $this->cookieSecure === '') {
            return null; // auto-detect from the request
        }
        return !($this->cookieSecure === '0' || $this->cookieSecure === 0 || $this->cookieSecure === false);
    }

    /** HttpOnly flag. Default true; set '0' to allow JavaScript access. */
    public function getCookieHttpOnly()
    {
        return !($this->cookieHttpOnly === '0' || $this->cookieHttpOnly === 0 || $this->cookieHttpOnly === false);
    }

    public function getDriver()
    {
        return ($this->driver !== null && $this->driver !== '') ? $this->driver : 'default';
    }

    public function getName()
    {
        return ($this->name !== null && $this->name !== '') ? $this->name : 'GENAISESS';
    }

    public function getLifetime()
    {
        return (int) $this->lifetime;
    }

    public function getPath()
    {
        return ($this->path !== null && $this->path !== '') ? $this->path : 'cache/session';
    }

    public function getTable()
    {
        return ($this->table !== null && $this->table !== '') ? $this->table : 'sessions';
    }
}
