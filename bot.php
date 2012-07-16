<?php
/**
 * 12306 刷票
 * Special thx 2 @也云
 * 
 * @author sskaje sskaje@gmail.com http://weibo.com/sskaje 
 */

date_default_timezone_set('Asia/Shanghai');

define('SPBOT_12306_DECAPTCHA_MODE_INPUT',     1);
define('SPBOT_12306_DECAPTCHA_MODE_TESSERACT', 2);

$config_file = __DIR__ . '/config.php';
if (isset($argv[2]) && ($cfg = trim($argv[2]))) {
	if (file_exists($cfg)) {
		$config_file = $cfg;
	} else if (file_exists(__DIR__ . "/config/{$cfg}.php")) {
		$config_file = __DIR__ . "/config/{$cfg}.php";
	}
}

$config = include($config_file);

PHP_OS == "WINNT" or die("Sorry, but Windows only unless you are about to change the default image viewer program.");

$bot = new sp12306bot;
$bot->set_config($config);
$bot->init();

$bot->login();

if (!isset($argv[1]) || !in_array($argv[1], array('pay', 'simple_pay', 'cookie'))) {
query_ticket:
	$t = $bot->query_ticket();
	if (!isset($argv[1]) || !isset($t[$argv[1]])) {
		MessageBox::msgbox('Check ur ticket now!');
		do {
			Logger::Out('Available options: ' . implode(', ', array_keys($t)) . ' E(x)it (R)etry (S2)=>Sleep 2 seconds');
			$opt = stdin();
		} while(!isset($t[$opt]));
		Logger::Out("Option selected: {$opt}");
	} else {
		$opt = $argv[1];
	}
	
	$confirm = $bot->confirm($t[$opt]);
	Logger::Out("Option selected: {$opt}");
	
	if (!$confirm) {
		goto query_ticket;
	}
} else if ($argv[1] == 'simple_pay') {
	$bot->simple_pay($config['ticket_key'], $config['sequence_no']);
	exit;
} else if ($argv[1] == 'cookie') {
	$bot->show_cookie_js();
	exit;
}
Logger::Out('Finished ? ');

MessageBox::msgbox('订票成功！！！正在尝试获取支付页面！！！');

# request for pay link
$s = $bot->pay_order();


define('SPBOT_12306_DECAPTCHA_MODE_INPUT',     1);
define('SPBOT_12306_DECAPTCHA_MODE_TESSERACT', 2);

class sp12306bot
{
	protected $convert_path		= '"C:/Program Files/ImageMagick-6.7.3-Q16/convert.exe"';
	protected $tesseract_path	= 'd:/Apps/Tesseract/tesseract.exe -l hcp -psm 7';
	protected $browser_path     = '"C:/Program Files (x86)/Internet Explorer/iexplore.exe"';
	
	protected $login_user;
	protected $login_pass;
	protected $login_realname;
	
	protected $train_date;
	protected $train_from;
	protected $train_to;
	protected $train_time;
	
	protected $train_no;
	protected $train_passtype;
	protected $train_class;
	
	protected $return_train_date;
	protected $return_train_time;
	
	protected $return_train_passtype;
	protected $return_train_class;
	
	protected $single_round_type = 1;# 1 单程 2 返程
	
	protected $tickets_info = array();
	
	protected $seats;
	
	protected $seat_detail = array(
		 array(
	 		'seat'		=>	4,	# @see $seatnames
	 		'ticket'	=>	1,
	 		'name'		=>	'测试',
	 		'cardtype'	=>	2,	# 1二代身份证,2一代身份证,C港澳通行证,G台湾通行证,B护照
	 		'cardno'	=>	'110110800101123',
	 		'mobileno'	=>	'13011112222',
		 ),
	);
	
	protected $seat_order = array();
	
