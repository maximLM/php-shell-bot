<?php
/**
 * Interactive process controller.
 * Allows bidirectional communication to a child process.
 * Best for sending single-line commands and retrieving single-line responses.
 *
 * Copyright (C) 2013 Andras Radics
 * Licensed under the Apache License, Version 2.0
 *
 * 2013-02-06 - AR.
 */

class Process {
    // unix signal numbers will never change
    const SIGKILL = 9;
    const SIGTERM = 15;
    protected $_proc;
    protected $_pid;
    protected $_isStarted = false;
    protected $_exitcode;
    protected $_termsig;
    protected $_pipes;
    protected $_outputFragment = '';
    protected $_errorFragment = '';
    public function __construct( $cmdline, $autostart = true ) {
        $this->_cmdline = $cmdline;
        // though it makes object creation a heavy-weight operation, the expectation is that
        // the process is running as soon as created.  Make it so.
        if ($autostart) $this->open();
    }
    public function __destruct( ) {
        // assume a standard app that exits when sent a SIGTERM
        // this is the fastest path, allows 1750/sec child processes started/stopped
        if ($this->_isStarted) $this->kill(self::SIGTERM)->close();
    }
    public static function create( $cmdline ) {
        $class = get_called_class();  // php 5.3
        return new $class($cmdline);
    }
    // connect to an already running process
    public function attach( $pid, $stdin, $stdout, $stderr ) {
        // WRITEME
    }
    // detach from the process
    public function detach( & $pid, & $pipes ) {
        // WRITEME
    }
    public function open( ) {
        if (!$this->_isStarted) {
            // exec the command to have the destructor kill the actual process, not the shell that starts it
            // @NOTE some shell built-ins are not exec-able, and will break (exit, return); run them as /bin/sh -c 'exit ...'
            $cmdline = strncmp(trim($this->_cmdline), "exec ", 5) === 0 ? $this->_cmdline : "exec {$this->_cmdline}";
            $descriptors = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w"));
            $this->_proc = proc_open($cmdline, $descriptors, $pipes);
            if (!$this->_proc) {
                // proc_open failed, this is fatal.
                // Note that we cannot detect if the process itself failed to fork, since
                // the shell started and it hasn`t run the command line yet
                // This means bad/missing commands are not detected here
                throw new QuickProcException("unable to start process");
            }
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $this->_pipes = $pipes;
            $this->_exitcode = null;
            $this->_termsig = null;
            // call getProcStatus last, it harvests the exitcode if child already done
            $this->_pid = $this->_getProcStatus()->pid;
            $this->_isStarted = true;
        }
        return $this;
    }
    // wait for process to finish and free resources
    public function close( ) {
        if ($this->_proc) {
            fclose($this->_pipes[0]);
            fclose($this->_pipes[1]);
            fclose($this->_pipes[2]);
            if ($this->_proc) {
                $status = $this->_getProcStatus();
                $code = proc_close($this->_proc);
                if ($status->running) {
                    $this->_exitcode = $code;
                }
            }
            else {
                // WRITEME
            }
            $this->_proc = null;
            $this->_pid = null;
            $this->_isStarted = false;
        }
        elseif ($this->_pid) {
            // WRITEME
        }
    }
    public function kill( $signal = self::SIGTERM ) {
        if ($this->_proc) {
            proc_terminate($this->_proc, $signal);
        }
        elseif ($this->_pid && function_exists('posix_kill')) {
            // kill all processes in this process group, both the shell and the running process
            $ok = posix_kill($this->_pid, $signal);
            // do not yield the cpu here, proc_close() will
        }
        elseif ($this->_pid) {
            // awkward without posix, but not impossible...
            `/bin/kill -{$signal} {$this->_pid} > /dev/null 2>&1`;
        }
        return $this;
    }

