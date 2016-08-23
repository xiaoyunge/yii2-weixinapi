<?php

namespace yangshihe\weixinapi\base;
/**
 * PHP 微信支付  for Yii2, 移植他人的sdk 修改而来
 *  1. 配置需求, 必须配置别名 @cert 在可以被物理路径访问的目录
 *  2. 而 @cert/weixin 目录表示微信存放证书的目录
 *  3. 证书通过ftp 上传到此目录,
 *  4. 证书的名称前缀统一使用$this->config['appid'] 的字符串作为前缀
 *
 *@package
 *@author yuzhiyang <yangshihe@qq.com>
 *@copyright DNA <http://www.Noooya.com/>
 *@version 1.0.0
 *@since 2016年8月22日
 *@todo
 */

use Yii;
use yii\helpers\Json;


class Weixinpay
{

	private $config;

	public $error = null;

	// 证书pem
	private $_apiclient_cert = '';
	//证书密钥
	private $_apiclient_key = '';

	const PREPAY_GATEWAY = 'https://api.mch.weixin.qq.com/pay/unifiedorder';

	const QUERY_GATEWAY = 'https://api.mch.weixin.qq.com/pay/orderquery';

	const REFUND_GATEWAY = 'https://api.mch.weixin.qq.com/secapi/pay/refund';

	public function __construct($config)
    {

		$this->config = $config;

		if (!isset($this->config['appid']) || !isset($this->config['appsecret'])) {

			throw new \yii\web\ConflictHttpException('appid OR appsecret MUST be set.');
		}

		if (!isset($this->config['mch_id'])) {

			throw new \yii\web\ConflictHttpException('mch_id MUST be set.');
		}

        $cert = Yii::getAlias($this->config['cert_path']);

        $this->config['SSLCERT_PATH'] = $cert . DIRECTORY_SEPARATOR . $this->config['appid'] . '_cert.pem';

        $this->config['SSLKEY_PATH'] = $cert . DIRECTORY_SEPARATOR . $this->config['appid'] . '_key.pem';


	}

	public function getPrepayId($body, $out_trade_no, $total_fee, $notify_url, $trade_type = 'JSAPI')
    {

		$data = array();

		$data['appid'] = $this->config['appid'];

		$data['mch_id'] = $this->config['mch_id'];

		$data['nonce_str'] = $this->getNonceString();

		$data['body'] = $body;

		$data['out_trade_no'] = $out_trade_no;

		$data['total_fee'] = $total_fee;

		$data['spbill_create_ip'] = $_SERVER['REMOTE_ADDR'];

		$data['notify_url'] = $notify_url;

		$data['trade_type'] = $trade_type;

		$data['openid'] = $this->config['openid'];

		$result = $this->curlPost(self::PREPAY_GATEWAY, $data);

		if ($result['return_code'] == 'FAIL') {

			return $result['return_msg'];

		}

		if ($result['result_code'] == 'SUCCESS') {

			return $result['prepay_id'];

		} else {

			$this->error = $result['err_code_des'];

			return null;

		}

	}

	public function getRefund()
    {

		$data = array();

		$data['appid'] = $this->config['appid'];

		$data['mch_id'] = $this->config['mch_id'];

		$data['nonce_str'] = $this->getNonceString();

		$data['out_trade_no'] = $this->config['out_trade_no'];

		$data['out_refund_no'] = $this->config['out_trade_no'] . date('Y');

		$data['total_fee'] = $this->config['total_fee'];

		$data['refund_fee'] = $this->config['refund_fee'];

		$data['op_user_id'] = $this->config['mch_id'];

		$result = $this->curlPostSSL(self::REFUND_GATEWAY, $data);

		return $result;

	}

	public function getPackage($prepay_id)
    {

		$data = array();

		$data['appId'] = $this->config['appid'];

		$data['timeStamp'] = (string) time();

		$data['nonceStr'] = $this->getNonceString();

		$data['package'] = 'prepay_id=' . $prepay_id;

		$data['signType'] = 'MD5';

		$data['paySign'] = $this->getSign($data);

		return $data;

	}

	public function getBackData()
    {

		$xml = file_get_contents('php://input');

		$data = $this->xml2array($xml);

		if ($this->validate($data)) {

			return $data;

		} else {

			return null;

		}

	}

