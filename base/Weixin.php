<?php
namespace yangshihe\weixinapi\base;

use Yii;
use yii\helpers\Json;
use DOMDocument;
use DOMElement;
use DOMText;

/**
 * PHP WeixinApi API for Yii2
 *
 *@package common\helpers
 *@author yuzhiyang <yangshihe@qq.com>
 *@copyright yangshihe <yangshihe@qq.com/>
 *@version 1.0.0
 *@since 2016年8月10日
 *@todo
 */

class Weixin
{

    public $appid;

    public $appsecret;

    public $access_token;

    public $url;

    public $code;

    public $jsapi_ticket;

    public $media_id;

    public $requestData;

    private $config;

    private $_token;

    private $cacheName = [
        'access_token' => '',
        'jsapi_ticket' => '',

    ];

    public $itemTag = 'item'; //微信xml格式的 itemTag name

    public function __construct($config)
    {

        $this->config = $config;

        $this->init();

        // $this->_token = $this->access_token = $this->_model->access_token;

        // $this->jsapi_ticket = $this->_model->jsapi_ticket;

      //  $this->media_id = $media_id;

    }

    public function init()
    {

        if (!isset($this->config['appid']) || !isset($this->config['appsecret'])) {

            throw new  \yii\web\ConflictHttpException('appid OR appsecret MUST be set');
        }

        $this->appid = $this->config['appid'];

        $this->appsecret = $this->config['appsecret'];

        //配置缓存
        $this->cacheName['access_token'] = $this->appid;
        $this->cacheName['jsapi_ticket'] = $this->appid . 'jsapi_ticket';

        // unset($config);

        $this->getAccessToken();

    }


    public function checkSignature($token)
    {
        $signature = Yii::$app->request->get('signature');
        $timestamp = Yii::$app->request->get('timestamp');
        $nonce = Yii::$app->request->get('nonce');

        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );

        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 接口验证
     * @param integer $scene_id
     * @param string $token range in (QR_SCENE,QR_LIMIT_SCENE)
     * @param string $echostr
     */
    public function valid($token, $echostr)
    {
        if($this->checkSignature($token)){
            echo $echostr;
            exit;
        }
    }

    /**
     * 数据签名
     * @param array $data
     * @return string
     */
    /* 参考就好
    public static function sign($data)
    {
        ksort($data);
        $string1 = "";
        foreach ($data as $k => $v) {
            if ($v) {
                $string1 .= "$k=$v&";
            }
        }
        $stringSignTemp = $string1 . "key=" . $this->config["key"];
        $sign = strtoupper(md5($stringSignTemp));

        return $sign;
    }
    */





    //解析服务器发送的xml
    /*
    *   必须配置xml可以被接收
        'components' => [
            'request' => [
                //'cookieValidationKey' => '_DNA_ZG5hQmFja2Vu-weixin',
                'parsers' => [
                    'application/json' => 'yii\web\JsonParser',
                    'text/xml' => 'bobchengbin\Yii2XmlRequestParser\XmlRequestParser',
                    'application/xml' => 'bobchengbin\Yii2XmlRequestParser\XmlRequestParser',
                ],
            ],
        ]
    *
    */

    /**
     * 接收微信post到指定api的xml内容,
     *
     * @return array
     */
    public function parsersRequest()
    {
        $this->requestData = Yii::$app->request->getBodyParams();
        return $this->requestData;
    }

    /**
     * 把请求后获得的微信公众号结构的xml字符串解析为 数组格式
     * @param string $str
     * @return array
     */
    public function parsersXML($str)
    {
        return (array) simplexml_load_string($str, 'SimpleXMLElement', LIBXML_NOCDATA);
    }

    /**
     * 响应微信的请求,
     * @see yangshihe\weixinapi\base\ReplyMessage
     * @param mixed $data
     * @param $type range in (text, image, voice, music, video, news)
     * @return string xml
     */
    public function message($data, $type ='text')
    {
        $replyMessage = new ReplyMessage($this->requestData['FromUserName'], $this->requestData['ToUserName']);

        $xmlData = $replyMessage->message($data, $type);

        echo $this->xml($xmlData);

        exit();
    }

