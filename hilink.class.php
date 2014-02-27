<?php

/**
 * hilink.class.php
 *
 * @author Andreas Mueller <webmaster@am-wd.de>
 * @package Huawei-HiLink
 *
 * @description
 * This class tries to fully control an UMTS Stick from Huawei
 * with an HiLink Webinterface
 *
 * functionality tested with Huawei E303
 * Link: http://www.huaweidevices.de/e303
 **/

@error_reporting(0);

/* ---                     DEPENDENCIES                           ---
------------------------------------------------------------------ */
function_exists('curl_version') or die('cURL Extension needed'.PHP_EOL);
function_exists('simplexml_load_string') or die('simplexml needed'.PHP_EOL);


class HiLink {
	// Class Attributes
	private $host, $ipcheck;

	public $trafficStats, $monitor, $device;

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
			"downloadRate"    => $this->getDownloadRate()
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
		return ($res[0] == "OK");
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
			case 'full': return ''.$res->FullName; break;
			case 'short': return ''.$res->ShortName; break;
			default: return ''.$res->Numeric; break;
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

	// IP provider
	public function getProviderIp() {
		$mon = $this->getMonitor();
		return ''.$mon->WanIPAddress;
	}

	// get DNS server
	public function getDnsServer($server = 1) {
		$mon = $this->getMonitor();
		if ($server == 2) {
			return ''.$mon->SecondaryDns;
		} else {
			return ''.$mon->PrimaryDns;
		}
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
		$req = new SimpleXMLElement('<request></request>');
		switch ($type) {
			case '2g': $req->addChild('NetworkMode', 1); break;
			case '3g': $req->addChild('NetworkMode', 2); break;
			default:   $req->addChild('NetworkMode', 0); break;
		}
		$req->addChild('NetworkBand', $band);

		$ch = curl_init($this->host.'/api/net/network');
		$opts = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $req->asXML(),
		);
		curl_setopt_array($ch, $opts);
		$ret = curl_exec($ch);
		curl_close($ch);