	/**
	 * 座位编号名称
	 *
	 * @var array
	 */
	protected $seat_names = array(
		'S' =>  '一等包座',
		'M' =>  '一等座',
		'O' =>  '二等座',
		'P' =>  '特等座',
		'Q' =>  '观光座',
		1   =>  '硬座',
		2   =>  '软座',
		3   =>  '硬卧',
		4   =>  '软卧',
		6   =>  '高级软卧',
		9   =>  '商务座',
	);
	/**
	 * 查票的查询字段
	 *
	 * @var array
	 */
	protected $query_fields = array(
		array('序号', null),
		array('车次', null),
		array('始发', null),
		array('到达', null),
		array('历时', null),
		array('商务座', 9),
		array('特等座', 'P'),
		array('一等座', 'M'),
		array('二等座', 'O'),
		array('高级软卧', 6),
		array('软卧', 4),
		array('硬卧', 3),
		array('软座', 2),
		array('硬座', 1),
		array('无座', null),
		array('其他', null),
		array('购票', null),
	);
	
	protected $seat_field_map = array();
	
	public function __construct()
	{
	}
	
	public function init()
	{
		$this->init_seat_field_map();
		$this->init_curl();
		# request for station names
		$this->request_station_names();
	}
	
	protected function init_seat_field_map() 
	{
		foreach ($this->query_fields as $k=>$v) {
			if ($v[1] !== null) {
				$this->seat_field_map[$v[1]] = $k;
			}
		}
	}
	
	protected $curl;
	