    /**
     * 配置当前页面接口所需要的 jsticket,
     * @return string array
     */
    public function getSignPackage() {

        $jsapiTicket = $this->getJsApiTicket();

        // 注意 URL 一定要动态获取，不能 hardcode.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

        $timestamp = time();

        $nonceStr = $this->createNonceStr();

        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

        $signature = sha1($string);

        $signPackage = array(
          "appId"     => $this->appid,
          "nonceStr"  => $nonceStr,
          "timestamp" => $timestamp,
          "url"       => $url,
          "signature" => $signature,
          "rawString" => $string
        );

        return $signPackage;
    }

    /**
     * 16位 随机数
     * @return string
     */
    private function createNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
          $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /**
     * 获得 access_token
     * @param mixed $accessToken
     * @return string jsapi_ticket
     */
    private function getJsApiTicket() {


        $jsapi_ticket = Yii::$app->cache->get($this->cacheName['jsapi_ticket']);


        $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=' . $this->access_token;


        if (!$jsapi_ticket || !isset($jsapi_ticket['time']) ||  $jsapi_ticket['time'] > (time())) {

            $jsapi_ticket = [];

            $data = $this->Tkget($url);

            $data = Json::decode($data);

            if (isset($data['errcode']) && $data['errmsg'] != 'ok') {

                $message = '获取jsapi_ticket错误：' . $this->getError($data['errcode']);

                throw new \yii\web\ServerErrorHttpException($message);

            }

            $jsapi_ticket['data'] = $data['ticket'];

            $jsapi_ticket['time'] = time() + 5000;

            Yii::$app->cache->set($this->cacheName['jsapi_ticket'], $jsapi_ticket, 5000);

        }

        $this->jsapi_ticket = $jsapi_ticket['data'];

        return $this->jsapi_ticket;
    }


    /**
     * 获得 access_token
     * @return string
     */
    public function getAccessToken()
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->appid . '&secret=' . $this->appsecret;

        $access_token = Yii::$app->cache->get($this->cacheName['access_token']);

        if (!$access_token || !isset($access_token['time']) ||  $access_token['time'] > (time())) {

            $access_token = [];

            $data = $this->get($url);

            $data = Json::decode($data);

            if (isset($data['errcode'])) {

                $message = '获取access_token错误：' . $this->getError($data['errcode']);

                throw new \yii\web\ServerErrorHttpException($message);

            }

            $access_token['data'] = $data['access_token'];

            $access_token['time'] = time() + 5000;

            Yii::$app->cache->set($this->cacheName['access_token'], $access_token, 5000);

        }

        $this->access_token = $access_token['data'];