		$res = simplexml_load_string($ret);
		return ($res[0] == "OK");
	}

	// signal strength
	public function getSignalStrength() {
		$mon = $this->getMonitor();
		return $mon->SignalStrength.'%';
	}

	// SIM
	public function getSimStatus() {
		$mon = $this->getMonitor();
		if ($mon->SimStatus == 1) {
			return "SIM ok";
		} else {
			return "SIM fail";
		}
	}

	public function getServiceStatus() {
		$mon = $this->getMonitor();
		if ($mon->ServiceStatus == 2) {
			return "PIN ok";
		} else {
			return "enter PIN";
		}
	}

	public function getSystemStatus() {
		return $this->getSimStatus().' ['.$this->getServiceStatus().']';
	}

	// roaming
	public function getRoamingStatus() {
		$mon = $this->getMonitor();
		switch ($mon->RoamingStatus) {
			case 0: return "inactive";
			case 1: return "active";
			default: return "unknown";
		}
	}

	// collected output
	public function getStatus($asArray = false) {
		if ($asArray) {
			return array(
			"status"    => $this->getSystemStatus(),
			"roaming"   => $this->getRoamingStatus(),
			"conStatus" => $this->getConnectionStatus(),
			"conType"   => $this->getConnectionType(),
			"sigStr"    => $this->getSignalStrength(),
			"ipProv"    => $this->getProviderIp(),
			"ipExt"     => $this->getExternalIp(),
			"ipDNS1"    => $this->getDnsServer(),
			"ipDNS2"    => $this->getDnsServer(2)
			);
		} else {
			$out = "";
			$out .= "System-Status:       ".$this->getSystemStatus().PHP_EOL;
			$out .= "Roaming:             ".$this->getRoamingStatus().PHP_EOL;
			$out .= "Connection-)tatus:   ".$this->getConnectionStatus().PHP_EOL;
			$out .= "Connection-Type:     ".$this->getConnectionType().PHP_EOL;
			$out .= "Connection-Strength: ".$this->getSignalStrength().PHP_EOL;
			$out .= "IPv4 - Provider:     ".$this->getProviderIp().PHP_EOL;
			$out .= "IPv4 - external:     ".$this->getExternalIp().PHP_EOL;
			$out .= "IPv4 - DNS (1):      ".$this->getDnsServer().PHP_EOL;
			$out .= "IPv4 - DNS (2):      ".$this->getDnsServer(2).PHP_EOL;
			return $out;
		}
	}
	public function printStatus() {
		echo $this->getStatus();
	}

	/* --- PIN actions
	------------------ */
	public function getPin() {
		$ch = curl_init($this->host.'/api/pin/status');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$ret = curl_exec($ch);
		curl_close($ch);

		$res = simplexml_load_string($ret);
		return $res;
	}

	private function pinDo($type, $pin, $new = '', $puk = '') {
		$req = new SimpleXMLElement('<request></request>');
		$req->addChild('OperateType', $type);
		$req->addChild('CurrentPin', $pin);
		$req->addChild('NewPin', $new);
		$req->addChild('PukCode', $puk);

		$ch = curl_init($this->host.'/api/pin/operate');
		$opts = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $req->asXML(),
		);
		curl_setopt_array($ch, $opts);
		$ret = curl_exec($ch);
		curl_close($ch);

		$res = simplexml_load_string($ret);
		return ($res[0] == "OK");
	}

	public function pinEnter($pin) {
		return $this->pinDo(0, $pin);
	}

	public function pinActivate($pin) {
		return $this->pinDo(1, $pin);
	}

	public function pinDeactivate($pin) {
		return $this->pinDo(2, $pin);
	}

	public function pinChange($pin, $new) {
		return $this->pinDo(3, $pin, $new);
	}

	public function pinEnterPuk($puk, $newPin) {
		return $this->pinDo(4, $newPin, $newPin, $puk);
	}

	public function getPinTryLeft() {
		$st = $this->getPin();
		return $st->SimPinTimes;
	}

	public function getPukTryLeft() {
		$st = $this->getPin();
		return $st->SimPukTimes;
	}

	public function getPinStatus($asArray = false) {
		$st = $this->getPin();
		if ($asArray) {
			return array(
				"pinTry" => $st->SimPinTimes,
				"pukTry" => $st->SimPukTimes,
			);
		} else {
			$out = '';
			$out .= 'PIN Tries Left: '.$st->SimPinTimes.PHP_EOL;
			$out .= 'PUK Tries Left: '.$st->SimPukTimes.PHP_EOL;
			return $out;
		}
	}

	public function printPinStatus() {
		echo $this->getPinStatus();
	}

	/* --- Connection
	----------------- */
	public function connect() {
		if ($this->isConnected())
				return true;

		$ch = curl_init($this->host.'/api/dialup/dial');
		$opts = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => "<request><Action>1</Action></request>",
		);
		curl_setopt_array($ch, $opts);
		$ret = curl_exec($ch);
		curl_close($ch);

		$res = simplexml_load_string($ret);

		return ($res[0] == 'OK');
	}

	public function disconnect() {
		if (!$this->isConnected())
				return true;

		$ch = curl_init($this->host.'/api/dialup/dial');
		$opts = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => "<request><Action>0</Action></request>",
		);
		curl_setopt_array($ch, $opts);
		$ret = curl_exec($ch);
		curl_close($ch);

		$res = simplexml_load_string($ret);

		return ($res[0] == 'OK');
	}

	public function isConnected() {
		$st = $this->getConnectionStatus();
		return ($st == 'Connected');
	}

	public function getConnection($asArray = false) {
		$ch = curl_init($this->host.'/api/dialup/connection');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$ret = curl_exec($ch);
		curl_close($ch);
		$res = simplexml_load_string($ret);

		if ($asArray) {
			return array(
				"ConnectMode"          => $res->ConnectMode,
				"AutoReconnect"        => $res->AutoReconnect,
				"RoamingAutoConnect"   => $res->RoamAutoConnectEnable,
				"RoamingAutoReconnect" => $res->RoamAutoReconnctEnable,
				"ReconnectInterval"    => $res->ReconnectInterval,
				"MaxIdleTime"          => $res->MaxIdelTime,
			);
		} else {
			$out = '';
			$out .= "Auto Connect:           ".(($res->ConnectMode == 0) ? "1" : "0").PHP_EOL;
			$out .= "Auto Reconnect:         ".$res->AutoReconnect.PHP_EOL;
			$out .= "Roaming Auto Connect:   ".$res->RoamAutoConnectEnable.PHP_EOL;
			$out .= "Roaming Auto Reconnect: ".$res->RoamAutoReconnctEnable.PHP_EOL;
			$out .= "Reconnect Interval:     ".$res->ReconnectInterval.PHP_EOL;
			$out .= "Max. Idle Time:         ".$res->MaxIdelTime.PHP_EOL;
			return $out;
		}
	}
	public function printConnection() {
		echo $this->getConnection();
	}

	private function doConnection($autoconnect, $reconnect, $roamingauto, $roamingre, $interval = 3, $idle = 0) {
		$req = new SimpleXMLElement('<request></request>');
		$req->addChild('RoamAutoConnectEnable', $roamingauto);
		$req->addChild('AutoReconnect', $reconnect);
		$req->addChild('RoamAutoReconnctEnable', $roamingre);
		$req->addChild('ReconnectInterval', $interval);
		$req->addChild('MaxIdelTime', $idle);
		$req->addChild('ConnectMode', $autoconnect);

		$ch = curl_init($this->host.'/api/dialup/connection');
		$opts = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $req->asXML(),
		);
		curl_setopt_array($ch, $opts);
		$ret = curl_exec($ch);
		curl_close($ch);

		$res = simplexml_load_string($ret);

		return ($res[0] == "OK");
	}

	public function activateAutoconnect() {
		$get = $this->getConnection(true);
		return $this->doConnection(0, $get['AutoReconnect'], $get['RoamingAutoConnect'], $get['RoamingAutoReconnect']);
	}
	public function deactivateAutoconnect() {
		$get = $this->getConnection(true);
		return $this->doConnection(1, $get['AutoReconnect'], $get['RoamingAutoConnect'], $get['RoamingAutoReconnect']);
	}

	public function activateAutoReconnect() {
		$get = $this->getConnection(true);
		return $this->doConnection($get['ConnectMode'], 1, $get['RoamingAutoConnect'], $get['RoamingAutoReconnect']);
	}
	public function deactivateAutoReconnect() {
		$get = $this->getConnection(true);
		return $this->doConnection($get['ConnectMode'], 0, $get['RoamingAutoConnect'], $get['RoamingAutoReconnect']);
	}

	public function activateRoamingAutoconnect() {
		$get = $this->getConnection(true);
		return $this->doConnection($get['ConnectMode'], $get['AutoReconnect'], 1, $get['RoamingAutoReconnect']);
	}
	public function deactivateRoamingAutoconnect() {
		$get = $this->getConnection(true);
		return $this->doConnection($get['ConnectMode'], $get['AutoReconnect'], 0, $get['RoamingAutoReconnect']);
	}

	public function activateRoamingAutoReconnect() {
		$get = $this->getConnection(true);
		return $this->doConnection($get['ConnectMode'], $get['AutoReconnect'], $get['RoamingAutoConnect'], 1);
	}

	public function deactivateRoamingAutoReconnect() {
		$get = $this->getConnection(true);
		return $this->doConnection($get['ConnectMode'], $get['AutoReconnect'], $get['RoamingAutoConnect'], 0);
	}

	public function setReconnectInterval($seconds) {
		$get = $this->getConnection(true);
		return $this->doConnection($get['ConnectMode'], $get['AutoReconnect'], $get['RoamingAutoConnect'], $get['RoamingAutoReconnect'], $seconds);
	}

	/* --- Device Infos
	------------------- */
	public function getDevice() {
		$device = $this->device;
		if (isset($device->UpdateTime) && ($device->UpdateTime + 3) > time()) {
			return $device;
		}
		
		$ch = curl_init($this->host.'/api/device/information');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$res = curl_exec($ch);
		curl_close($ch);

		$device = simplexml_load_string($res);
		$device->UpdateTime = time();
		$this->device = $device;
		return $device;
	}

	public function getDeviceName() {
		$dev = $this->getDevice();
		return ''.$dev->DeviceName;
	}

	public function getSerialNumber() {
		$dev = $this->getDevice();
		return ''.$dev->SerialNumber;
	}

	public function getIMEI() {
		$dev = $this->getDevice();
		return ''.$dev->Imei;
	}

	public function getIMSI() {
		$dev = $this->device;
		return ''.$dev->Imsi;
	}

	public function getICCID() {
		$dev = $this->getDevice();
		return ''.$dev->Iccid;
	}

	public function getPhoneNumber() {
		$dev = $this->getDevice();
		return ''.$dev->Msisdn;
	}

	public function getHardwareVersion() {
		$dev = $this->getDevice();
		return ''.$dev->HardwareVersion;
	}

	public function getSoftwareVersion() {
		$dev = $this->getDevice();
		return ''.$dev->SoftwareVersion;
	}

	public function getGuiVersion() {
		$dev = $this->getDevice();
		return ''.$dev->WebUIVersion;
	}

	public function getUptime() {
		$dev = $this->getDevice();
		return $this->getTime($dev->Uptime);
	}

	public function getMAC($interface = 1) {
		$dev = $this->getDevice();
		if ($interface == 2) {
			return ''.$dev->MacAddress2;
		} else {
			return ''.$dev->MacAddress1;
		}
	}

	public function getDeviceInfo($asArray = false) {
		if ($asArray) {
			return array(
				"name"   => $this->getDeviceName(),
				"sn"     => $this->getSerialNumber(),
				"imei"   => $this->getIMEI(),
				"imsi"   => $this->getIMSI(),
				"iccid"  => $this->getICCID(),
				"number" => $this->getPhoneNumber(),
				"hw"     => $this->getHardwareVersion(),
				"sw"     => $this->getSoftwareVersion(),
				"ui"     => $this->getGuiVersion(),
				"uptime" => $this->getUptime(),
				"mac"    => $this->getMAC(),
			);
		} else {
			$out = "";
			$out .= "Name:         ".$this->getDeviceName().PHP_EOL;
			$out .= "SerialNo:     ".$this->getSerialNumber().PHP_EOL;
			$out .= "IMEI:         ".$this->getIMEI().PHP_EOL;
			$out .= "IMSI:         ".$this->getIMSI().PHP_EOL;
			$out .= "ICCID:        ".$this->getICCID().PHP_EOL;
			$out .= "Phone Number: ".$this->getPhoneNumber().PHP_EOL;
			$out .= "HW Version:   ".$this->getHardwareVersion().PHP_EOL;
			$out .= "SW Version:   ".$this->getSoftwareVersion().PHP_EOL;
			$out .= "UI Version:   ".$this->getGuiVersion().PHP_EOL;
			$out .= "Uptime:       ".$this->getUptime().PHP_EOL;
			$out .= "MAC:          ".$this->getMAC().PHP_EOL;
			return $out;
		}
	}

	public function printDeviceInfo() {
		echo $this->getDeviceInfo();
	}

	/* --- APN
	---------- */
	public function getApn() {
		$ch = curl_init($this->host.'/api/dialup/profiles');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$ret = curl_exec($ch);
		curl_close($ch);

		return simplexml_load_string($ret);
	}

	public function createProfile($name, $apn, $user, $password, 
			$isValid = 1, $apnIsStatic = 1, $dailupNum = '*99#', $authMode = 0, 
			$ipIsStatic = 0, $ipAddress = '0.0.0.0', $dnsIsStatic = '', $primaryDns = '', $secondaryDns = '') {
		$req = new SimpleXMLElement('<request></request>');
		$req->addChild('Delete', 0);
		$req->addChild('SetDefault', 0);
		$req->addChild('Modify', 1);
		$p = $req->addChild('Profile');
		$p->addChild('Index');
		$p->addChild('IsValid', $isValid);
		$p->addChild('Name', $name);
		$p->addChild('ApnIsStatic', $apnIsStatic);
		$p->addChild('ApnName', $apn);
		$p->addChild('DailupNum', $dailupNum);
		$p->addChild('Username', $user);
		$p->addChild('Password', $password);
		$p->addChild('AuthMode', $authMode);
		$p->addChild('IpIsStatic', $ipIsStatic);
		$p->addChild('IpAddress', $ipAddress);
		$p->addChild('DnsIsStatic', $dnsIsStatic);
		$p->addChild('PrimaryDns', $primarayDns);
		$p->addChild('SecondaryDns', $secondaryDns);
		$p->addChild('ReadOnly', 0);

		$ch = curl_init($this->host.'/api/dialup/profiles');
		$opts = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $req->asXML(),
		);
		curl_setopt_array($ch, $opts);
		$ret = curl_exec($ch);
		curl_close($ch);

		$res = simplexml_load_string($ret);
		return ($res[0] == "OK");
	}

	public function editProfile($idx, $name, $apn, $user, $password, 
			$readOnly = 0, $isValid = 1, $apnIsStatic = 1, $dailupNum = '*99#', $authMode = 0, 
			$ipIsStatic = 0, $ipAddress = '0.0.0.0', $dnsIsStatic = '', $primaryDns = '', $secondaryDns = '') {
		$req = new SimpleXMLElement('<request></request>');
		$req->addChild('Delete', 0);
		$req->addChild('SetDefault', $idx);
		$req->addChild('Modify', 2);
		$p = $req->addChild('Profile');
		$p->addChild('Index', $idx);
		$p->addChild('IsValid', $isValid);
		$p->addChild('Name', $name);
		$p->addChild('ApnIsStatic', $apnIsStatic);
		$p->addChild('ApnName', $apn);
		$p->addChild('DailupNum', $dailupNum);
		$p->addChild('Username', $user);
		$p->addChild('Password', $password);
		$p->addChild('AuthMode', $authMode);
		$p->addChild('IpIsStatic', $ipIsStatic);
		$p->addChild('IpAddress', $ipAddress);
		$p->addChild('DnsIsStatic', $dnsIsStatic);
		$p->addChild('PrimaryDns', $primarayDns);
		$p->addChild('SecondaryDns', $secondaryDns);
		$p->addChild('ReadOnly', $readOnly);

		$ch = curl_init($this->host.'/api/dialup/profiles');
		$opts = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $req->asXML(),
		);
		curl_setopt_array($ch, $opts);
		$ret = curl_exec($ch);
		curl_close($ch);

		$res = simplexml_load_string($ret);
		return ($res[0] == "OK");
	}

	public function setProfileDefault($idx) {
		$req = new SimpleXMLElement('<request></request>');
		$req->addChild('Delete', 0);
		$req->addChild('SetDefault', $idx);
		$req->addChild('Modify', 0);

		$ch = curl_init($this->host.'/api/dialup/profiles');
		$opts = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $req->asXML(),
		);
		curl_setopt_array($ch, $opts);
		$ret = curl_exec($ch);
		curl_close($ch);

		$res = simplexml_load_string($ret);
		return ($res[0] == "OK");
	}

	public function deleteProfile($idx) {
		$req = new SimpleXMLElement('<request></request>');
		$req->addChild('Delete', $idx);
		$req->addChild('SetDefault', 1);
		$req->addChild('Modify', 0);

		$ch = curl_init($this->host.'/api/dialup/profiles');
		$opts = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $req->asXML(),
		);
		curl_setopt_array($ch, $opts);
		$ret = curl_exec($ch);
		curl_close($ch);

		$res = simplexml_load_string($ret);
		return ($res[0] == "OK");
	}

	public function listApn($asArray = false) {
		$apn = $this->getApn();
		$p = $apn->Profiles->Profile;
		$res = array();

		for ($i = 0; $i < count($p); ++$i) {
			$ar = array();
			$ar['idx']  = ''.$p[$i]->Index;
			$ar['name'] = ''.$p[$i]->Name;
			$ar['apn']  = ''.$p[$i]->ApnName;
			$ar['user'] = ''.$p[$i]->Username;
			$ar['pass'] = ''.$p[$i]->Password;

			$res[] = $ar;
		}

		if ($asArray)
				return $res;

		$out = 'Index: Name [APN] - User:Password'.PHP_EOL;
		$out.= '---------------------------------'.PHP_EOL;
		foreach ($res as $line) {
			$out .= $line['idx'].': '.$line['name'].' ['.$line['apn'].'] - '.$line['user'].':'.$line['pass'].PHP_EOL;
		}
		return $out;
	}

	public function printApn() {
		echo $this->listApn();
	}

	/* --- SMS
	---------- */
	public function getSms($box = 1, $site = 1, $prefUnread = 0, $count = 20) {
		$req = new SimpleXMLElement('<request></request>');
		$req->addChild('PageIndex', $site);
		$req->addChild('ReadCount', $count);
		$req->addChild('BoxType', $box);
		$req->addChild('SortType', 0);
		$req->addChild('Ascending', 0);
		$req->addChild('UnreadPreferred', $prefUnread);

		$ch = curl_init($this->host.'/api/sms/sms-list');
		$opts = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $req->asXML(),
		);
		curl_setopt_array($ch, $opts);
		$ret = curl_exec($ch);
		curl_close($ch);

		return simplexml_load_string($ret);
	}

	private function doSmsBox($box, $asArray) {
		$box = $this->getSms($box);
		$ret = array();

		$msg = $box->Messages->Message;

		for ($i = 0; $i < $box->Count; ++$i) {
			$m = $msg[$i];
			$ar = array();
			$ar['idx'] = "$m->Index";
			$ar['read'] = (($m->Smstat == 1) ? true : false);
			$ar['number'] = "$m->Phone";
			$ar['msg'] = "$m->Content";
			$ar['time'] = strtotime($m->Date);
			$ar['proxy'] = "$m->Sca";

			$ret[] = $ar;
		}

		if ($asArray)
				return $ret;

		$out = '';
		foreach ($ret as $l) {
			$out .= $l['idx'].") ".$l['number']." - ".date('d.m.Y H:i:s').PHP_EOL;
			$out .= "Read: ".$l['read'].PHP_EOL;
			$out .= "Nachricht: ".$l['msg'].PHP_EOL;
		}

		return $out;
	}


	public function getSmsInbox($asArray = true) {
		return $this->doSmsBox(1, $asArray);
	}

	public function getSmsOutbox($asArray = true) {
		return $this->doSmsBox(2, $asArray);
	}

	public function getSmsDraft($asArray = true) {
		return $this->doSmsBox(3, $asArray);
	}

	public function printSmsBox($box = 0) {
		switch ($box) {
			case 1: echo $this->getSmsInbox(false); break;
			case 2: echo $this->getSmsOutbox(false); break;
			case 3: echo $this->getSmsDraft(false); break;
			default:
				echo "Inbox:".PHP_EOL;
				echo $this->getSmsInbox(false).PHP_EOL;
				echo "Outbox:".PHP_EOL;
				echo $this->getSmsOutbox(false).PHP_EOL;
				echo "Draft:".PHP_EOL;
				echo $this->getSmsDraft(false);
				break;
		}
	}

	public function getNotifications() {
		$ch = curl_init($this->host.'/api/monitoring/check-notifications');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$ret = curl_exec($ch);
		curl_close($ch);

		return simplexml_load_string($ret);
	}

	public function getUnreadSms() {
		$not = $this->getNotifications();
		return $not->UnreadMessage;
	}

	public function smsStorageFull() {
		$not = $this->getNotificaitons();
		return ($not->SmsStorageFull == 0) ? false : true;
	}

	public function getSmsCount($box = 'default') {
		$ch = curl_init($this->host.'/api/sms/sms-count');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$ret = curl_exec($ch);
		curl_close($ch);

		$res = simplexml_load_string($ret);

		switch ($box) {
			case 'in': case 'inbox': case 1:
				return "".$res->LocalInbox;
			case 'out': case 'outbox': case 2:
				return "".$res->LocalOutbox;
			case 'draft': case 'drafts': case 3:
				return "".$res->LocalDraft;
			case 'deleted': case 4:
				return "".$res->localDeleted;
			case 'unread': case 'new':
				return "".$res->LocalUnread;
			default:
				return array(
					'inbox'   => "".$res->LocalInbox,
					'outbox'  => "".$res->LocalOutbox,
					'draft'   => "".$res->LocalDraft,
					'deleted' => "".$res->LocalDeleted,
					'unread'  => "".$res->LocalUnread,
				);
		}
	}

	public function listUnreadSms() {
		$list = $this->getSmsInbox();
		$ret = array();
		foreach ($list as $sms) {
			if (!$sms['read']) {
				$ret[] = $sms;
			}
		}

		return $ret;
	}

	public function setSmsRead($idx) {
		$req = new SimpleXMLElement('<request></request>');

		if (is_array($idx)) {
			for ($i = 0; $i < count($idx); $i++)
					$req->addChild('Index', $idx[$i]);
		} else {
			$req->addChild('Index', $idx);
		}

		$ch = curl_init($this->host.'/api/sms/set-read');
		$opts = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $req->asXML(),
		);
		curl_setopt_array($ch, $opts);
		$ret = curl_exec($ch);
		curl_close($ch);

		$res = simplexml_load_string($ret);

		return ($res[0] == "OK");
	}

	public function deleteSms($idx) {
		$req = new SimpleXMLElement('<request></request>');

		if (is_array($idx)) {
			for ($i = 0; $i < count($idx); $i++)
					$req->addChild('Index', $idx[$i]);
		} else {
			$req->addChild('Index', $idx);
		}

		$ch = curl_init($this->host.'/api/sms/delete-sms');
		$opts = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $req->asXML(),
		);
		curl_setopt_array($ch, $opts);
		$ret = curl_exec($ch);
		curl_close($ch);

		$res = simplexml_load_string($ret);

		return ($res[0] == "OK");
	}

	public function sendSms($no, $message, $idx = -1) {
		$req = new SimpleXMLElement('<request></request>');
		$req->addChild('Index', $idx);
		$ph = $req->addChild('Phones');
		
		if (is_array($no)) {
			for ($i = 0; $i < count($no); $i++) {
				$ph->addChild('Phone', $no[$i]);
			}
		} else {
			$ph->addChild('Phone', $no);
		}

		$req->addChild('Sca');
		$req->addChild('Content', $message);
		$req->addChild('Length', strlen($message));
		$req->addChild('Reserved', 1);
		$req->addChild('Date', date('Y-m-d H:i:s'));

//		return true;  // backup return to prohibit high costs

		$ch = curl_init($this->host.'/api/sms/send-sms');
		$opts = array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $req->asXML(),
		);
		curl_setopt_array($ch, $opts);
		$ret = curl_exec($ch);
		curl_close($ch);

		$res = simplexml_load_string($ret);

		return ($res[0] == "OK");
	}

	public function sendSmsStatus() {
		$ch = curl_init($this->host.'/api/sms/send-status');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$ret = curl_exec($ch);
		curl_close($ch);

		$res = simplexml_load_string($ret);

		return $res;
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
