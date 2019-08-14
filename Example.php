<?php

namespace app\home\controller;
use think\Controller;
use think\Cookie;
error_reporting(0);

class example extends controller { 
	public function pay()
    {
        vendor('WeixinPay.example.WxPay#JsApiPay');//引入文件
		
        try{
			
	        $tools = new \JsApiPay();
			
			$OData=db("order")->where(["order_num"=>input("id"),"user_id"=>Cookie::get('userid','Mine_')])->find();
	        $openId = $tools->GetOpenid();
	        //②、统一下单
	        $input = new \WxPayUnifiedOrder();
	        $input->SetBody("商品支付");        //商品描述
	        $input->SetAttach(input("id"));    //附加数据暂未使用到可以注释掉	
	        $input->SetOut_trade_no(input("id"));//商户订单号,此处订单号根据实际项目中订单号进行赋值,要求32个字符内，只能是数字、大小写字母_-|* 且在同一个商户号下唯一
	        $input->SetTotal_fee($OData["order_price"]*100);      //订单总金额，单位为分
	        $input->SetTime_start(date("YmdHis"));//订单生成时间，格式为yyyyMMddHHmmss，如2009年12月25日9点10分10秒表示为20091225091010
	        $input->SetTime_expire(date("YmdHis", time() + 600));//订单失效时间，格式为yyyyMMddHHmmss，如2009年12月27日9点10分10秒表示为20091227091010。订单失效时间是针对订单号而言的，由于在请求支付的时候有一个必传参数prepay_id只有两小时的有效期，所以在重入时间超过2小时的时候需要重新请求下单接口获取新的prepay_id
	        //$input->SetGoods_tag("test");//订单优惠标记，使用代金券或立减优惠功能时需要的参数,项目暂未使用到，因此注释掉
	        $input->SetNotify_url("/ExampleNotify.html");//异步接收微信支付结果通知的回调地址，通知url必须为外网可访问的url，不能携带参数。
	        $input->SetTrade_type("JSAPI");
	        $input->SetOpenid($openId);
			$config = new \WxPayConfig();
	        $order = \WxPayApi::unifiedOrder($config, $input);
	       // echo '<font color="#f00"><b>订单支付</b></font><br/>';
	       // $this->printf_info($order);
	        $jsApiParameters = $tools->GetJsApiParameters($order);
 
	        //获取共享收货地址js函数参数
	        $editAddress = $tools->GetEditAddressParameters();
            //将数据渲染到模板中或前端页面中
            $this->assign("data",$jsApiParameters);
			$this->assign("money",$OData["order_price"]);
            return $this->fetch();
        } catch(Exception $e) {
	        Log::ERROR(json_encode($e));//此处因为没有使用微信日志方法，所以暂未引入日志类
        }
   

   }
	//扫码支付
	public function CodePay()
    {
		
        try{
			

	        $notify = new \NativePay();
			
			//进行数据库操作声明$map
			

			$input = new \WxPayUnifiedOrder();
			$input->SetBody($map["订单名称"]."\n辅助说明");
			$input->SetAttach("test");

			$input->SetOut_trade_no($map["订单号"]);
			$input->SetTotal_fee($map["订单金额"]*100);
			$input->SetTime_start(date("YmdHis"));
			//$input->SetTime_expire(date("YmdHis", time() + 600));
			$input->SetGoods_tag("test");
			$input->SetNotify_url("/Example/notify.html");
			$input->SetTrade_type("NATIVE");
			$input->SetProduct_id(intval(input("Sid")));

			$result = $notify->GetPayUrl($input);
			//dump($result);
			$url2 = $result["code_url"];
			$this->assign("Code",$url2);
			$this->assign("state",$state);
			$this->assign("num",$map["订单号"]);
			$this->assign("money",$map["订单金额"]);
            return $this->fetch();//前台模板渲染
        } catch(Exception $e) {
	        Log::ERROR(json_encode($e));//此处因为没有使用微信日志方法，所以暂未引入日志类
        }
   

    }
	//生成二维码
    function qrcode($url,$size=11){
    Vendor('WeixinPay.example.phpqrcode');
    // 如果没有http 则添加
    \QRcode::png($url,false,QR_ECLEVEL_L,$size,2,false,0xFFFFFF,0x000000);
    }
	//查询扫码状态
	public function orderquery(){
		vendor('WeixinPay.lib.WxPay#Notify');
		if(isset($_REQUEST["out_trade_no"]) && $_REQUEST["out_trade_no"] != ""){
			try{
				$out_trade_no = $_REQUEST["out_trade_no"];
				$input = new \WxPayOrderQuery();
				$input->SetOut_trade_no($out_trade_no);
				$config = new \WxPayConfig();
				//printf_info(WxPayApi::orderQuery($config, $input));
				$result=\WxPayApi::orderQuery($config, $input);
				//print_r($result);
				if($result['trade_state']=='SUCCESS'){
					db("订单")->where(["订单号"=>input("out_trade_no")])->update(["order_state"=>1,"transaction_id"=>$result['transaction_id']]);
				}
				return JSON($result);
				
			} catch(Exception $e) {
				Log::ERROR(json_encode($e));
			}
			exit();
		}
	}
	//扫码回调
	public function ExampleReturn()
    {
		vendor('WeixinPay.lib.WxPay#Notify');
        $notify = new \WxPayNotify();
        $notify->Handle();
    }
	public function printf_info($data)
    {
        foreach($data as $key=>$value){
            echo "<font color='#00ff55;'>$key</font> :  ".htmlspecialchars($value, ENT_QUOTES)." <br/>";
        }
    }
	public function notify(){
		$curl_request = $_SERVER['REQUEST_METHOD']; //获取请求方式
        if($curl_request == 'POST'){ 
            $xmldata=file_get_contents("php://input");
            libxml_disable_entity_loader(true);
            //把微信支付回调结果写入日志
            $this->writeLogs(RUNTIME_PATH.'Logs/','getPayMentCallBack',"\r\n-------------------".date('Y-m-d H:i:s')."微信支付回调结果---------\r\n---响应数据：".json_encode(simplexml_load_string($xmldata, 'SimpleXMLElement', LIBXML_NOCDATA))."\r\n------------\r\n");
            //处理微信支付返回的xml数据
            $data = json_encode(simplexml_load_string($xmldata, 'SimpleXMLElement', LIBXML_NOCDATA));
            $sign_return = json_decode($data,true)['sign'];
            $sign = $this->appgetSign(json_decode($data,true));
			$this->writeLogs(RUNTIME_PATH.'Logs/','getPayMentCallBack',"\r\n-------------------".date('Y-m-d H:i:s')."生成key---------\r\n---响应数据：".$sign."\r\n------------\r\n");
            //给微信返回接收成功通知，生成xml数据
            $this->returnXml();
            if($sign == $sign_return){
                //把数据提交给订单处理方法
                $this->state($data);
            }
			exit;
            
        }
	}
	
