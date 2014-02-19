<?php

/**
 * hilink.class.php
 *
 * @author Andreas Mueller <webmaster@am-wd.de>
 * @package Huawei-HiLink
 *
 * @description
 * This class tries to fully control an UMTS Stick from Huawei
 * with an HiLink Webinterface (e.g. Huawei E303)
 **/


/* ---                     DEPENDENCIES                           ---
------------------------------------------------------------------ */
function_exists('curl_version') or die('cURL Extension needed'.PHP_EOL);
function_exists('simplexml_load_string') or die('simplexml needed'.PHP_EOL);


class HiLink {
	// Class Attributes
	private $host, $ipcheck;

	public function __construct() {
		$this->setHost('192.168.1.1');
		$this->setIpCheck('http://am-wd.de/ip.php');
	}

	// call default constructor
	public static function create() {
		return new self();
	}

	// call constructor and set url to HiLink
	public static function host($url) {
		$self = new self();
		$self->setHost($url);
		return $self;
	}

	// url to HiLik -> host
	public function setHost($host) {
		if (substr($host,0,5) == 'https') {
			$this->host = str_replace('/', '', substr($host,0,6));
		} else if (substr($host,0,4) == 'http') {
			$this->host = str_replace('/', '', substr($host,0.5));
		} else {
			$this->host = $host;
		}
	}
	public function getHost() {
		return $this->host;
	}

	// check if server (HiLink host) is reachable
	public function online($server = '', $timeout = 1) {
		if (empty($server)) 
				$server = $this->host;

		$sys = $this->getSystem();
		switch ($sys) {
			case "win":
				$cmd = "ping -n 1 -w ".($timeout * 1000)." ".$server;
				break;
			case "mac":
			$cmd = "ping -c 1 -t ".$timeout." ".$server." 2> /dev/null";
			break;
			case "lnx":
				$cmd = "ping -c 1 -W ".$timeout." ".$server." 2> /dev/null";
				break;
			default:
				return false;
		}
		$res = exec($cmd, $out, $ret);

		return ($ret == 0);
	}


	// url to check external ip
	public function setIpCheck($url) {
		$this->ipcheck = $url;
	}
	public function getIpCheck() {
		return $this->ipcheck;
	}

	// returns the external ip address
	public function getExternalIp() {
		$sc = stream_context_create(array('http' => array('timeout' => 1)));
		$ip = @file_get_contents($this->ipcheck, false, $sc);
		return (strlen($ip) > 15) ? '' : $ip;
	}

	/* --- Traffic Statistics
	------------------------- */
	public function getTrafficStatistic() {
		$ch = curl_init($this->host.'/api/monitoring/traffic-statistics');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$res = curl_exec($ch);
		curl_close($ch);

		return simplexml_load_string($res);
	}
	



















	/* --- HELPER FUNCTIONS
	----------------------- */
	private function getSystem() {
		if (substr(__DIR__,0,1) == '/') {
			return (exec('uname') == 'Darwin') ? 'mac' : 'lnx';
		} else {
			return 'win';
		}
	}

	private function getTime($time) {
		$h = floor($time/3600);
		$m = floor($time/60) - $h*60;
		$s = $time - ($h*3600 + $m*60);

		if ($h > 10) $h = '0'.$h;
		if ($m > 10) $m = '0'.$m;
		if ($s > 10) $s = '0'.$s;

		return $h.':'.$m.':'.$s;
	}

	private function getData($bytes) {
		$kb = round($bytes/1024, 2);
		$mb = round($bytes/(1024*1024), 2);
		$gb = round($bytes/(1024*1024*1024), 2);

		if ($bytes > (1024*1024*1024)) return $gb." GB";
		if ($bytes > (1024*1024)) return $mb." MB";
		if ($bytes > 1024) return $kb." KB";
		else return $bytes." B";
	}
}

?>