    // wait for the child process to exit for up to $timeout sec
    public function wait( $timeout = 0 ) {
        $timeout_tm = microtime(true) + $timeout;
        $usleep_tm = microtime(true) + .001;
        do {
            if (!$this->isRunning()) break;
            // sleeping here triples the runtime and uses 40% more cpu overall
            // yes it's a busywait, but if the wait period is short it's more efficient
            $now_tm = microtime(true);
            if ($now_tm > $usleep_tm) usleep(1);
        } while (microtime(true) < $timeout_tm);
        return $this;
    }
    public function isRunning( ) {
        if (!$this->_pid || isset($this->_exitcode)) return false;
        return (bool) $this->_getProcStatus()->running;
    }
    public function getPid( ) {
        return $this->_pid;
    }
    public function getExitcode( ) {
        if (isset($this->_exitcode)) return $this->_exitcode;
        if (!$this->_proc) return null;
        $this->_getProcStatus();
        return $this->_exitcode;
    }
    // nickname for putInput
    public function fputs( $line ) {
        fputs($this->_pipes[0], $line);
    }
    // nickname for getOutputLine
    public function fgets( $timeout = 0 ) {
        return $this->_getLine($this->_pipes[1], $timeout, $this->_outputFragment);
    }
    public function putInput( $string ) {
        // caution: writing commands is blocking, and can deadlock
        // if the command is long and generates so much output that the
        // stdout pipe fills up (blocking the child proc so it stops reading)
        fputs($this->_pipes[0], $string);
    }
    public function getOutputLine( $timeout = 0 ) {
        return $this->_getLine($this->_pipes[1], $timeout, $this->_outputFragment);
    }
    public function getErrorLine( $timeout = 0 ) {
        return $this->_getLine($this->_pipes[2], $timeout, $this->_errorFragment);
    }
    public function getOutputLines( $nlines, $timeout = 0 ) {
        return $this->_getLines($this->_pipes[1], $nlines, $timeout, $this->_outputFragment);
    }
    public function getErrorLines( $nlines, $timeout = 0 ) {
        return $this->_getLines($this->_pipes[2], $nlines, $timeout, $this->_errorFragment);
    }
    public function getOutput( $timeout = 0 ) {
        $ret = $this->_getLines($this->_pipes[1], 2000000000, $timeout, $this->_outputFragment);
        return implode('', $ret);
    }
    public function getError( $timeout = 0 ) {
        $ret = $this->_getLines($this->_pipes[2], 2000000000, $timeout, $this->_outputFragment);
        return implode('', $ret);
    }
    // return up to $nlines of output, waiting no more than $timeout for lines to appear
    protected function _getLines( $fp, $nlines, $timeout, & $fragment ) {
        $lines = array();
        $timeout_tm = microtime(true) + $timeout;
        while (count($lines) < $nlines) {
            if (($line = $this->_tryGetLine($fp, $fragment)) !== false) {
                $lines[] = $line;
                continue;
            }
            if ($lines || microtime(true) >= $timeout_tm)
                break;
            usleep(10);
        }
        return $lines;
    }
    // wait for and return at most 1 line of output, or false if no output yet
    protected function _getLine( $fp, $timeout, & $fragment ) {
        $timeout_tm = microtime(true) + $timeout;
        do {
            if (($line = $this->_tryGetLine($fp, $fragment)) > '')
                return $line;
        } while (microtime(true) < $timeout_tm && (1 + usleep(10)));
        return false;
    }
    // return a ready line of output, else false if none yet
    protected function _tryGetLine( $fp, & $fragment ) {
        if (($line = fgets($fp)) > '') {
            if ((substr($line, -1) === "\n")) {
                if ($fragment !== '') {
                    $line = $fragment . $line;
                    $fragment = '';
                }
                return $line;
            }
            else {
                $this->_outputFragment .= $line;
            }
        }
        return false;
    }
    protected function _getProcStatus( ) {
        if ($this->_proc) {
            // note: proc_get_status() generates a warning if the proc has been closed
            $status = proc_get_status($this->_proc);
            if (!$status['running'] && ($status['exitcode'] >= 0 || $status['termsig'])) {
                // be sure to save the exitcode, it only shows up once
                // exitcode will be as returned from the process, or -1 if killed by signal
                $this->_exitcode = $status['exitcode'];
                if ($status['signaled']) $this->_termsig = $status['termsig'];
            }
            return ((object) $status);
        }
        else
            return ((object) array());
    }
    protected function _waitpid( $pid ) {
        if (!function_exists('pcntl_wait') || !$this->_pid)
            return false;
        if (pcntl_waitpid($this->_pid, $status, WNOHANG) > 0) {
            $this->_exitcode = pcntl_wexitstatus($status);
            return true;
        }
    }
    protected function _processExists( $pid ) {
        $test = new Exists();
        return $test->processExists($pid);
    }
}
/* quicktest: /*
error_reporting(E_ALL);
$tm = microtime(true);
$nloops = 2000;
for ($i=0; $i<$nloops; ++$i) {
    $proc = new Quick_Proc_Process("exit");
    $proc->open();
    $proc->__destruct();
}
$tm = microtime(true) - $tm;
echo "AR: $nloops proc open/close in $tm sec, " . ($nloops/$tm) . " / sec\n";
// 1890/sec just open, abandon process
// 1750/sec kill()->close() (w/o kill yield cpu at all!!), 1710 if new proc obj created too
// 1640/sec if also wait before closing
$nloops = 20000;
$proc = new Quick_Proc_Process("cat");
$proc->open();
$tm = microtime(true);
for ($i=0; $i<$nloops; ++$i) {
    $proc->fputs("line $i\n");
    $line = $proc->getOutputLine(.02);
}
$tm = microtime(true) - $tm;
$proc->__destruct();
echo "AR: $nloops lines sent/received in $tm sec, " . ($nloops/$tm) . "/sec \n";
echo "AR: last line was: $line\n";
// 75k lines/sec round trip sent/received
die();
$tempfile = tempnam("/tmp", "test-");
for ($i=0; $i<100000; $i++) file_put_contents($tempfile, "line $i $i $i\n", FILE_APPEND);
$proc = new Quick_Proc_Process("cat $tempfile");
$proc->open();
$tm = microtime(true);
    $output = $proc->getOutputLines(999999999, 5);
$tm = microtime(true) - $tm;
// 500k / sec response lines read
//print_r($output);
@unlink($tempfile);
echo "AR: ".count($output)." short lines read in $tm sec\n";
die();
//echo "AR: proc status on start\n";
//$proc->isRunning();
$proc->fputs("hello\n");
echo "AR: " . $proc->getOutputLine(.01);
$proc->putInput("line 1\nline 2\n");
echo "AR: output = " . $proc->getOutput(4);
$tm = microtime(true);
for ($i=0; $i<10000; $i++) {
    $proc->fputs($i);
    $val = $proc->getOutputLine(1);
}
$tm = microtime(true) - $tm;
echo "val = $val\n";
echo $tm . "\n";
// AR: about 60k lines sent/replies received per second (cat)
// 2.6.18 kernel context switches either 40-80k/sec (bursts to 130k/sec; fast), or 1775/sec (slow)
// ... maybe usleep is slow, but i/o blocking is fast? maybe other procs running help?? but its multicore
// A: usleep 100us too long; w/ 10us always high ctx switch rates
$proc->wait(0)->kill(Quick_Proc_Process::SIGTERM)->close();
print_r($proc);
//sleep(5);
/**/