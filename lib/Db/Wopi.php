<?php

/**
 * ownCloud - Richdocuments App
 *
 * @author Ashod Nakashian
 * @copyright 2016 Ashod Nakashian ashod.nakashian@collabora.co.uk
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Richdocuments\Db;

/**
 * @method string generateToken()
 * @method string getWopiForToken()
 */

class Wopi extends \OCA\Richdocuments\Db {
	// Tokens expire after this many seconds (not defined by WOPI specs).
	const TOKEN_LIFETIME_SECONDS = 36000;
	// If the expiry is closer than this time, it will be refreshed.
	const TOKEN_REFRESH_THRESHOLD_SECONDS = 3600;

	const ATTR_CAN_VIEW = 0;
	const ATTR_CAN_UPDATE = 1;
	const ATTR_CAN_EXPORT = 2;
	const ATTR_CAN_PRINT = 4;
	const ATTR_HAS_WATERMARK = 8;

	const appName = 'richdocuments';

	protected $tableName  = '`*PREFIX*richdocuments_wopi`';

	protected $insertStatement  = 'INSERT INTO `*PREFIX*richdocuments_wopi` (`owner_uid`, `editor_uid`, `fileid`, `version`, `attributes`, `server_host`, `token`, `expiry`)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)';

	protected $loadStatement = 'SELECT * FROM `*PREFIX*richdocuments_wopi` WHERE `token`= ?';

	/**
	 * Generate token for document being shared with public link
	 *
	 * @param int $fileId
	 * @param int $version
	 * @param int $attributes
	 * @param string $serverHost
	 * @param string $owner
	 * @param string $editor
	 * @return string
	 * @throws \Exception
	 */
	public function generateToken($fileId, $version, $attributes, $serverHost, $owner, $editor) {
		$token = \OC::$server->getSecureRandom()->getMediumStrengthGenerator()->generate(32,
			\OCP\Security\ISecureRandom::CHAR_LOWER . \OCP\Security\ISecureRandom::CHAR_UPPER .
			\OCP\Security\ISecureRandom::CHAR_DIGITS);

		\OC::$server->getLogger()->debug('generateFileToken(): Issuing token, editor: {editor}, file: {fileId}, version: {version}, owner: {owner}, token: {token}', [
			'app' => self::appName,
			'owner' => $owner,
			'editor' => $editor,
			'fileId' => $fileId,
			'version' => $version,
			'token' => $token ]);

		$wopi = new \OCA\Richdocuments\Db\Wopi([
			$owner,
			$editor,
			$fileId,
			$version,
			$attributes,
			$serverHost,
			$token,
			\time() + self::TOKEN_LIFETIME_SECONDS
		]);

		if (!$wopi->insert()) {
			throw new \Exception('Failed to add wopi token into database');
		}

		return $token;
	}

	/**
	 * @param string $token
	 * @return array | false for invalid token
	 */
	public function getWopiForToken($token) {
		$wopi = new Wopi();
		$row = $wopi->loadBy('token', $token)->getData();
		\OC::$server->getLogger()->debug('Loaded WOPI Token record: {row}.', [
			'app' => self::appName,
			'row' => $row ]);
		if (\count($row) == 0 || $row['expiry'] <= \time()) {
			return false;
		}

		if ($row['expiry'] - self::TOKEN_REFRESH_THRESHOLD_SECONDS <= \time()) {
			$this->refreshTokenExpiry($token);
		}

		return [
			'owner' => $row['owner_uid'],
			'editor' => $row['editor_uid'],
			'attributes' => $row['attributes'],
			'server_host' => $row['server_host']
		];
	}

	/**
	 * Refresh token life time
	 *
	 * @param string $token
	 * @return boolean
	 */
	protected function refreshTokenExpiry($token) {
		$count = \OC::$server->getDatabaseConnection()->executeUpdate('UPDATE `*PREFIX*richdocuments_wopi` SET `expiry` = ? WHERE `token` = ?',
			[\time() + self::TOKEN_LIFETIME_SECONDS, $token]
		);
		return $count > 0;
	}
}
