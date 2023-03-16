<?php
/**
 * ownCloud - Richdocuments App
 *
 * @copyright Copyright (c) 2021, ownCloud GmbH
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Richdocuments\Controller;

use GuzzleHttp\Mimetypes;
use OC\AppFramework\Http;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\ILogger;
use OCP\IRequest;

class WebAssetController extends Controller {

	/**
	 * @var ILogger
	 */
	private $logger;

	/**
	 * WebAssetController constructor.
	 *
	 * @param string $appName - application name
	 * @param IRequest $request - request object
	 * @param ILogger $logger
	 */
	public function __construct($appName, IRequest $request, ILogger $logger) {
		parent::__construct($appName, $request);
		$this->logger = $logger;
	}

	/**
	 * Loads the richdocuments.js file for integration into ownCloud Web
	 *
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @return Response
	 */
	public function get(): Response {
		$basePath = \dirname(__DIR__, 2);
		$filePath = \realpath($basePath . '/js/web/richdocuments.js');

		try {
			return new DataDisplayResponse(\file_get_contents($filePath), Http::STATUS_OK, [
				'Content-Type' => $this->getMimeType($filePath),
				'Content-Length' => \filesize($filePath),
				'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
				'Pragma' => 'no-cache',
				'Expires' => 'Tue, 24 Sep 1985 22:15:00 GMT',
				'X-Frame-Options' => 'DENY'
			]);
		} catch (\Exception $e) {
			$this->logger->logException($e, ['app' => $this->appName]);
			return new DataResponse(["message" => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	private function getMimeType(string $filename): string {
		$mimeTypes = Mimetypes::getInstance();
		return $mimeTypes->fromFilename($filename);
	}
}
