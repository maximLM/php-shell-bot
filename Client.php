<?php

class Client
{
    protected $token = "739656624:AAGiea8iuSMYAtbmdczto-uHP3NoaVo4ps0";
    protected $updateId;

    protected function query($method, $params = []) {
        $url = "https://api.telegram.org/bot";
        $url .= $this->token;
        $url .= "/" . $method;
        if (!empty($params))
        {
            $url .= "?" . http_build_query($params);
        }

        $ch = curl_init();
        $proxy = 'socks5://aqua.tgsocks.tk:443';
        $proxy_pwd = "gurlik:gurlik";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_pwd);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, null);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
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

}