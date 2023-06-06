<?php

/**
 * ownCloud - Richdocuments App
 *
 * @author Victor Dubiniuk
 * @copyright 2013 Victor Dubiniuk victor.dubiniuk@gmail.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Richdocuments;

use \DateTime;
use \DateTimeZone;

class Helper {
	public static $MIMETYPE_LIBREOFFICE_WORDPROCESSOR = [
		'application/pdf',
		'application/vnd.oasis.opendocument.text',
		'application/vnd.oasis.opendocument.presentation',
		'application/vnd.oasis.opendocument.spreadsheet',
		'application/vnd.oasis.opendocument.graphics',
		'application/vnd.oasis.opendocument.text-flat-xml',
		'application/vnd.oasis.opendocument.presentation-flat-xml',
		'application/vnd.oasis.opendocument.spreadsheet-flat-xml',
		'application/vnd.oasis.opendocument.graphics-flat-xml',
		'application/vnd.lotus-wordpro',
		'image/svg+xml',
		'application/vnd.visio',
		'application/vnd.wordperfect',
		'application/msonenote',
		'application/msword',
		'application/rtf',
		'text/rtf',
		'text/plain',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
		'application/vnd.ms-word.document.macroEnabled.12',
		'application/vnd.ms-word.template.macroEnabled.12',
		'application/vnd.ms-excel',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
		'application/vnd.ms-excel.sheet.macroEnabled.12',
		'application/vnd.ms-excel.template.macroEnabled.12',
		'application/vnd.ms-excel.addin.macroEnabled.12',
		'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
		'application/vnd.ms-powerpoint',
		'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'application/vnd.openxmlformats-officedocument.presentationml.template',
		'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
		'application/vnd.ms-powerpoint.addin.macroEnabled.12',
		'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
		'application/vnd.ms-powerpoint.template.macroEnabled.12',
		'application/vnd.ms-powerpoint.slideshow.macroEnabled.12'
	];
	
	/**
	 * Parse document id to retrieve fileid, instanceid, version and sessionid
	 * for the document editing session
	 *
	 * @param string $documentId
	 * @return array
	 * @throws \Exception
	 */
	public static function parseDocumentId($documentId) {
		$arr = \explode('_', $documentId);
		if (\count($arr) === 1) {
			$fileId = $arr[0];
			$instanceId = '';
			$version = '0';
			$sessionId = null;
		} elseif (\count($arr) === 2) {
			list($fileId, $instanceId) = $arr;
			$version = '0';
			$sessionId = null;
		} elseif (\count($arr) === 3) {
			list($fileId, $instanceId, $version) = $arr;
			$sessionId = null;
		} elseif (\count($arr) === 4) {
			list($fileId, $instanceId, $version, $sessionId) = $arr;
		} else {
			throw new \Exception('$fileId has not the expected format');
		}

		return [
			$fileId,
			$instanceId,
			$version,
			$sessionId
		];
	}

	/**
	 * WOPI helper function to convert to ISO 8601 round-trip format.
	 * @param integer $time Must be seconds since unix epoch
	 */
	public static function toISO8601($time) {
		// TODO: Be more precise and don't ignore milli, micro seconds ?
		$datetime = DateTime::createFromFormat('U', (string)$time, new DateTimeZone('UTC'));
		if ($datetime) {
			return $datetime->format('Y-m-d\TH:i:s.u\Z');
		}

		return false;
	}

	/**
	 * @param string $path
	 */
	public static function getNewFileName($view, $path, $prepend = ' ') {
		$fileNum = 1;

		while ($view->file_exists($path)) {
			$fileNum += 1;
			$path = \preg_replace('/(\.|' . $prepend . '\(\d+\)\.)([^.]*)$/', $prepend . '(' . $fileNum . ').$2', $path);
		};

		return $path;
	}

	public static function getArrayValueByKey($array, $key, $default='') {
		if (\array_key_exists($key, $array)) {
			return $array[$key];
		}
		return $default;
	}

	public static function isVersionsEnabled() {
		return \OCP\App::isEnabled('files_versions');
	}

	public static function getRandomColor() {
		$str = \dechex((int)\floor(\rand(0, 16777215)));
		return '#' . \str_pad($str, 6, "0", STR_PAD_LEFT);
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public static function getMemberColor($name) {
		$hash = \md5($name);
		$maxRange = \hexdec('ffffffffffffffffffffffffffffffff');
		$hue = \hexdec($hash) / $maxRange * 256;
		return '#' . self::convertHSLToRGB($hue, 90, 60);
	}

	/**
	 * @param integer $iH
	 * @param integer $iS
	 * @param integer $iV
	 * @return string
	 */
	protected static function convertHSLToRGB($iH, $iS, $iV) {
		if ($iH < 0) {
			$iH = 0;   // Hue:
		}
		if ($iH > 360) {
			$iH = 360; //   0-360
		}
		if ($iS < 0) {
			$iS = 0;   // Saturation:
		}
		if ($iS > 100) {
			$iS = 100; //   0-100
		}
		if ($iV < 0) {
			$iV = 0;   // Lightness:
		}
		if ($iV > 100) {
			$iV = 100; //   0-100
		}

		$dS = $iS / 100.0; // Saturation: 0.0-1.0
		$dV = $iV / 100.0; // Lightness:  0.0-1.0
		$dC = $dV * $dS;   // Chroma:     0.0-1.0
		$dH = $iH / 60.0;  // H-Prime:    0.0-6.0
		$dT = $dH;	   // Temp variable

		while ($dT >= 2.0) {
			$dT -= 2.0;
		} // php modulus does not work with float
		$dX = $dC * (1 - \abs($dT - 1));	 // as used in the Wikipedia link

		switch ($dH) {
			case($dH >= 0.0 && $dH < 1.0):
				$dR = $dC;
				$dG = $dX;
				$dB = 0.0;
				break;
			case($dH >= 1.0 && $dH < 2.0):
				$dR = $dX;
				$dG = $dC;
				$dB = 0.0;
				break;
			case($dH >= 2.0 && $dH < 3.0):
				$dR = 0.0;
				$dG = $dC;
				$dB = $dX;
				break;
			case($dH >= 3.0 && $dH < 4.0):
				$dR = 0.0;
				$dG = $dX;
				$dB = $dC;
				break;
			case($dH >= 4.0 && $dH < 5.0):
				$dR = $dX;
				$dG = 0.0;
				$dB = $dC;
				break;
			case($dH >= 5.0 && $dH < 6.0):
				$dR = $dC;
				$dG = 0.0;
				$dB = $dX;
				break;
			default:
				$dR = 0.0;
				$dG = 0.0;
				$dB = 0.0;
				break;
		}

		$dM = $dV - $dC;
		$dR += $dM;
		$dG += $dM;
		$dB += $dM;
		$dR *= 255;
		$dG *= 255;
		$dB *= 255;

		$dR = \str_pad(\dechex((int)\round($dR)), 2, "0", STR_PAD_LEFT);
		$dG = \str_pad(\dechex((int)\round($dG)), 2, "0", STR_PAD_LEFT);
		$dB = \str_pad(\dechex((int)\round($dB)), 2, "0", STR_PAD_LEFT);
		return $dR.$dG.$dB;
	}
}
