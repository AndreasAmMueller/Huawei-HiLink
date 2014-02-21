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

@error_reporting(E_ALL ^ E_NOTICE);

/* ---                     DEPENDENCIES                           ---
------------------------------------------------------------------ */
function_exists('curl_version') or die('cURL Extension needed'.PHP_EOL);
function_exists('simplexml_load_string') or die('simplexml needed'.PHP_EOL);


class HiLink {
	// Class Attributes
	private $host, $ipcheck;

	public $trafficStats, $monitor;

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
		$stats = $this->trafficStats;

		if (isset($stats->UpdateTime) && ($stats->UpdateTime + 3) > time()) {
			return $stats;
		}
		
		$ch = curl_init($this->host.'/api/monitoring/traffic-statistics');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$res = curl_exec($ch);
		curl_close($ch);

		$stats = simplexml_load_string($res);
		$stats->UpdateTime = time();
		$this->trafficStats = $stats;
		return $stats;
	}

	// Online Time
	public function getOnlineTime() {
		$stats = $this->getTrafficStatistic();
		return $this->getTime($stats->CurrentConnectTime);
	}

	public function getTotalOnlineTime() {
		$stats = $this->getTrafficStatistic();
		return $this->getTime($stats->TotalConnectTime);
	}

	// Upload
	public function getTotalUpload() {
		$stats = $this->getTrafficStatistic();
		return $this->getData($stats->TotalUpload);
	}

	public function getCurrentUpload() {
		$stats = $this->getTrafficStatistic();
		return $this->getData($stats->CurrentUpload);
	}

	public function getUploadRate() {
		$stats = $this->getTrafficStatistic();
		return $this->getData($stats->CurrentUploadRate).'/s';
	}

	// Download
	public function getTotalDownload() {
		$stats = $this->getTrafficStatistic();
		return $this->getData($stats->TotalDownload);
	}

	public function getCurrentDownload() {
		$stats = $this->getTrafficStatistic();
		return $this->getData($stats->CurrentDownload);
	}

	public function getDownloadRate() {
		$stats = $this->getTrafficStatistic();
		return $this->getData($stats->CurrentDownloadRate).'/s';
	}

	// collected output
	public function getTraffic($asArray = false) {
		if ($asArray) {
			return array(
			"timeCurrent"     => $this->getOnlineTime(),
			"timeTotal"       => $this->getTotalOnlineTime(),
			"uploadTotal"     => $this->getTotalUpload(),
			"uploadCurrent"   => $this->getCurrentUpload(),
			"uploadRate"      => $this->getUploadRate(),
			"downloadTotal"   => $this->getTotalDownload(),
			"downloadCurrent" => $this->getCurrentDownload(),
			"downluadRate"    => $this->getDownloadRate()
			);
		} else {
			$ret = '';
			// current
			$ret .= "Current Session:".PHP_EOL;
			$ret .= "- Time:     ".$this->getOnlineTime().PHP_EOL;
			$ret .= "- Upload:   ".$this->getCurrentUpload();
			$ret .= " [".$this->getUploadRate()."]";
			$ret .= PHP_EOL;
			$ret .= "- Download: ".$this->getCurrentDownload();
			$ret .= " [".$this->getDownloadRate()."]";
			$ret .= PHP_EOL;
			// total
			$ret .= "Total Data:".PHP_EOL;
			$ret .= "- Time:     ".$this->getTotalOnlineTime().PHP_EOL;
			$ret .= "- Upload:   ".$this->getTotalUpload().PHP_EOL;
			$ret .= "- Download: ".$this->getTotalDownload().PHP_EOL;
			return $ret;
		}
	}
	public function printTraffic() {
		echo $this->getTraffic();
	}
	
	public function resetTrafficStats() {
		$ch = curl_init($this->host.'/api/monitoring/clear-traffic');
		$opts = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => "<request><ClearTraffic>1</ClearTraffic></request>"
		);
		curl_setopt_array($ch, $opts);
		$ret = curl_exec($ch);
		curl_close($ch);
		
		$res = simplexml_load_string($ret);
		return ($res->response == "OK");
	}

	/* --- Provider
	--------------- */
	public function getProvider($length = 'full') {
		$ch = curl_init($this->host.'/api/net/current-plmn');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$ret = curl_exec($ch);
		curl_close($ch);
		$res = simplexml_load_string($ret);

		switch ($length) {
			case 'full': return $res->FullName; break;
			case 'short': return $res->ShortName; break;
			default: return $res->Numeric; break;
		}
	}

	/* --- Monitoring Stats
	----------------------- */
	public function getMonitor() {
		$monitor = $this->monitor;
		if (isset($monitor->UpdateTime) && ($monitor->UpdateTime + 3) > time()) {
			return $monitor;
		}
		
		$ch = curl_init($this->host.'/api/monitoring/status');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$res = curl_exec($ch);
		curl_close($ch);

		$monitor = simplexml_load_string($res);
		$monitor->UpdateTime = time();
		$this->monitor = $monitor;
		return $monitor;
	}

	// connection status
	public function getConnectionStatus() {
		$mon = $this->getMonitor();
		switch ($mon->ConnectionStatus) {
			case "112": return "No autoconnect";
			case "113": return "No autoconnect (roaming)";
			case "114": return "No reconnect on timeout";
			case "115": return "No reconnect on timeout (roaming)";
			case "900": return "Connecting";
			case "901": return "Connected";
			case "902": return "Disconnected";
			case "903": return "Disconnecting";
			default: return "Unknown status";
		}
	}

	// connection type
	public function getConnectionType() {
		$mon = $this->getMonitor();
		switch ($mon->CurrentNetworkType) {
			case "3": return "2G";
			case "4": return "3G";
			case "7": return "3G+";
			default: return "Unknown type";
		}
	}

	public function setConnectionType($type = 'auto', $band = '-1599903692') {
		$type = strtolower($type);
		switch ($type) {
			case '2g': $req = "<request><NetworkMode>1</NetworkMode><NetworkBand>$band</NetworkBand></request>"; break;
			case '3g': $req = "<request><NetworkMode>2</NetworkMode><NetworkBand>$band</NetworkBand></request>"; break;
			default:   $req = "<request><NetworkMode>0</NetworkMode><NetworkBand>$band</NetworkBand></request>"; break;
		}

		$ch = curl_init($this->host.'/api/net/network');
		$opts = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $req
		);
		curl_setopt_array($ch, $opts);
		$ret = curl_exec($ch);
		curl_close($ch);

		$res = simplexml_load_string($ret);
		return ($res->response == "OK");
	}
















	// collected output
	public function getStatus($asArray = false) {
		if ($asArray) {
			return array(
//			"status"    => $this->getSysStatus(),
//			"roaming"   => $this->getRoaming(),
			"conStatus" => $this->getConnectionStatus(),
			"conType"   => $this->getConnectionType(),
//			"conStr"    => $this->getConStrength(),
			"ipProv"    => $this->getProvider(),
			"ipExt"     => $this->getExternalIp(),
//			"ipDNS1"    => $this->getIPv4DNS(1),
//			"ipDNS2"    => $this->getIPv4DNS(2)
			);
		} else {
			$out = "";
//			$out .= "System-Status:       ".$this->getSysStatus().PHP_EOL;
//			$out .= "Roaming:             ".$this->getRoaming().PHP_EOL;
			$out .= "Connection-Status:   ".$this->getConnectionStatus().PHP_EOL;
			$out .= "Connection-Type:     ".$this->getConnectionType().PHP_EOL;
//			$out .= "Connection-Strength: ".$this->getConStrength().PHP_EOL;
			$out .= "PIv4 - Provider:     ".$this->getProvider().PHP_EOL;
			$out .= "IPv4 - external:     ".$this->getExternalIp().PHP_EOL;
//			$out .= "IPv4 - DNS (1):      ".$this->getIPv4DNS(1).PHP_EOL;
//			$out .= "IPv4 - DNS (2):      ".$this->getIPv4DNS(2).PHP_EOL;
			return $out;
		}
	}
	public function printStatus() {
		echo $this->getStatus();
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

		if ($h < 10) $h = '0'.$h;
		if ($m < 10) $m = '0'.$m;
		if ($s < 10) $s = '0'.$s;

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
