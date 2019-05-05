<?php

class Client
{
    protected $token = "739656624:AAGiea8iuSMYAtbmdczto-uHP3NoaVo4ps0";
    protected $updateId;

    private function makeUrl($method, $params = []) {
        $url = "https://api.telegram.org/bot";
        $url .= $this->token;
        $url .= "/" . $method;
        if (!empty($params)) {
            $url .= "?" . http_build_query($params);
        }
        return $url;
    }

    private function makeFileUrl($method, $params = []) {
        $url = "https://api.telegram.org/file/bot";
        $url .= $this->token;
        $url .= "/" . $method;
        if (!empty($params)) {
            $url .= "?" . http_build_query($params);
        }
        return $url;
    }

    protected function setCurlProxy($ch) {

        $proxy = 'socks5://aqua.tgsocks.tk:443';
        $proxy_pwd = "gurlik:gurlik";
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_pwd);
    }

    public function sendMedia($chat_id, $file_path) {
        $file_path = str_replace("\n", '', $file_path);
        $post = array('chat_id' => $chat_id, 'document'=>new CurlFile($file_path));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"https://api.telegram.org/bot" . $this->token. "/sendDocument");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $this->setCurlProxy($ch);
        curl_exec ($ch);
        curl_close ($ch);
    }

    public function query($method, $params = []) {
        $url = $this->makeUrl($method, $params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        $this->setCurlProxy($ch);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, null);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result);
    }

    public function getUpdates() {

        $response = $this->query('getUpdates', [
            'offset' => $this->updateId + 1
        ]);
        if (!empty($response->result)) {
            $this->updateId = $response->result[count($response->result) -1]->update_id;
        }
        return $response->result;
    }

    public function sendMessage($chat_id, $text) {

        $response = $this->query('sendMessage',[
            'chat_id' => $chat_id,
            'text' => $text
        ]);
        return $response;
    }



    public function saveFile($update, $current_dir) {
        $file_info = $this->query('getFile', [
            'file_id' => $update->message->document->file_id
        ]);

        $caption = $update->message->caption;

        if ($file_info->ok) {
            $url = $this->makeFileUrl($file_info->result->file_path);
            $output_path = str_replace("\n", '', $current_dir . '/' . $caption);

            $fp = fopen ($output_path, 'w+');
            $ch = curl_init(str_replace(" ","%20",$url));
            curl_setopt($ch, CURLOPT_TIMEOUT, 50);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $this->setCurlProxy($ch);
            $res = curl_exec($ch);
            curl_close($ch);
            fclose($fp);

            if ($res) {
                $this->sendMessage($update->message->chat->id, "successfully uploaded file " . $caption);
            } else {
                $this->sendMessage($update->message->chat->id, "unsuccessful attempt to upload file " . $caption);
            }
        } else {
            $this->sendMessage($update->message->chat->id, "unsuccessful attempt to upload file " . $caption);
        }
    }
}