	protected function init_curl() 
	{
		$header = array(
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: zh-cn,zh;q=0.5',
			'Accept-Charset: GB2312,utf-8;q=0.7,*;q=0.7',
			'Connection: keep-alive',
			'Cache-Control: max-age=0',
		);
	
		$this->curl = curl_init();
		
		$cookiefile = $this->get_cookie_file();
		curl_setopt($this->curl, CURLOPT_COOKIEJAR,  $cookiefile);
		curl_setopt($this->curl, CURLOPT_COOKIEFILE, $cookiefile);
		curl_setopt($this->curl, CURLOPT_VERBOSE, 1);
		curl_setopt($this->curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:8.0) Gecko/20100101 Firefox/8.0');
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->curl, CURLOPT_HEADER, 0);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $header);
		curl_setopt($this->curl, CURLOPT_COOKIESESSION, 1);
		curl_setopt($this->curl, CURLOPT_TIMEOUT, 60);
		curl_setopt($this->curl, CURLOPT_ENCODING, 'gzip');
	
		return $this->curl;
	}
	
	protected function get_cookie_file()
	{
		return $this->login_user . $this->cookiejar;
	}
	
	protected $cookiejar = '_cookie.txt';
	
	public function __destruct()
	{
		$this->flush_curl();
	}
	
	protected function exec_curl()
	{
		$ret = curl_exec($this->curl);
		if (false === $ret) {
			Logger::Out('Curl Exec failed, retrying...');
			return $this->exec_curl();
		} else {
			return $ret;
		}
	}
	
    protected function is_403()
    {
    	$info = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
    	return $info == '403';
    }
    
    protected function test_403()
    {
    	if ($this->is_403()) {
            Logger::Out('403 Forbidden');
            return true;
        } else {
            return false;
        }
    }
	protected function is_200()
	{
		$info = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
		return $info == '200';
	}
	
	protected function flush_curl()
	{
		curl_close($this->curl);
		if (!file_exists($this->get_cookie_file())) {
			Logger::Out('Cookie jar file not found');
		}
		
		$time = time() + 3600 * 24;
		# update expiration
		file_put_contents(
			$this->get_cookie_file(),
			str_replace(
				"\t0\t",
				"\t{$time}\t",
				file_get_contents($this->get_cookie_file()) 
			)
		);
        Logger::Out('Cookie jar updated. (Expiration only)');
	}
	
	public function show_cookie_js()
	{
		$f = $this->get_cookie_file();
		if (!is_file($f)) {
			Logger::Out("Cookie File '{$f}' Not Found.");
			return false;
		}
		
		$s = file($f);
		if (isset($s[4])) {
			for ($i=4; isset($s[$i]); $i++) {
				$tmp = explode("\t", trim($s[$i]));
				Logger::Out("INLINE SCRIPT: javascript:alert(document.cookie=\"{$tmp[5]}={$tmp[6]}; path=/\")");
			}
		}
	}
	/**
	 * 加载配置
	 * 
	 * @param array $config
	 */
	public function set_config($config)
	{
		$this->login_user		= $config['login_user'];
		$this->login_pass		= $config['login_pass'];
		$this->login_realname	= $config['login_realname'];
		
		$this->train_date		= $config['train_date'] ? : date('Y-m-d', time()+86400*11);
		$this->train_from		= $config['train_from'];
		$this->train_to			= $config['train_to'];
		$this->train_time		= $config['train_time'];
		$this->train_no			= $config['train_no'];
		$this->train_passtype	= $config['train_passtype'];
		$this->train_class		= $config['train_class'];
		$this->seat_detail		= $config['seat_detail'];
		
		$this->single_round_type= $config['single_round_type'];
		
		$this->return_train_date		= $config['return_train_date'];
		$this->return_train_time		= $config['return_train_time'];
		
		$this->return_train_passtype	= $config['return_train_passtype'];
		$this->return_train_class		= $config['return_train_class'];
		
		$this->tickets_info		= $config['tickets_info'];
		
		return true;
	}
	
	/**
	 * 验证码识别
	 * 
	 * @param string $url
	 * @param int $hit
	 */
	protected function decaptcha($url, $is_login=true, $hit=0) 
	{
        $flag_input =
            $is_login ?
                defined('SPBOT_12306_DECAPTCHA_LOGIN') && SPBOT_12306_DECAPTCHA_LOGIN == SPBOT_12306_DECAPTCHA_MODE_INPUT
                :
                defined('SPBOT_12306_DECAPTCHA_CONFIRM') && SPBOT_12306_DECAPTCHA_CONFIRM == SPBOT_12306_DECAPTCHA_MODE_INPUT
            ;
            
        #$flag_input = 1;
		
		curl_setopt($this->curl, CURLOPT_URL, $url . (strpos($url, '?') === false ? '?' : '&') . mt_rand());
		curl_setopt($this->curl, CURLOPT_TIMEOUT, $flag_input ? 20 : 15);
		curl_setopt($this->curl, CURLOPT_HTTPGET, 1);
		curl_setopt($this->curl, CURLOPT_POST,    0);
		
		do {
			$o = $this->exec_curl();
		} while (stripos($o, '<html') !== false && false !== sleep(1));
		
		Logger::File('out.jpg', $o);
	
		if ($flag_input) {
			Logger::Out("Trying to open image...");
#			exec("C:/windows/system32/mspaint.exe out.jpg");
			exec("out.jpg");
			Logger::Out("Input captcha you read:");
			$decaptcha = stdin();
		} else {
#			exec("{$this->convert_path} out.jpg out.tiff 2>&1 >nul");
#			exec("{$this->tesseract_path} out.tiff out 2>&1 >nul");

#			passthru("{$this->convert_path} -compress none -depth 8 -alpha off -colorspace Gray out.jpg out.tif 2>&1 >nul");
#			passthru("{$this->convert_path} out.tif -scale 110% out.tif 2>&1 >nul");
#			passthru("{$this->tesseract_path} out.tif out 2>&1");

			exec("{$this->tesseract_path} out.jpg out 2>&1 >nul");
		
			$decaptcha = str_replace(' ', '', trim(file_get_contents('out.txt')));
			Logger::Out('Decaptcha: hit ' . $hit);
		}
		
		if (!$this->decaptcha_valid($decaptcha)) {
			return $this->decaptcha($url, $is_login, ++$hit);
		} else {
			#rename('out.jpg', 'decaptcha_' . $decaptcha . '.jpg');
			return $decaptcha;
		}
	}
	/**
	 * 判断验证码结果是否合法
	 * 
	 * @param string $decaptcha
	 * @return boolean
	 */
	protected function decaptcha_valid($decaptcha) 
	{
		return preg_match('#^[A-Z0-9]{4}$#', $decaptcha);
	}
	
	protected function get_message($o)
	{
		$m = array();
		if (preg_match('#var message = "(.+)";#', $o, $m)) {
			return $m[1];
		} else {
			return null;
		}
	}
	protected function get_formtoken($o)
	{
	    $m = array();
	    if (preg_match('#<input type="hidden" name="org.apache.struts.taglib.html.TOKEN" value="(.+)">#', $o, $m)) {
	        return $m[1];
	    } else {
	        return null;
	    }
	}
	protected function get_sequence_no($o)
	{
	    $m = array();
	    if (preg_match('#<input type="hidden" name="sequence_no" value="(.+)">#', $o, $m)) {
	        return $m[1];
	    } else {
	        return null;
	    }
	}
	
	
	protected function is_logged_in($html = '')
	{
		if (!empty($html)) {
			# 比较串
			if (strpos($html, 'isLogin= true')) {
				Logger::Out('Login: Already logged in as ' . $this->login_user);
				return true;
			} else {
				Logger::Out('Login required. ');
				return false;
			}
        } else {

            $test_login_url = 'https://dynamic.12306.cn/otsweb/passengerAction.do?method=initUsualPassenger';
			$test_login_url = 'https://dynamic.12306.cn/otsweb/loginAction.do?method=initForMy12306';
			curl_setopt($this->curl, CURLOPT_URL,     $test_login_url);
            curl_setopt($this->curl, CURLOPT_REFERER, $test_login_url);
            $count_403 = 0;
retry_test_login:
            $o = $this->exec_curl();
            Logger::File('test_login.txt', $o);
            if ($this->test_403()) {
                Logger::Out("Retry test login: " . ++$count_403);
                if ($count_403 < 10) {
                    if ($count_403 % 5 == 0) {
                        sleep(1);
                    }
                    goto retry_test_login;
                }
            }

			if (!$o || !$this->is_200()) {
				return false;
			} else {
				$m = array();
				if (null !== ($m = $this->get_message($o))) {
				    Logger::Out('Login required. ' . $m);
				    return false;
				} else {
				    Logger::Out('Login: Already logged in as ' . $this->login_user);
				    return true;
				}
		}
		}
	}
	/**
	 * 登录尝试
	 */
	public function login()
	{
		if (!$this->is_logged_in()) {
			$login = false;
			$login_attempts = 0;
			$refresh_captcha = true;
			do {
				Logger::Out("Login Attempts: {$login_attempts}");
				if ($refresh_captcha) {
					$decaptcha = $this->decaptcha('https://dynamic.12306.cn/otsweb/passCodeAction.do?rand=lrand', true);
					Logger::Out("Login Captcha: {$decaptcha}");
				} else {
					Logger::Out("Reuse Login Captcha: {$decaptcha}");
				}
				
				$o = '';
				$login = $this->do_login($decaptcha, $o, $refresh_captcha);
				++ $login_attempts;
				
				if ($login_attempts % 10 == 0) {
					Logger::Out("Login Sleep: 2");
					sleep(2);
				}
			} while (!$login || !$this->is_logged_in($o));
		}
		
		# 成功后先关闭curl再重开
		$this->flush_curl();
		$this->init_curl();
		
		Logger::Out('Login success.');
		return true;
	}
	/**
	 * 执行登录
	 * 
	 * @param string $decaptcha
	 */
	protected function do_login($decaptcha, &$o=null, &$refresh_captcha=true) 
	{
		if (empty($this->login_user) || empty($this->login_pass)) {
			_err('登录用户名密码未指定');
			exit;
		}
		Logger::Out("Login in as {$this->login_user}");
		$postfields = '';
		$postfields .= 'loginUser.user_name='.urlencode($this->login_user);
		$postfields .= '&nameErrorFocus=&user.password=' . urlencode($this->login_pass);
		$postfields .= '&passwordErrorFocus=&randCode='.urlencode($decaptcha);
		$postfields .= '&randErrorFocus=';
		
		curl_setopt($this->curl, CURLOPT_REFERER, 'https://dynamic.12306.cn/otsweb/loginAction.do?method=init');
		curl_setopt($this->curl, CURLOPT_URL, 'https://dynamic.12306.cn/otsweb/loginAction.do?method=login');
		curl_setopt($this->curl, CURLOPT_POST, 1);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postfields);
		curl_setopt($this->curl, CURLOPT_TIMEOUT, 30);
		$o = $this->exec_curl();
		
	    Logger::File('login.html', $o);
	    
        if ($this->test_403()) {
            sleep(1);
            return false;
        }
        
        if (strpos($o, 'var isLogin= false')) {
	    	if (strpos($o, '请输入正确的验证码') !== false) {
	    		$refresh_captcha = true;
	    		Logger::Out('Login failed: bad captcha');
	    	} else {
	    		$refresh_captcha = false;
	    	}
	    	
        	if (null !== ($m = $this->get_message($o))) {
	    		Logger::Out("Login failed: {$m}");
	    		if (strpos($m, '密码错误') !== false) {
	    			exit;
	    		}
        	}
        	return false;
        } else {
        	return true;
        }
	}
	/**
	 * 查询余票
	 */
	public function query_ticket()
	{
		$ret = array();
		foreach ($this->tickets_info as $k=>$row) {
			$tmp = explode('#', $row);
			Logger::Out("Query ticket[preset]: [{$k}] {$tmp[0]}({$row})");
			$ret[$k] = $tmp;
		}
		return $ret;
	}
	
	protected $station_names;
	
	public function request_station_names() 
	{
		if (!($data = cache_get('station_name'))) {	
			$url = 'https://dynamic.12306.cn/otsweb/js/common/station_name.js?rand='.mt_rand();
			curl_setopt($this->curl, CURLOPT_URL, $url);
			curl_setopt($this->curl, CURLOPT_HTTPGET, 1);
			curl_setopt($this->curl, CURLOPT_POST, 0);
			curl_setopt($this->curl, CURLOPT_TIMEOUT, 60);
			$o = $this->exec_curl();
			
			$m = array();
			if (!preg_match('#\'(.+)\'#', $o, $m)) {
				return false;
			}
			$s = explode('@', $m[1]);
			$data = array();
			foreach ($s as $_s) {
				if (!$_s) {
					continue;
				}
	
				$t = explode('|', $_s);
				$data[$t[2]] = $t;
			}
	
			cache_set('station_name', $data, 86400 * 10);
		}
		return $this->station_names = $data;
	}
	
	protected function parse_submit($o, &$refresh_captcha=false)
	{
		$refresh_captcha = false;
		if (null !== ($m = $this->get_message($o))) {
			Logger::Out('Error: ' . $m);
			if (false !== strpos($m, '验证码')) {
				$refresh_captcha = true;
            } else if (
                false === strpos($m, '提交订单用户过多')
                && false === strpos($m, '重复提交')
            ) {
				exit;
			}
		}

		return $this->get_formtoken($o);
	}
	
	public function confirm($opt)
	{
		# request for order
		$confirm = false;
		$confirm_attempts = 0;
		$refresh_captcha = true;
		$last_token = '';
		$token = '';
		
		do {
			Logger::Out("Confirm Attempts: {$confirm_attempts}");
			if ($refresh_captcha) {
				$decaptcha = $this->decaptcha('https://dynamic.12306.cn/otsweb/passCodeAction.do?rand=randp', false);
				Logger::Out("Confirm Captcha: {$decaptcha}");
			} else {
				Logger::Out("Reuse Confirm Captcha: {$decaptcha}");
			}
			if ($last_token == $token || empty($token)) {
				$token = '';
				do {
					$r = $this->fetch_incompleted();
					#$r = $this->fetch_incompleted('https://dynamic.12306.cn/otsweb/order/myOrderAction.do?method=init&showMessage=Y');
					$token = $r['token'];
					 
					if (isset($r['ticket_key'])) {
						Logger::Out("Ticket key found on incompleted order.");
						return true;
					}
				} while (!$token);
				Logger::Out("Read token from incompleted-orders");
			}
			
			$last_token = $token;
			Logger::Out("Using token: {$token}");
			$confirm = $this->do_confirm_order($opt, $token, $decaptcha, $refresh_captcha);
			
			++ $confirm_attempts;
		} while (!$confirm);
		
		return $confirm;
	}
	
	protected function do_confirm_order($req, & $token, $decaptcha, &$refresh_captcha)
	{
		# 单程票
		$url = 'https://dynamic.12306.cn/otsweb/order/confirmPassengerAction.do?method=confirmPassengerInfoSingle';
		
		# Z65#10:15#20:00#2400000Z6505#BXP#JJG#06:15#北京西#九江#302910002440458000026084200001
		$post[] = 'org.apache.struts.taglib.html.TOKEN=' . urlencode($token);
		$post[] = 'orderRequest.reserve_flag=A';
		$post[] = 'orderRequest.train_date=' . urlencode($this->train_date);
		$post[] = 'orderRequest.train_no=' . urlencode($req[3]); # 240000Z13304';
		$post[] = 'orderRequest.station_train_code=' . urlencode($req[0]); #Z133';
		$post[] = 'orderRequest.from_station_telecode=' . urlencode($req[4]); #BXP';
		$post[] = 'orderRequest.to_station_telecode=' . urlencode($req[5]); #JJG';
		$post[] = 'orderRequest.seat_type_code=';
		$post[] = 'orderRequest.ticket_type_order_num=';
		$post[] = 'orderRequest.bed_level_order_num=000000000000000000000000000000';
		$post[] = 'orderRequest.start_time=' . urlencode($req[2]); #19%3A45';
		$post[] = 'orderRequest.end_time=' . urlencode($req[6]); #06%3A09';
		$post[] = 'orderRequest.from_station_name=' . urlencode($req[7]); #%E5%8C%97%E4%BA%AC%E8%A5%BF';
		$post[] = 'orderRequest.to_station_name=' . urlencode($req[8]); #%E4%B9%9D%E6%B1%9F';
		$post[] = 'orderRequest.cancel_flag=1';
		$post[] = 'orderRequest.id_mode=Y';
		$post[] = 'randCode=' . $decaptcha;
		
		$post[] = 'checkbox0=0';
		$post[] = 'textfield=%E4%B8%AD%E6%96%87%E6%88%96%E6%8B%BC%E9%9F%B3%E9%A6%96%E5%AD%97%E6%AF%8D';
		$i = 0;
		foreach ($this->seat_detail as $s) {
			++$i;
			$post[] = 'passengerTickets='.urlencode("{$s['seat']},{$s['ticket']},{$s['name']},{$s['cardtype']},{$s['cardno']},{$s['mobileno']},Y");
			$post[] = 'oldPassengers=' . urlencode("{$s['name']},{$s['cardtype']},{$s['cardno']}");
			$post[] = "passenger_{$i}_seat={$s['seat']}";
			$post[] = "passenger_{$i}_ticket={$s['ticket']}";
			$post[] = "passenger_{$i}_name=" . urlencode($s['name']);
			$post[] = "passenger_{$i}_cardtype={$s['cardtype']}";
			$post[] = "passenger_{$i}_cardno={$s['cardno']}";
			$post[] = "passenger_{$i}_mobileno={$s['mobileno']}";
		}

		for ($c=5-$i; $c>0; $c--) {
			$post[] = 'oldPassengers=&checkbox9=Y';
		}
		$postfields = implode('&', $post);
		
		Logger::Out("post fields: {$postfields}");
		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_POST, 1);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postfields);
		curl_setopt($this->curl, CURLOPT_REFERER, 'https://dynamic.12306.cn/otsweb/order/confirmPassengerAction.do?method=init');
		curl_setopt($this->curl, CURLOPT_TIMEOUT, 180);
		
		$o = $this->exec_curl();
		$msg = $this->get_message($o);
		$refresh_captcha = false;
		if ($msg) {
			Logger::Out("Message: {$msg}");
			if (strpos($msg, '验证码') !== false) {
				$refresh_captcha = true;
			}
		}
		# 验证码策略改了
		$refresh_captcha = true;
		
		if (strpos($o, '席位已成功锁定')) {
			Logger::File('cfm_success.txt', $o);
			Logger::Out('Done: 支付去吧');
			return $o;
		} else if (strpos($msg, '未处理的订单') !== false) {
			MessageBox::msgbox('有未支付订单');
			return $o;
		} else {
			Logger::File('cfm_fail.txt', $o);
			$token = $this->get_formtoken($o);
			return false;
		}
	}
	
	protected function fetch_incompleted($url = null)
	{
		$referer = 'https://dynamic.12306.cn/otsweb/loginAction.do?method=initForMy12306';

		if ($url === null) {
			$url = 'https://dynamic.12306.cn/otsweb/order/myOrderAction.do?method=queryMyOrderNotComplete';
		}
		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_REFERER, $referer);
		curl_setopt($this->curl, CURLOPT_POST, 0);
		curl_setopt($this->curl, CURLOPT_HTTPGET, 1);
		curl_setopt($this->curl, CURLOPT_TIMEOUT, 60);
		
		$o = $this->exec_curl();
		Logger::File('incompleted.html', $o);

		$m = array();
		if (preg_match('#cancelOrder\(\'(.+)\'\)#', $o, $m)) {
			$ret = array();
			$ret['seq'] = $m[1];
			preg_match('#value="('.$ret['seq'].'.+)"#', $o, $m);
			$ret['ticket_key'] = $m[1];
			$ret['token'] = $this->get_formtoken($o);
			return $ret;
		} else if (null === ($m = $this->get_message($o))) {
			return array(
				'token'	=>	$this->get_formtoken($o),
			);
		} else {
			Logger::Out('Read incompleted order failed');
			usleep(300000);
			return $this->fetch_incompleted();
		}
	}
	
	public function pay_order()
	{
	    $ret = $this->fetch_incompleted();
	    if (!isset($ret['ticket_key']) || empty($ret['ticket_key'])) {
	    	Logger::Out('Error: 没有待支付订单');
	    	exit;
	    }
	    $url = 'https://dynamic.12306.cn/otsweb/order/myOrderAction.do?method=laterEpay&orderSequence_no=' . urlencode($ret['seq']) . '&con_pay_type=epay';
	    
	    $postfields = 'queryOrderDTO.from_order_date=&queryOrderDTO.to_order_date=';
	    $postfields .= '&org.apache.struts.taglib.html.TOKEN='.urlencode($ret['token']);
	    $postfields .= '&ticket_key='.urlencode($ret['ticket_key']);
	
	    curl_setopt($this->curl, CURLOPT_URL, $url);
	    curl_setopt($this->curl, CURLOPT_POST, 1);
	    curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postfields);
	    curl_setopt($this->curl, CURLOPT_TIMEOUT, 60);
	    
	    $o = $this->exec_curl();
	    Logger::File('pay_order.txt', $o);
	    
	    if (!strpos($o, '席位已成功锁定')) {
	    	$m = $this->get_message($o);
	    	Logger::Out('Pay order: 支付失败，重试中...' . $m);
	    	usleep(500000);
	    	return $this->pay_order();
	    }
	    $m = array();
	    if (preg_match('#<form id="epayForm".+</form>#sU', $o, $m)) {
			$pay_script = <<<HTML
<script type="text/javascript">
function pay() {
	var form =document.getElementById("epayForm");
	    form.submit();
	}
pay();
</script>
HTML;
	    	Logger::File('payorder.html', $m[0] . $pay_script);
			
			Logger::Out('Trying to popup browser');
			 
			$cmd = $this->browser_path.' '.getcwd().'/payorder.html';
			system($cmd);
			exit;
	    } else {
	    	Logger::Out('Pay error: 无法找到支付表单');
	    	return $this->pay_order();
	    }
	    
	    $token = $this->get_formtoken($o);
	    $seq   = $this->get_sequence_no($o);
	    $batch_no = '1#';
	
	    $postfields = '';
	    $postfields .= 'org.apache.struts.taglib.html.TOKEN='.urlencode($token);
	    $postfields .= '&sequence_no='.urlencode($seq);
	    $postfields .= '&batch_no='.urlencode($batch_no);
	    
	    $url = 'https://dynamic.12306.cn/otsweb/order/payConfirmOnlineSingleAction.do?method=payConfirmOnlineSingleEPay';
	    curl_setopt($this->curl, CURLOPT_URL, $url);
	    curl_setopt($this->curl, CURLOPT_POST, 1);
	    curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postfields);
	    
	    $o = $this->exec_curl();
	    Logger::File('pay_order_2.html', $o);
	
	    if (!strpos($o, 'interfaceName')) {
	    	Logger::Out('Pay order: 支付请求提交失败，重试中...' );
	    	return $this->pay_order();
	    }
	    Logger::Out('Trying to popup browser');
	    
	    $cmd = $this->browser_path.' '.getcwd().'/pay_order_2.html';
	    system($cmd);
	    exit;
	}
	
	public function simple_pay($ticket_key, $sequence_no, $batch_no='1#')
	{
		$token = '';
		do {
			#$r = $this->fetch_incompleted();
			$r = $this->fetch_incompleted('https://dynamic.12306.cn/otsweb/order/myOrderAction.do?method=init&showMessage=Y');
			$token = $r['token']; 
		} while (!$token);
		
		$postfields = '';
		$postfields .= 'org.apache.struts.taglib.html.TOKEN='.urlencode($token);
		$postfields .= '&sequence_no='.urlencode($sequence_no);
		$postfields .= '&batch_no='.urlencode($batch_no);
		 
		$url = 'https://dynamic.12306.cn/otsweb/order/payConfirmOnlineSingleAction.do?method=payConfirmOnlineSingleEPay';
		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_POST, 1);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postfields);
		
		
		$o = $this->exec_curl();
		Logger::File('pay_order_2.html', $o);
		
		if (!strpos($o, 'interfaceName')) {
			Logger::Out('Pay order: 支付请求提交失败，重试中...' );
			return $this->simple_pay($ticket_key, $sequence_no, $batch_no);
		}
		Logger::Out('Trying to popup browser');
		 
		$cmd = $this->browser_path.' '.getcwd().'/pay_order_2.html';
		system($cmd);
		exit;
	}
	
	public function cancel_order()
	{
		$ret = $this->fetch_incompleted();
		if (!isset($ret['ticket_key']) || empty($ret['ticket_key'])) {
			Logger::Out('Error: 没有待支付订单');
			exit;
		}
		$url = 'https://dynamic.12306.cn/otsweb/order/orderAction.do?method=cancelMyOrderNotComplete';
		$postfields = '';
		$postfields .= 'org.apache.struts.taglib.html.TOKEN='.urlencode($ret['token']);
		$postfields .= '&sequence_no='.urlencode($ret['seq']);
		
		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_POST, 1);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postfields);
		curl_setopt($this->curl, CURLOPT_TIMEOUT, 60);
		
		$o = $this->exec_curl();
		Logger::File('cancel.txt', $o);
		
		if (null === ($m = $this->get_message($o)) || false === strpos($m, '成功')) {
			Logger::Out('Cancel Order Failed: ' . $m);
			exit;
		} else {
			Logger::Out('Cancel Order: ' . $m);
		}
		usleep(500000);
	}
}


