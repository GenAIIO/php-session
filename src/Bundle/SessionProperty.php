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
 *   path     = cache/session ; for driver=file
 *   table    = sessions      ; for driver=database
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
    private $cookieDomain;

    public function bindData(Map $data)
    {
        $this->driver       = $data->get('driver');
        $this->name         = $data->get('name');
        $this->lifetime     = $data->get('lifetime');
        $this->path         = $data->get('path');
        $this->table        = $data->get('table');
        $this->cookieDomain = $data->get('cookie_domain');
    }

    /**
     * Cookie domain. '' (default) = host-only. Set a leading-dot domain like
     * '.genai.io.vn' to share the session cookie across all subdomains (SSO).
     */
    public function getCookieDomain()
    {
        return ($this->cookieDomain !== null) ? (string) $this->cookieDomain : '';
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