	public function responseBack($return_code = 'SUCCESS', $return_msg = null)
    {

		$data = array();

		$data['return_code'] = $return_code;

		if ($return_msg) {

			$data['return_msg'] = $return_msg;

		}

		$xml = $this->array2xml($data);

		echo $xml;

	}

	public function queryOrder($out_trade_no)
    {

		$data = array();

		$data['appid'] = $this->config['appid'];

		$data['mch_id'] = $this->config['mch_id'];

		$data['out_trade_no'] = $out_trade_no;

		$data['nonce_str'] = $this->getNonceString();

		$result = $this->curlPost(self::QUERY_GATEWAY, $data);

		if ($result['result_code'] == 'SUCCESS') {

			return $result['trade_state'];

		} else {

			$this->error = $result['err_code_des'];

			return null;

		}

	}

	public function array2xml($array)
    {

		$xml = '<xml>' . PHP_EOL;

		foreach ($array as $k => $v) {

			$xml .= '<' . $k . '><![CDATA[' . $v . ']]></' . $k . '>' . PHP_EOL;

		}

		$xml .= '</xml>';

		return $xml;

	}

	public function xml2array($xml)
    {

		$array = array();

		foreach ((array) simplexml_load_string($xml) as $k => $v) {

			$array[$k] = (string) $v;

		}

		return $array;

	}

	private function curlGet($url)
    {

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

		curl_setopt($ch, CURLOPT_URL, $url);

		curl_setopt($ch, CURLOPT_HEADER, 0);

		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$str = curl_exec($ch);

		curl_close($ch);

		return $str;

	}

	public function curlPost($url, $data)
    {

		$data['sign'] = $this->getSign($data);

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

		curl_setopt($ch, CURLOPT_POST, 1);

		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->array2xml($data));

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		curl_setopt($ch, CURLOPT_URL, $url);

		$content = curl_exec($ch);

		$array = $this->xml2array($content);

		return $array;

	}

	public function curlPostSSL($url, $data, $second = 30)
    {

		$data['sign'] = $this->getSign($data);

		$ch = curl_init();

		//超时时间

		curl_setopt($ch, CURLOPT_TIMEOUT, $second);

		//这里设置代理，如果有的话

		//curl_setopt($ch, CURLOPT_PROXY, '8.8.8.8');

		//curl_setopt($ch, CURLOPT_PROXYPORT, 8080);

		curl_setopt($ch, CURLOPT_URL, $url);

		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

		//设置header

		curl_setopt($ch, CURLOPT_HEADER, FALSE);

		//要求结果为字符串且输出到屏幕上

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

		//设置证书

		//使用证书：cert 与 key 分别属于两个.pem文件

		//默认格式为PEM，可以注释

		curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');

		curl_setopt($ch, CURLOPT_SSLCERT, $this->config['SSLCERT_PATH']);

		//默认格式为PEM，可以注释

		curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');

		curl_setopt($ch, CURLOPT_SSLKEY, $this->config['SSLKEY_PATH']);

		//post提交方式

		curl_setopt($ch, CURLOPT_POST, true);

		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->array2xml($data));

		$content = curl_exec($ch);

		//返回结果

        //var_dump($this->config);exit;

		if ($content) {

			curl_close($ch);

			$array = $this->xml2array($content);

			return $array;

		} else {

			$error = curl_errno($ch);

			echo "curl出错，错误码:$error" . "<br>";

			echo "<a href='http://curl.haxx.se/libcurl/c/libcurl-errors.html'>错误原因查询</a></br>";

			curl_close($ch);

			return false;

		}

	}

	public function getSign($data)
    {

		ksort($data);

		$str = '';

		foreach ($data as $k => $v) {

			if ($v) {

				$str .= $k . '=' . $v . '&';

			}

		}

		$stringSignTemp = $str . 'key=' . $this->config['key'];

		$sign = strtoupper(md5($stringSignTemp));

		return $sign;

	}

	public function validate($data)
    {

		if (!isset($data['sign'])) {

			return false;

		}

		$sign = $data['sign'];

		unset($data['sign']);

		return $this->getSign($data) == $sign;

	}

	public function getNonceString()
    {

		return str_shuffle('abcdefghijklmnopqrstuvwxyz');

	}

}
