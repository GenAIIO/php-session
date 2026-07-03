<?php

namespace GenAI\Session\Store;

use GenAI\Session\SessionStore;

/**
 * Stores session data as files in a project directory (e.g. cache/session),
 * rather than the system default save_path. Keeps sessions inside the app and
 * easy to clear. One file per session: sess_<id>.
 *
 * Compatible with PHP 5.3.29.
 */
class FileStore implements SessionStore
{
    /** @var string */
    private $dir;

    public function __construct($dir)
    {
        $dir = rtrim($dir, '/\\');

        // Resolve to an absolute path NOW. The session save handler's write()
        // runs at shutdown, when the working directory is no longer the app's —
        // a relative path would then point at the wrong place and silently fail.
        if ($dir !== '' && $dir[0] !== '/' && !preg_match('#^[A-Za-z]:[\\\\/]#', $dir)) {
            $dir = getcwd() . '/' . $dir;
        }

        $this->dir = $dir;
    }

    public function open($savePath, $sessionName)
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0700, true);
        }
        return is_dir($this->dir);
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        $file = $this->file($id);
        if (!is_file($file)) {
            return '';
        }
        $data = @file_get_contents($file);
        return $data === false ? '' : $data;
    }

    public function write($id, $data)
    {
        return @file_put_contents($this->file($id), $data, LOCK_EX) !== false;
    }

    public function destroy($id)
    {
        $file = $this->file($id);
        if (is_file($file)) {
            @unlink($file);
        }
        return true;
    }

    public function gc($maxlifetime)
    {
        $files = glob($this->dir . '/sess_*');
        if ($files === false) {
            return true;
        }
        $cutoff = time() - (int) $maxlifetime;
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
        return true;
    }

    private function file($id)
    {
        // Sanitise the id so it can't escape the directory.
        return $this->dir . '/sess_' . preg_replace('/[^a-zA-Z0-9,\-]/', '', $id);
    }
}
