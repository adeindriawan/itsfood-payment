<?php

namespace App\Http\Utils;

class BniCallback {

  public static function getContent($payload = '')
  {
    $url = env('BNI_PAYMENT_URL');

    $useCookie = __DIR__ . '/cookie.txt';
		$header[] = 'Content-Type: application/json';
		$header[] = 'Accept-Encoding: gzip, deflate';
		$header[] = 'Cache-Control: max-age=0';
		$header[] = 'Connection: keep-alive';
		$header[] = 'Accept-Language: en-US,en;q=0.8,id;q=0.6';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($ch, CURLOPT_ENCODING, TRUE);
		curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1) AppleWebkit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.120 Safari/537.36');

		if ($payload) {
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		}

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$rs = curl_exec($ch);
		if (empty($rs)) {
			var_dump($rs, curl_error($ch));
			curl_close($ch);

			return FALSE;
		}

		curl_close($ch);
		return $rs;
  }

}