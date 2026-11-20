<?php
namespace clogin_passport\lib;

class Oauth{
	private $apiurl;
	private $appid;
	private $appkey;
	private $callback;

	function __construct($config){
		$appurl = trim($config['appurl'] ?? '');
		$this->apiurl = $appurl === '' ? '' : rtrim($appurl, '/').'/connect.php';
		$this->appid = $config['appid'] ?? '';
		$this->appkey = $config['appkey'] ?? '';
		$this->callback = $config['callback'] ?? null;
	}

	//获取登录跳转url
	public function login($type){

		//-------生成唯一随机串防CSRF攻击
		$state = bin2hex(random_bytes(16));
		$_SESSION['echo_social_state'] = $state;

		//-------构造请求参数列表
		$keysArr = array(
			"act" => "login",
			"appid" => $this->appid,
			"appkey" => $this->appkey,
			"type" => $type,
			"redirect_uri" => $this->callback,
			"state" => $state
		);
		return $this->request($keysArr);
	}

	//登录成功返回网站
	public function callback($code = null, $type = ''){
		$code = $code ?? ($_GET['code'] ?? null);
		if (!is_string($code) || $code === '') {
			return null;
		}
		//-------请求参数列表（彩虹 API 的 callback 需要 type）
		$keysArr = array(
			"act" => "callback",
			"appid" => $this->appid,
			"appkey" => $this->appkey,
			"code" => $code,
			"type" => $type
		);

		return $this->request($keysArr);
	}

	//查询用户信息
	public function query($type, $social_uid){
		//-------请求参数列表
		$keysArr = array(
			"act" => "query",
			"appid" => $this->appid,
			"appkey" => $this->appkey,
			"type" => $type,
			"social_uid" => $social_uid
		);

		return $this->request($keysArr);
	}

	private function request($params){
		if ($this->apiurl === '') {
			return null;
		}
		$response = wp_remote_get($this->apiurl.'?'.http_build_query($params), array(
			'timeout' => 10,
			'redirection' => 0,
		));
		if (is_wp_error($response)) {
			return null;
		}
		$status = (int) wp_remote_retrieve_response_code($response);
		$data = json_decode(wp_remote_retrieve_body($response), true, 512, JSON_BIGINT_AS_STRING);
		if (!is_array($data)) {
			return null;
		}
		if ($status < 200 || $status >= 300) {
			return array('msg' => $data['msg'] ?? '');
		}
		return $data;
	}
}
