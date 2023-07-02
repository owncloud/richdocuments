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

class Wopi extends \OCA\Richdocuments\Db {
	// Tokens expire after this many seconds (not defined by WOPI specs).
	public const TOKEN_LIFETIME_SECONDS = 36000;

	public const ATTR_CAN_VIEW = 0;
	public const ATTR_CAN_UPDATE = 1;
	public const ATTR_CAN_EXPORT = 2;
	public const ATTR_CAN_PRINT = 4;
	public const ATTR_HAS_WATERMARK = 8;
	public const ATTR_FEDERATED = 16;

	public const appName = 'richdocuments';

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
	 * @return array
	 * @throws \Exception
	 */
	public function generateToken($fileId, $version, $attributes, $serverHost, $owner, $editor) {
		$token = \OC::$server->getSecureRandom()->generate(
			32,
			\OCP\Security\ISecureRandom::CHAR_LOWER . \OCP\Security\ISecureRandom::CHAR_UPPER .
			\OCP\Security\ISecureRandom::CHAR_DIGITS
		);
		$token_ttl = \time() + self::TOKEN_LIFETIME_SECONDS;

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
			$token_ttl
		]);

		if (!$wopi->insert()) {
			throw new \Exception('Failed to add wopi token into database');
		}

		// we store access_token_ttl as second,
		// but wopi clients expect millisecond
		return [
			'access_token' => $token,
			'access_token_ttl' => $token_ttl * 1000
		];
	}

	/**
	 * @param string $token
	 * @return array|null
	 */
	public function getWopiForToken($token) : ?array {
		$wopi = new Wopi();
		$row = $wopi->loadBy('token', $token)->getData();
		\OC::$server->getLogger()->debug('Loaded WOPI Token record: {row}.', [
			'app' => self::appName,
			'row' => $row ]);
		if (!isset($row['expiry']) || $row['expiry'] <= \time()) {
			return null;
		}

		return [
			'fileId' => $row['fileId'],
			'version' => $row['version'],
			'owner' => $row['owner_uid'],
			'editor' => $row['editor_uid'],
			'attributes' => $row['attributes'],
			'server_host' => $row['server_host']
		];
	}
}