function stdin()
{
	$opt = fgets(STDIN);
	$opt = strtoupper(trim($opt));
	if ($opt === 'X' || $opt === ':X') {
		exit;
	} else if ($opt === ':MOFF') {
		MessageBox::moff();
	} else if ($opt === ':MON') {
		MessageBox::mon();
	}
	return $opt;
}

class Logger
{
	static public function File($file, $content)
	{
		return file_put_contents($file, $content);
	}
	
	static public function Out($msg)
	{
		$msg = date('[H:i:s] ') . trim($msg) . "\r\n";
		file_put_contents('_log_out.txt', $msg, FILE_APPEND);
		return fwrite(
			STDOUT, 
			iconv('UTF-8', 'GBK', $msg)
		);
	}
	
	static public function Err($msg)
	{
		$msg = date('[H:i:s] ') . trim($msg) . "\r\n";
		file_put_contents('_log_err.txt', $msg, FILE_APPEND);
		return fwrite(
			STDERR, 
			iconv('UTF-8', 'GBK', $msg)
		);
	}
}

class MessageBox
{
	static public function msgbox($message)
	{
		if (PHP_OS == 'WINNT' && self::$on) {
			system(__DIR__ . '/messagebox.vbs "' . iconv('utf-8', 'gbk', $message) . '"');
		}
	}

	static protected $on = true;

	static public function mon()
	{
		self::$on = true;
	}

	static public function moff()
	{
		self::$on = false;
	}
}

function cache_set($key, $val, $expire=864000)
{
	$f = cache_file($key);
	return file_put_contents(
		$f,
		serialize(array(
			'expire'	=>  time() + $expire,
			'data'	  =>  $val,
		))
	);
}

function cache_get($key)
{
	$f = cache_file($key);
	if (!is_file($f)) {
		return false;
	}
	$d = unserialize(file_get_contents($f));

	if ($d['expire'] < time()) {
		return false;
	} else {
		return $d['data'];
	}
}

function cache_file($key)
{
	if (!is_dir(__DIR__ . '/caches')) {
		mkdir(__DIR__ . '/caches');
	}

	return realpath(__DIR__ . '/caches/c_' . strval($key));
}

# EOF