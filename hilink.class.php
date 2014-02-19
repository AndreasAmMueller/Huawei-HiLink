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
function_exists('curl_version') or die('cURL Extension needed');
function_exists('xml_parse') or die('xml parser needed');


class HiLink {
	# Class Attributes
	private $host;

	# Constructor
	public function __construct() {
		$this->setHost('http://hi.link');
	}

	public static function create() {
		return new self();
	}

	public static function host($url) {
		$self = new self();
		$self->setHost($url);
		return $self;
	}

	public function setHost($url) {
		if (substr($url,0,7) == 'http://' || substr($url,0,8) == 'https://') {
			$this->host = $url;
		} else {
			$this->host = 'http://'.$url;
		}
	}

	public function getHost() {
		return $this->host;
	}

}

?>
