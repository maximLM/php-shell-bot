
<?php
/**
 * Copyright (C) 2013 Andras Radics
 * Licensed under the Apache License, Version 2.0
 */
class Exists
{
    public function processExists( $pid ) {
        // linux-specific test for whether the process exists
        if (php_uname("s") === 'Linux') {
            $procdir = "/proc/$pid";
            $this->_clearStatCache($procdir);
            return is_dir($procdir) && is_numeric($pid);
        }
        elseif (function_exists('posix_kill')) {
            // half-hearted existence test, only works for our processes (signal perms)
            return ($pid > 0 && posix_kill($pid, 0) === true);
        }
    }
    protected function _clearStatCache( $filename ) {
        if (version_compare(phpversion(), '5.3.0') > 0)
            clearstatcache(false, $filename);
        else
            clearstatcache();
    }
}