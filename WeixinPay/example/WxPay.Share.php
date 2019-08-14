<?php


class WxShare
{
	private $appId="wx8a8958a29e6683ed";
	private $appSecret="1aeb3fd41686a0333bac9b1c2ddf0e22";

	

	public function getSignPackage($pageid)
	{
	$jsapiTicket = $this->getJsApiTicket();
	$url = $pageid;
	$timestamp = time();
	$nonceStr = $this->createNonceStr();

	// 这里参数的顺序要按照 key 值 ASCII 码升序排序
	$string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
	
	$signature = sha1($string);

	$signPackage = array(
	"appId" => $this->appId,
	"nonceStr" => $nonceStr,
	"timestamp" => $timestamp,
	"url" => $url,
	"signature" => $signature,
	"rawString" => $string
	);
	return $signPackage;
	}

	private function createNonceStr($length = 16)
	{
	$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	$str = "";
	for ($i = 0; $i < $length; $i++) {
	$str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
	}
	return $str;
	}

	private function getJsApiTicket()
	{
	// jsapi_ticket 应该全局存储与更新，以下代码以写入到文件中做示例
	$data = json_decode(file_get_contents(VENDOR_PATH."Weixinpay/example/jsapi_ticket.json"));
	if ($data->expire_time < time()) {
	$accessToken = $this->getAccessToken();
	$url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
	$res = json_decode($this->httpGet($url));
	$ticket = $res->ticket; //服务器，在本地会报错的，必须上传到服务器
	// $ticket = $res['ticket']; //本地
	if ($ticket) {
	$data->expire_time = time() + 7000;
	$data->jsapi_ticket = $ticket;
	$fp = fopen(VENDOR_PATH."Weixinpay/example/jsapi_ticket.json", "w");
	fwrite($fp, json_encode($data));
	fclose($fp);
	}
	} else {
	$ticket = $data->jsapi_ticket;
	}
	return $ticket;
	}

	private function getAccessToken()
	{
	// access_token 应该全局存储与更新，以下代码以写入到文件中做示例
	$data = json_decode(file_get_contents(VENDOR_PATH."Weixinpay/example/access_token.json"));
	if ($data->expire_time < time()) {
	$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->appId."&secret=".$this->appSecret;
	
	$res = json_decode($this->httpGet($url));
	$access_token = $res->access_token; //服务器，在本地会报错的，必须上传到服务器
	// $access_token = $res['access_token']; //本地
	if ($access_token) {
	$data->expire_time = time() + 7000;
	$data->access_token = $access_token;
	$fp = fopen(VENDOR_PATH."Weixinpay/example/access_token.json", "w");
	fwrite($fp, json_encode($data));
	fclose($fp);
	}
	} else {
	$access_token = $data->access_token;
	}
	return $access_token;
	}

	public function httpGet($url)
	{
		$ch = curl_init();
		$params[CURLOPT_URL] = $url;    //请求url地址
		$params[CURLOPT_HEADER] = false; //是否返回响应头信息
		$params[CURLOPT_RETURNTRANSFER] = true; //是否将结果返回
		 $params[CURLOPT_SSL_VERIFYPEER] = false;
        $params[CURLOPT_SSL_VERIFYHOST] = false;
		curl_setopt_array($ch, $params); //传入curl参数
        $content = curl_exec($ch); //执行
        curl_close($ch); //关闭连接
        return $content;	
	}
}





