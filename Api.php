<?php
require_once 'vendor/autoload.php';
require_once 'simple_html_dom.php';
use \Dejurin\GoogleTranslateForFree;

class Api{
    private $api_key;
    private $vk;

    public function __construct($api_key)
    {
        $this->api_key = $api_key;
        $this->vk = new VK\Client\VKApiClient();
    }

    public function server(){
        try {
            return $this->vk->messages()->getLongPollServer($this->api_key, [
                'need_pts' => 0,
                'lp_version' => 3,
            ]);
        } catch (\VK\Exceptions\VKApiException $e) {
            echo $e->getMessage();
        } catch (\VK\Exceptions\VKClientException $e) {
            echo $e->getMessage();
        }
    }

    public function info($res)
    {
        $url = "https://{$res['server']}?act=a_check&key={$res['key']}&ts={$res['ts']}&wait=25&mode=2&version=3";
        $info = file_get_contents($url);
        $info = json_decode($info, true);
        return $info;
    }

    public function start($info, $res)
    {
        $ts = $info['ts'];
        while (true) {
            $msg = "";
            $result = 0;

            if(!isset($info['failed'])) {
                $url = "https://{$res['server']}?act=a_check&key={$res['key']}&ts={$ts}&wait=25&mode=2&version=3";
                $info = file_get_contents($url);
                $info = json_decode($info, true);
                $ts = $info['ts'];
            }else{
                try {
                    $res = $this->vk->messages()->getLongPollServer($this->api_key, [
                        'need_pts' => 0,
                        'lp_version' => 3,
                    ]);
                } catch (\VK\Exceptions\VKApiException $e) {
                } catch (\VK\Exceptions\VKClientException $e) {}
                $url = "https://{$res['server']}?act=a_check&key={$res['key']}&ts={$ts}&wait=25&mode=2&version=3";
                $info = file_get_contents($url);
                $info = json_decode($info, true);
                $ts = $info['ts'];
            }

            echo "<pre>";
            print_r($info);
            echo "</pre>";

            if (!empty($info['updates'])) {
                if ($info['updates'][0][0] == 80) {
                    $updates = $info['updates'][1][5];
                    $peer_id = $info['updates'][1][3];
                    $self = FALSE;
                } elseif ($info['updates'][0][0] == 4) {
                    $updates = $info['updates'][0][5];
                    $peer_id = $info['updates'][0][3];
                    $self = TRUE;
                } else {
                    continue;
                }
            } else {
                continue;
            }
            $user_msg = mb_strtolower($updates);
            $msg_item = explode(" ", $user_msg);
            switch ($msg_item[0]){
                case "!рандом":
                    $msg = rand(0,101);
                    break;
                case "!погода":
                    if (!empty($msg_item[1])) {
                        $apiKey = "c6524f8c109942db947396c7e14b2a0c";
                        $city = $msg_item[1];
                        $url = "http://api.openweathermap.org/data/2.5/weather?q=" . $city . "&lang=ru&units=metric&appid=" . $apiKey;
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_URL, $url);
                        $data = json_decode(curl_exec($ch));
                        curl_close($ch);

                        if (!empty($data->main->temp)) {
                            $msg = "&#9989;Погода в вашем городе: " . $data->weather[0]->description . "\n";
                            $msg .= "Температура: " . $data->main->temp . "℃\n";
                            $msg .= "Ощущается как: " . $data->main->feels_like . "℃\n";
                            $msg .= "Скорость ветра: " . $data->wind->speed . "m/h\n";
                            $msg .= "Облачность: " . $data->clouds->all . "%&#9989;\n";
                        }else{
                            $msg = "✖Ошибка";
                        }
                    }else{
                        $msg = "✖Укажите город";
                    }
                    break;
                case "!перевод":
                    $source = $msg_item[1];
                    $target = $msg_item[2];
                    $attempts = 5;
                    $text_item = explode($msg_item[2],$user_msg);
                    $text = $text_item[1];

                    $tr = new GoogleTranslateForFree();
                    $result = $tr->translate($source, $target, $text, $attempts);
                    $msg = $result;
                    break;
                case "!транслит":
                    $msg_item = strstr($user_msg," ");
                    $msg = $this->correctString($msg_item);
                    break;
                case "!удалить_все_заявки":
                    if ($self) {
                        try {
                            $result = $this->vk->friends()->deleteAllRequests($this->api_key);
                        } catch (\VK\Exceptions\VKApiException $e) {
                        } catch (\VK\Exceptions\VKClientException $e) {}
                        if (isset($result) && $result == 1) {
                            $msg = 'Успешно';
                        } else{
                            $msg = "✖Ошибка";
                        }
                    }else{
                        $msg = '✖Ошибка, личная команда';
                    }
                    break;
                case "!добавить_друга":
                    if ($self){
                        $msg_item = strstr($user_msg," ");
                        try {
                            $result = $this->vk->friends()->add($this->api_key, [
                                'user_id' => (int)$msg_item,
                                'text' => "",
                                'follow' => 0,
                            ]);
                        } catch (\VK\Exceptions\Api\VKApiFriendsAddEnemyException $e) {
                        } catch (\VK\Exceptions\Api\VKApiFriendsAddInEnemyException $e) {
                        } catch (\VK\Exceptions\Api\VKApiFriendsAddNotFoundException $e) {
                        } catch (\VK\Exceptions\Api\VKApiFriendsAddYourselfException $e) {
                        } catch (\VK\Exceptions\VKApiException $e) {
                        } catch (\VK\Exceptions\VKClientException $e) {}
                        if ($result == 1 || $result == 2){
                            $msg = 'Успешно';
                        } else{
                            $msg = "✖Ошибка";
                        }
                    }else{
                        $msg = '✖Ошибка, личная команда';
                    }
                    break;
                case "!удалить_друга":
                    if ($self) {
                        $msg_item = strstr($user_msg, " ");
                        try {
                            $result = $this->vk->friends()->delete($this->api_key, [
                                'user_id' => (int)$msg_item,
                            ]);
                        } catch (\VK\Exceptions\VKApiException $e) {
                        } catch (\VK\Exceptions\VKClientException $e) {
                        }
                        if (isset($result)) {
                            if ($result['success'] == 1) {
                                $msg = 'Успешно';
                            } else {
                                $msg = "✖Ошибка";
                            }
                        }
                    }else{
                        $msg = '✖Ошибка, личная команда';
                    }
                    break;
                case "!искать_фото":
                    $msg_item = strstr($user_msg," ");
                    try {
                        $result = $this->vk->photos()->search($this->api_key, [
                            'q' => $msg_item,
                            'count' => 1,
                        ]);
                    } catch (\VK\Exceptions\VKApiException $e) {
                    } catch (\VK\Exceptions\VKClientException $e) {}
                    if (isset($result)){
                        $c = count($result['items'][0]['sizes']);
                        $msg = $result['items'][0]['sizes'][$c-1]['url'];
                    }
                    break;
                case "!курс":
                    $data = $this->getCourse();
                    switch ($msg_item[1]){
                        case "usd":
                            $msg = $data->Valute->USD->Value."руб";
                            break;
                        case "eur":
                            $msg = $data->Valute->EUR->Value."руб";
                            break;
                        case "uah":
                            $msg = $data->Valute->UAH->Value."руб";
                            break;
                        case "jpy":
                            $msg = $data->Valute->JPY->Value."руб";
                            break;
                        case "try":
                            $msg = $data->Valute->TRY->Value."руб";
                            break;
                        case "cny":
                            $msg = $data->Valute->CNY->Value."руб";
                            break;
                        case "cad":
                            $msg = $data->Valute->CAD->Value."руб";
                            break;
                        default:
                            $msg = "✖Укажите курс";
                    }
                    break;
                case "!искать_текст":
                    $name = str_replace(" ", "-", substr(strstr($user_msg," "),1));
                    $base = 'https://genius.com/amp/'.$name.'-lyrics';
                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
                    curl_setopt($curl, CURLOPT_HEADER, true);
                    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($curl, CURLOPT_URL, $base);
                    curl_setopt($curl, CURLOPT_REFERER, $base);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
                    curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36");
                    $str = curl_exec($curl);
                    curl_close($curl);
                    $html = new simple_html_dom();
                    $html->load($str);
                    $e = $html->find('div.lyrics',0);
                    $msg = str_replace("<br>","\n",$e->innertext);
                    $msg = strip_tags($msg);
                    $html->clear();
                    unset($html);
                    if (empty($msg)){
                        $msg = "✖Ошибка";
                    }
                    break;
                case "!такси":
                    $addres = substr(strstr($user_msg," "),1);
                    $addres = explode("-", $addres);
                    $rll = $this->getRll($addres[0])."~".$this->getRll($addres[1]);
                    $url = "https://taxi-routeinfo.taxi.yandex.net/taxi_info?rll=".$rll."&clid=ak1383&apikey=1152f76fe1be4eaca7b975e715ee0e07";
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_URL, $url);
                    $data = json_decode(curl_exec($ch),true);
                    curl_close($ch);
                    if (!empty($data)) {
                        $price = $data["options"][0]["price"];
                        $time = $data["time_text"];
                        $msg = "Стоимость составит:" . $price . "руб \n Время поездки:" . $time;
                    }else{
                        $msg = "✖Ошибка";
                    }
                    break;
                case "/help":
                    $msg .= "♥ !рандом                       - случайное число от 1 до 100\n";
                    $msg .= "♥ !погода (город)               - погода в вашем городе\n";
                    $msg .= "♥ !перевод (from) (to)          - перевод\n";
                    $msg .= "♥ !транслит (subject)           - смена раскладки\n";
                    $msg .= "♥ !искать_фото (key word)       - ищет фото из общего доступа вк по ключевым словам\n";
                    $msg .= "♥ !курс (subject)               - курсы валют в рублях\n";
                    $msg .= "♥ !искать_текст (artists title) - текст песни по исполнителю и названию\n";
                    $msg .= "♥ !такси (from - to)            - говорит сколько будет стоить поездка и сколько она продлится (яндекс)\n";
                    $msg .= "♥ !удалить_все_заявки           - отмечает все заявки как просмотренные (личная)\n";
                    $msg .= "♥ !добавить_друга (id)          - отправляет или принимает заявку в друзья (личная)\n";
                    $msg .= "♥ !удалить_друга (id)           - отклоняет заявку или удаляет из друзей (личная)\n";
                    break;

                default:
                    continue 2;
            }
            if (isset($msg)) {
                try {
                    $this->vk->messages()->send($this->api_key, [
                        'peer_id' => $peer_id,
                        'random_id' => rand(1, 1000000),
                        'message' => $msg,
                    ]);
                } catch (\VK\Exceptions\Api\VKApiMessagesCantFwdException $e) {
                } catch (\VK\Exceptions\Api\VKApiMessagesChatBotFeatureException $e) {
                } catch (\VK\Exceptions\Api\VKApiMessagesChatUserNoAccessException $e) {
                } catch (\VK\Exceptions\Api\VKApiMessagesContactNotFoundException $e) {
                } catch (\VK\Exceptions\Api\VKApiMessagesDenySendException $e) {
                } catch (\VK\Exceptions\Api\VKApiMessagesKeyboardInvalidException $e) {
                } catch (\VK\Exceptions\Api\VKApiMessagesPrivacyException $e) {
                } catch (\VK\Exceptions\Api\VKApiMessagesTooLongForwardsException $e) {
                } catch (\VK\Exceptions\Api\VKApiMessagesTooLongMessageException $e) {
                } catch (\VK\Exceptions\Api\VKApiMessagesTooManyPostsException $e) {
                } catch (\VK\Exceptions\Api\VKApiMessagesUserBlockedException $e) {
                } catch (\VK\Exceptions\VKApiException $e) {
                } catch (\VK\Exceptions\VKClientException $e) {
                }
            }
        }
    }
    private function correctString($value){
        $converter = array(
            'f' => 'а',	',' => 'б',	'd' => 'в',	'u' => 'г',	'l' => 'д',	't' => 'е',	'`' => 'ё',
            ';' => 'ж',	'p' => 'з',	'b' => 'и',	'q' => 'й',	'r' => 'к',	'k' => 'л',	'v' => 'м',
            'y' => 'н',	'j' => 'о',	'g' => 'п',	'h' => 'р',	'c' => 'с',	'n' => 'т',	'e' => 'у',
            'a' => 'ф',	'[' => 'х',	'w' => 'ц',	'x' => 'ч',	'i' => 'ш',	'o' => 'щ',	'm' => 'ь',
            's' => 'ы',	']' => 'ъ',	"'" => "э",	'.' => 'ю',	'z' => 'я',

            'F' => 'А',	'<' => 'Б',	'D' => 'В',	'U' => 'Г',	'L' => 'Д',	'T' => 'Е',	'~' => 'Ё',
            ':' => 'Ж',	'P' => 'З',	'B' => 'И',	'Q' => 'Й',	'R' => 'К',	'K' => 'Л',	'V' => 'М',
            'Y' => 'Н',	'J' => 'О',	'G' => 'П',	'H' => 'Р',	'C' => 'С',	'N' => 'Т',	'E' => 'У',
            'A' => 'Ф',	'{' => 'Х',	'W' => 'Ц',	'X' => 'Ч',	'I' => 'Ш',	'O' => 'Щ',	'M' => 'Ь',
            'S' => 'Ы',	'}' => 'Ъ',	'"' => 'Э',	'>' => 'Ю',	'Z' => 'Я',

            '@' => '"',	'#' => '№',	'$' => ';',	'^' => ':',	'&' => '?',	'/' => '.',	'?' => ',',
        );

        $value = strtr($value, $converter);
        return $value;
    }
    private function getCourse() {
        static $rates;

        if ($rates === null) {
            $rates = json_decode(file_get_contents('https://www.cbr-xml-daily.ru/daily_json.js'));
        }

        return $rates;
    }
    private function getRll($addres){
        $map_api = '9dbc24bb-9dc1-42b8-9618-e27fcff046f4';
        $url = 'https://geocode-maps.yandex.ru/1.x/?apikey='.$map_api.'&format=json&geocode='.urlencode($addres);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $data = json_decode(curl_exec($ch));
        curl_close($ch);
        $c = $data->response->GeoObjectCollection->featureMember[0]->GeoObject->Point->pos;
        $c = str_replace(" ", ",", $c);
        return $c;
    }

}

