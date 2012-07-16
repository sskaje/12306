<?php


$config = array(
	'login_user'	=>	'test12306001',
	'login_pass'	=>	'testtest',
	'login_realname'=>	'测试',
	'single_round_type'	=>	1,	# 1 单程, 2 返程
	'train_date'	=>	'2012-01-18',
	'train_from'	=>	'XXF',
	'train_to'		=>	'BXP',
	'train_time'	=>	'00:00--24:00',
	'train_no'		=>	'',
	'train_passtype'=>	'QB',
#	'train_class'	=>	'QB#D#Z#T#K#QT#',
	'train_class'	=>	'D#',
		
	'return_train_date'		=>	'2012-01-07',
	'return_train_time'		=>	'00:00--24:00',
	'return_train_passtype'	=>	'QB',
	'return_train_class'	=>	'Z#',

	'seat_order'	=>	array(
		'M','O',
	),

	'tickets_info'	=>	array(
#		'D136#05:01#11:31#380000D13602#XXF#BXP#16:32#新乡#北京西#M021500008O018000096O018003000',
	),

	'seat_detail'	=>	array(),
	'ticket_key'	=>	'',
	'sequence_no'	=>	'',
);

$config['seat_detail'] = array(
	array(
		'seat'		=>  'M',
		'ticket'	=>  1,
		'name'		=>  '测试',
		'cardtype'	=>  2,
		'cardno'	=>  '110110811003023',
		'mobileno'	=>  '13211221122',
	),
);

define('SPBOT_12306_DECAPTCHA_LOGIN',   SPBOT_12306_DECAPTCHA_MODE_INPUT);
#define('SPBOT_12306_DECAPTCHA_CONFIRM', SPBOT_12306_DECAPTCHA_MODE_INPUT);
define('SPBOT_12306_DECAPTCHA_CONFIRM', SPBOT_12306_DECAPTCHA_MODE_INPUT);

return $config;
# EOF