	private function notifyXmlToArray($xml)
    {
            libxml_disable_entity_loader(true);
            return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }
	
	public  function appgetSign($data){
		vendor('WeixinPay.example.WxPay#Config');//引入文件
		$config = new \WxPayConfig();
		$appwxpay_key = $config->GetKey();
		//签名步骤一：按字典序排序参数
		ksort($data);
		$String = $this->callbackToUrlParams($data);
		//签名步骤二：在string后加入KEY
		if($appwxpay_key){
			$String = $String."&key=".$appwxpay_key;
		}
		//签名步骤三：MD5加密
		$String = md5($String);
		//签名步骤四：所有字符转为大写
		$result_ = strtoupper($String);
		return $result_;
	}
	/**
     * 格式化参数格式化成url参数
     */
	public function callbackToUrlParams($Parameters){
        $buff = "";
        foreach ($Parameters as $k => $v){
            if($k != "sign" && $v != "" && !is_array($v)){
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        return $buff;
    }
	/**
     * @param  拼装xml数据返回 
     * @author  yangzl <[<email address>]>
     */
	public  function returnXml(){
        header("Content-type:text/xml;");
        $xml = "<?xml version='1.0' encoding='UTF-8'?>\n";
        $xml .= "<xml>\n";
        $xml .= "<return_code><![CDATA[SUCCESS]]></return_code>\n";
        $xml .= "<return_msg><![CDATA[OK]]></return_msg>\n";
        $xml .= "</xml>";
        echo  $xml;
    }
	
	public function state($data){
		$orders_info = json_decode($data,true);
		$Num=$orders_info ['attach'];
		//$Log=db("log")->insert(["log"=>$Num]);保存用户日志
		$This=db("order")->where("order_num",$Num)->find();
		if($This["order_state"]=='0'){
			//数据库操作
		}
	}

    public function writeLogs($path,$file,$content,$more=true){
        $newpath = '';
        if (!file_exists($path)) {
            mkdir ($path);
            @chmod ($path, 0777 );
        }
        if($more){
            $newpath .= $path.$file.@date('Y-m-d').".log";
        }else{
            $newpath .= $path.$file.".log";
        }       
        $content .="\r\n"."----------------------------------------------------------------------------------------------------------------"."\r\n";
        $this->write_file($newpath,$content,"a+");
    }
	/**
     * 写内容
     * @param  $filename   string   日志文件名
     * @param  $data       string   记录内容
     * @param  $method     
     * @author yanzl
     **/
    private function write_file($filename,$data,$method="rb+",$iflock=1){
        @touch($filename);
        $handle=@fopen($filename,$method);
        if($iflock){
            @flock($handle,LOCK_EX);
        }
        @fputs($handle,$data);
        if($method=="rb+") @ftruncate($handle,strlen($data));
        @fclose($handle);
        @chmod($filename,0777); 
        if( is_writable($filename) ){
            return 1;
        }else{
            return 0;
        }
    }
	public function success(){
		return $this->fetch();
	}
	
	public function tixianApi(){
		if('当前用户余额')>=input("money")){
			$map["openid"]='openid';
			$map["money"]=input("money");
			$Data=$this->transfer($map);
			if($Data["status"]==1){
				
				//return $Data;
				return JSON(["state"=>0,"msg"=>$Data[0]]);
			}	
			//数据库操作
			return JSON(["state"=>1,"msg"=>"提现成功!"]);
		}else{
			return JSON(["state"=>0,"msg"=>"可用积分不足"]);
		}
	}
	//退款
	public function refund($transaction,$price){
			$transaction_id = $transaction;
			$total_fee = $price*100;
			$refund_fee = $price*100;
			$input = new \WxPayRefund();
			$input->SetTransaction_id($transaction_id);
			$input->SetTotal_fee($total_fee);
			$input->SetRefund_fee($refund_fee);

			$config = new \WxPayConfig();
			$input->SetOut_refund_no("sdkphp".date("YmdHis"));
			$input->SetOp_user_id($config->GetMerchantId());
			$result=\WxPayApi::refund($config, $input);
			return $result["return_code"];
	}
	//提现到零钱 需要openid
	function transfer($data){
		vendor('WeixinPay.example.WxPay#Config');//引入文件
		$config = new \WxPayConfig();
    	//支付信息
    	$wxchat['appid'] = $config->GetAppId();
    	$wxchat['mchid'] = $config->GetMerchantId();
    	$webdata = array(
    			'mch_appid' => $wxchat['appid'],//商户账号appid
                'mchid'     => $wxchat['mchid'],//商户号
    			'nonce_str' => md5(time()),//随机字符串
                'partner_trade_no'=> date('YmdHis'), //商户订单号，需要唯一
    			'openid' => $data['openid'],//转账用户的openid
    			'check_name'=> 'NO_CHECK', //OPTION_CHECK不强制校验真实姓名, FORCE_CHECK：强制 NO_CHECK：
    			'amount' => $data['money']*100, //付款金额单位为分
    			'desc'   => '提现到零钱',//企业付款描述信息
    			'spbill_create_ip' => request()->ip(),//获取IP
    	);
    	foreach ($webdata as $k => $v) {
    		$tarr[] =$k.'='.$v;
    	}
    	sort($tarr);
    	$sign = implode('&',$tarr);
    	$sign .= '&key='.$config->GetKey();
    	$webdata['sign']=strtoupper(md5($sign));
    	$wget = $this->ArrToXml($webdata);//数组转XML
    	$pay_url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';//api地址
    	$res = $this->postData($pay_url,$wget);//发送数据
    	if(!$res){
    		return array('status'=>1, 'msg'=>"无法连接到服务器" );
    	}
    	$content = simplexml_load_string($res, 'SimpleXMLElement', LIBXML_NOCDATA);
    	if(strval($content->return_code) == 'FAIL'){
    		return array('status'=>1, 'msg'=>strval($content->return_msg));
    	}
    	if(strval($content->result_code) == 'FAIL'){
    		return array('status'=>1, 'msg'=>strval($content->err_code),':'.strval($content->err_code_des));
    	}
    	$rdata = array(
				'status'		   => 2,
    			'mch_appid'        => strval($content->mch_appid),
    			'mchid'            => strval($content->mchid),
    			'device_info'      => strval($content->device_info),
    			'nonce_str'        => strval($content->nonce_str),
    			'result_code'      => strval($content->result_code),
    			'partner_trade_no' => strval($content->partner_trade_no),
    			'payment_no'       => strval($content->payment_no),
    			'payment_time'     => strval($content->payment_time),
    	);
    	return $rdata;
    }
	
	function ArrToXml($arr){
		if(!is_array($arr) || count($arr) == 0){ 
			return;
		}
		$xml="<xml>";
		foreach ($arr as $key=>$val)
		{
			if (is_numeric($val)){
				$xml.="<".$key.">".$val."</".$key.">";
			}else{
				$xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
			}
		}
		$xml.="</xml>";
		return $xml;
	}
	//发送数据
    function postData($url,$postfields){
        $ch = curl_init();
        $params[CURLOPT_URL] = $url;    //请求url地址
        $params[CURLOPT_HEADER] = false; //是否返回响应头信息
        $params[CURLOPT_RETURNTRANSFER] = true; //是否将结果返回
        $params[CURLOPT_FOLLOWLOCATION] = true; //是否重定向
        $params[CURLOPT_POST] = true;
        $params[CURLOPT_POSTFIELDS] = $postfields;
        $params[CURLOPT_SSL_VERIFYPEER] = false;
        $params[CURLOPT_SSL_VERIFYHOST] = false;
        //以下是证书相关代码
        $params[CURLOPT_SSLCERTTYPE] = 'PEM';
        $params[CURLOPT_SSLCERT] = getcwd().'/../vendor/WeixinPay/cert/证书CERT.pem';//绝对路径
        $params[CURLOPT_SSLKEYTYPE] = 'PEM';
        $params[CURLOPT_SSLKEY] = getcwd().'/../vendor/WeixinPay/cert/证书Key.pem';//绝对路径
        curl_setopt_array($ch, $params); //传入curl参数
        $content = curl_exec($ch); //执行
        curl_close($ch); //关闭连接
        return $content;
    }

}
