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
	const TOKEN_LIFETIME_SECONDS = 1800;

	const ATTR_CAN_VIEW = 0;
	const ATTR_CAN_DOWNLOAD = 1;
	const ATTR_CAN_PRINT = 2;
	const ATTR_HAS_WATERMARK = 4;
	const ATTR_CAN_UPDATE = 8;

	const appName = 'richdocuments';

	protected $tableName  = '`*PREFIX*richdocuments_wopi`';

	protected $insertStatement  = 'INSERT INTO `*PREFIX*richdocuments_wopi` (`owner_uid`, `editor_uid`, `fileid`, `version`, `attributes`, `server_host`, `token`, `expiry`)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)';

	protected $loadStatement = 'SELECT * FROM `*PREFIX*richdocuments_wopi` WHERE `token`= ?';

	/**
	 * Generate token for document being shared with public link
	 *
	 * @param $fileId
	 * @param $version
	 * @param $attributes
	 * @param $serverHost
	 * @param $owner
	 * @param $editor
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

	/*
	 * Given a token, validates it and
	 * constructs and validates the path.
	 * Returns the path, if valid, else false.
	 */
	public function getWopiForToken($token) {
		$wopi = new Wopi();
		$row = $wopi->loadBy('token', $token)->getData();
		\OC::$server->getLogger()->debug('Loaded WOPI Token record: {row}.', [
			'app' => self::appName,
			'row' => $row ]);
		if (\count($row) == 0) {
			// Invalid token.
			\http_response_code(401);
			return false;
		}

		//TODO: validate.
		if ($row['expiry'] > \time()) {
			// Expired token!
			//http_response_code(404);
			//$wopi->deleteBy('id', $row['id']);
			//return false;
		}

		return [
			'owner' => $row['owner_uid'],
			'editor' => $row['editor_uid'],
			'attributes' => $row['attributes'],
			'server_host' => $row['server_host']
		];
	}
}
