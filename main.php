<?php
/**
 * Created by PhpStorm.
 * User: lessmeaning
 * Date: 08.04.19
 * Time: 20:57
 */

define('token', "739656624:AAGiea8iuSMYAtbmdczto-uHP3NoaVo4ps0");
include "Client.php";
include "quick_proc/Process.php";
include "Invoker.php";
include('vendor/autoload.php');
$telegramApi = new Client();
$proc = new Process("/bin/sh");
$invoker = new Invoker(function($update) use($telegramApi, $proc) {
    $text = $update->message->text;
    $chat_id = $update->message->chat->id;
    if ($text == 'restart') {
        $result = 'please do not use this command';
    } else {
        $proc->putInput($text . "\n");
        $result = $proc->getOutput(2);
        if (empty($result)) $result = "<>";
    }
    $telegramApi->sendMessage($chat_id, $result);
});

$invoker->add_command("restart", function($update) use($telegramApi, $proc) {
    $proc->close();
    $proc->open();
    $telegramApi->sendMessage($update->message->chat->id, "restarted");
});

$invoker->add_command("test", function($update) use($telegramApi, $proc) {
    $telegramApi->sendMessage($update->message->chat->id, "congratulations u used test command");
});

$invoker->add_command("help", function($update) use($telegramApi) {
    $telegramApi->sendMessage($update->message->chat->id, "TODO help");
});

$invoker->add_command("upload", function($update) use($telegramApi, $proc) {
    $telegramApi->sendMessage($update->message->chat->id, "TODO upload");
});

$invoker->add_command("download", function($update) use($telegramApi, $proc) {
    $telegramApi->sendMessage($update->message->chat->id, "TODO download");
});

$invoker->add_command("clear", function($update) use($telegramApi, $proc) {
    $telegramApi->sendMessage($update->message->chat->id, "TODO clear");
});

while (true) {
    sleep(2);
    $updates = $telegramApi->getUpdates();
    foreach ($updates as $update){
        if (isset($update->message->text)) {
            $invoker->run($update);
        }
    }
}