        return $this->access_token;

    }


    /**
     * 获取临时二维码 QR_SCENE
     * 获取永久二维码 QR_LIMIT_SCENE
     * @param integer $scene_id
     * @param string $qr range in (QR_SCENE,QR_LIMIT_SCENE)
     * @param integer $time
     * @return binary
     */
    public function qrcode($scene_id, $qr = 'QR_LIMIT_SCENE', $time = 1800){

        if ($qr == 'QR_LIMIT_SCENE') {

            $postjson = Json::encode([
                'action_name' => 'QR_LIMIT_SCENE',
                'action_info' => [
                    'scene' => ['scene_id' => $scene_id]
                ]
            ]);

        } else {

            $postjson = Json::encode([
                'expire_seconds' => $time,
                'action_name' => 'QR_SCENE',
                'action_info' => [
                    'scene' => ['scene_id' => $scene_id]
                ]
            ]);

        }

        $url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=' . $this->access_token;

        $dataJson = $this->post($url, $postjson);

        $data = Json::decode($dataJson);

        if(isset($data['errcode'])) return $this->getError($data['errcode']) ;

        return $this->getQrcode($data['ticket']);

   }

    /**
     * 根据ticket 获取二维码二进制图片文件,
     * @param string $ticket
     * @return binary
     */
    public function getQrcode($ticket) {

        $ticketUrl = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . $ticket;

        $img = $this->get($ticketUrl);

        if($img) return $img; // 二进制文件

        return 'no image';

    }


    /**
     * 获取关注者的详细信息
     * @param string $openid
     * @return mixed
     */
    public function userInfo($openid){


        $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token=' . $this->access_token . '&openid=' . $openid . '&lang=zh_CN ';

        $dataJson = $this->get($url);

        $data = Json::decode($dataJson);

        if(isset($data['errcode'])) return $this->getError($data['errcode']) ;

        return $data;

   }

   /**
     * 创建菜单
     * @param array $data
     * @return mixed
     */
    public function createMenu($data){

        $url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token=' . $this->access_token;

        $data = Json::encode($data);

        $dataJson = $this->post($url, $data);

        $data = Json::decode($dataJson);

        if(isset($data['errcode']) && $data['errmsg'] != 'ok') return $this->getError($data['errcode']) ;

        return $data;

   }

    /**
     * get 请求,
     * @param string $url
     * @return mixed
     */
    public function get($url)
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);//不要header
        //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1); //SSL
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//SSL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;

   }

   /**
     * get 请求2, 没有仔细研究这个 是js取票用的
     * @param string $url
     * @return mixed
     */
    private function Tkget($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_URL, $url);
        $res = curl_exec($curl);
        curl_close($curl);

        return $res;
    }



    /**
     * post 请求,
     * @param string $url
     * @param json $json encode
     * @return mixed
     */
    public function post($url, $json)
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) {
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        }
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $tmpInfo = curl_exec($ch);
        if (curl_errno($ch)) {
            return 'Errno'.curl_error($ch);
        }
        curl_close($ch);
        return $tmpInfo;

    }

    /**
     * 创建微信格式的XML
     * @param array $data
     * @param null $charset
     * @return string
     */
    public function xml(array $data, $charset = null)
    {
        $dom = new DOMDocument('1.0', $charset === null ? Yii::$app->charset : $charset);
        $root = new DOMElement('xml');
        $dom->appendChild($root);
        $this->buildXml($root, $data);
        $xml = $dom->saveXML();
        return trim(substr($xml, strpos($xml, '?>') + 2));
    }


    /**
     * @see yii\web\XmlResponseFormatter::buildXml()
     */
    protected function buildXml($element, $data)
    {
        if (is_object($data)) {
            $child = new DOMElement(StringHelper::basename(get_class($data)));
            $element->appendChild($child);
            if ($data instanceof Arrayable) {
                $this->buildXml($child, $data->toArray());
            } else {
                $array = [];
                foreach ($data as $name => $value) {
                    $array[$name] = $value;
                }
                $this->buildXml($child, $array);
            }
        } elseif (is_array($data)) {
            foreach ($data as $name => $value) {
                if (is_int($name) && is_object($value)) {
                    $this->buildXml($element, $value);
                } elseif (is_array($value) || is_object($value)) {
                    $child = new DOMElement(is_int($name) ? $this->itemTag : $name);
                    $element->appendChild($child);
                    $this->buildXml($child, $value);
                } else {
                    $child = new DOMElement(is_int($name) ? $this->itemTag : $name);
                    $element->appendChild($child);
                    $child->appendChild(new DOMText((string) $value));
                }
            }
        } else {
            $element->appendChild(new DOMText((string) $data));
        }
    }

    public function getError($errcode)
    {

        $array = [

            '-1' => '系统繁忙，此时请开发者稍候再试 ',
            '0' => '请求成功',
            '40001' => '获取access_token时AppSecret错误，或者access_token无效。请开发者认真比对AppSecret的正确性，或查看是否正在为恰当的公众号调用接口 ',
            '40002' => '不合法的凭证类型',
            '40003' => '不合法的OpenID，请开发者确认OpenID（该用户）是否已关注公众号，或是否是其他公众号的OpenID  ',
            '40004' => '不合法的媒体文件类型',
            '40005' => '不合法的文件类型',
            '40006' => '不合法的文件大小',
            '40007' => '不合法的媒体文件id ',
            '40008' => '不合法的消息类型',
            '40009' => '不合法的图片文件大小',
            '40010' => '不合法的语音文件大小',
            '40011' => '不合法的视频文件大小',
            '40012' => '不合法的缩略图文件大小',
            '40013' => '不合法的AppID，请开发者检查AppID的正确性，避免异常字符，注意大小写  ',
            '40014' => '不合法的access_token，请开发者认真比对access_token的有效性（如是否过期），或查看是否正在为恰当的公众号调用接口  ',
            '40015' => '不合法的菜单类型',
            '40016' => '不合法的按钮个数',
            '40017' => '不合法的按钮个数',
            '40018' => '不合法的按钮名字长度',
            '40019' => '不合法的按钮KEY长度',
            '40020' => '不合法的按钮URL长度',
            '40021' => '不合法的菜单版本号',
            '40022' => '不合法的子菜单级数',
            '40023' => '不合法的子菜单按钮个数',
            '40024' => '不合法的子菜单按钮类型',
            '40025' => '不合法的子菜单按钮名字长度',
            '40026' => '不合法的子菜单按钮KEY长度',
            '40027' => '不合法的子菜单按钮URL长度',
            '40028' => '不合法的自定义菜单使用用户',
            '40029' => '不合法的oauth_code',
            '40030' => '不合法的refresh_token',
            '40031' => '不合法的openid列表',
            '40032' => '不合法的openid列表长度',
            '40033' => '不合法的请求字符，不能包含\uxxxx格式的字符',
            '40035' => '不合法的参数',
            '40036' => '不合法的URL长度',
            '40037' => '不合法的分组id',
            '40038' => '不合法的请求格式',
            '40039' => '不合法的URL长度',
            '40050' => '不合法的分组id',
            '40051' => '分组名字不合法',

            '41001' => '缺少access_token参数',
            '41002' => '缺少appid参数',
            '41003' => '缺少refresh_token参数',
            '41004' => '缺少secret参数 ',
            '41005' => '缺少多媒体文件数据',
            '41006' => '缺少media_id参数 ',
            '41007' => '缺少子菜单数据 ',
            '41008' => '缺少oauth code ',
            '41009' => '缺少openid',

            '42001' => 'access_token超时，请检查access_token的有效期，请参考基础支持-获取access_token中，对access_token的详细机制说明 ',
            '42002' => 'refresh_token超时',
            '42003' => 'oauth_code超时',

            '43001' => '需要GET请求',
            '43002' => '需要POST请求',
            '43003' => '需要HTTPS请求',
            '43004' => '需要接收者关注',
            '43005' => '需要好友关系',

            '44001' => '多媒体文件为空',
            '44002' => 'POST的数据包为空',
            '44003' => '图文消息内容为空',
            '44004' => '文本消息内容为空',

            '45001' => '多媒体文件大小超过限制',
            '45002' => '消息内容超过限制',
            '45003' => '标题字段超过限制',
            '45004' => '描述字段超过限制',
            '45005' => '链接字段超过限制',
            '45006' => '图片链接字段超过限制',
            '45007' => '语音播放时间超过限制',
            '45008' => '图文消息超过限制',
            '45009' => '接口调用超过限制',
            '45010' => '创建菜单个数超过限制',
            '45015' => '回复时间超过限制',
            '45016' => '系统分组，不允许修改',
            '45017' => '分组名字过长',

            '45018' => '分组数量超过上限',
            '46001' => '不存在媒体数据',
            '46002' => '不存在的菜单版本',
            '46003' => '不存在的菜单数据',
            '46004' => '不存在的用户',

            '47001' => '解析JSON/XML内容错误',

            '48001' => 'api功能未授权，请确认公众号已获得该接口，可以在公众平台官网-开发者中心页中查看接口权限 ',

            '50001' => '用户未授权该api',

            '61451' => '参数错误(invalid parameter) ',
            '61452' => '无效客服账号(invalid kf_account) ',
            '61453' => '客服帐号已存在(kf_account exsited) ',
            '61454' => '客服帐号名长度超过限制(仅允许10个英文字符，不包括@及@后的公众号的微信号)(invalid kf_acount length) ',
            '61455' => '客服帐号名包含非法字符(仅允许英文+数字)(illegal character in kf_account) ',
            '61456' => '客服帐号个数超过限制(10个客服账号)(kf_account count exceeded) ',
            '61457' => '无效头像文件类型(invalid file type) ',
            '61450' => '系统错误(system error) ',
            '61500' => '日期格式错误',
            '61501' => '日期范围错误',
            '7000000' => '请求正常，无语义结果',
            '7000001' => '缺失请求参数',
            '7000002' => 'signature 参数无效',
            '7000003' => '地理位置相关配置 1 无效',
            '7000004' => '地理位置相关配置 2 无效',
            '7000005' => '请求地理位置信息失败',
            '7000006' => '地理位置结果解析失败',
            '7000007' => '内部初始化失败',
            '7000008' => '非法 appid（获取密钥失败）',
            '7000009' => '请求语义服务失败',
            '7000010' => '非法 post 请求',
            '7000011' => 'post 请求 json 字段无效',
            '7000030' => '查询 query 太短',
            '7000031' => '查询 query 太长',
            '7000032' => '城市、经纬度信息缺失',
            '7000033' => 'query 请求语义处理失败',
            '7000034' => '获取天气信息失败',
            '7000035' => '获取股票信息失败',
            '7000036' => 'utf8 编码转换失败',
        ];

        if (!empty($errcode) && array_key_exists($errcode, $array)) {

            return $array[$errcode];

        }

        return '';

    }

}
