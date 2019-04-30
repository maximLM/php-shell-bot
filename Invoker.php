<?php
/**
 * Created by PhpStorm.
 * User: lessmeaning
 * Date: 30.04.19
 * Time: 16:10
 */

class Invoker {
    protected $commands;
    protected $default_command;

    function __construct($default_command) {
        $this->default_command = $default_command;
        $this->commands = array();
    }

    public function run($update) {
        $key = explode(" ", $update->message->text)[0];
        if (array_key_exists($key, $this->commands)) {
            $this->commands[$key]($update);
        } else {
            $kek = $this->default_command;
            $kek($update);
        }
    }

    public function add_command($key, $command) {
        $this->commands["/" . $key] = $command;
    }

    /**
     * @return mixed
     */
    public function getDefaultCommand()
    {
        return $this->default_command;
    }

    /**
     * @param mixed $default_command
     */
    public function setDefaultCommand($default_command)
    {
        $this->default_command = $default_command;
    }


}