<?php
/**
 * Process id file manager.
 * The pidfile controls access to the resource, acting as a mutex for ownership.
 * The process whose pid is stored in the file has access to (owns) the resource.
 * Ownership changes are made under a lock to guarantee atomicity.
 *
 * Copyright (C) 2013 Andras Radics
 * Licensed under the Apache License, Version 2.0
 *
 * 2013-02-08 - AR.
 */

class Pidfile
{
    protected $_pidfile;
    protected $_isExclusive = false;
    public function __construct( $pidfile ) {
        $this->_pidfile = $pidfile;
    }
    public function __destruct( ) {
        $this->release();
    }
    public function acquire( $pid = null ) {
        if ($pid === null) $pid = getmypid();
        if (!($fp = @fopen($this->_pidfile, "r+"))) {
            @touch($this->_pidfile);
            $fp = @fopen($this->_pidfile, "r+");
            if (!$fp) $this->_throwException("$this->_pidfile: unable to create/open");
        }
        flock($fp, LOCK_EX);
        if (($oldpid = trim(fgets($fp))) && $oldpid != $pid && $this->_processExists($oldpid)) {
            flock($fp, LOCK_UN);
            $this->_throwException("$this->_pidfile: currently held by process $oldpid");
        }
        fseek($fp, 0, SEEK_SET);
        $ok = fputs($fp, $pid . "\n");
        flock($fp, LOCK_UN);
        if (!$ok) $this->_throwException("$this->_pidfile: unable to write pid");
        $this->_isExclusive = true;
        return true;
    }
    public function release( ) {
        if ($this->_isExclusive) {
            $ok = @(unlink($this->_pidfile) ? true : file_put_contents($this->_pidfile, ''));
            $this->_isExclusive = false;
        }
    }
    protected function _throwException( $msg ) {
        throw new QuickProcException($msg);
    }
    protected function _processExists( $pid ) {
        $test = new Exists();
        return $test->processExists($pid);
    }
}