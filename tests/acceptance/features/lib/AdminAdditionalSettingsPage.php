<?php declare(strict_types=1);

/**
 * ownCloud
 *
 * @author Saugat Pachhai <saugat@jankaritech.com>
 * @copyright Copyright (c) 2019 Saugat Pachhai saugat@jankaritech.com
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License,
 * as published by the Free Software Foundation;
 * either version 3 of the License, or any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace Page;

use Behat\Mink\Element\NodeElement;
use SensioLabs\Behat\PageObjectExtension\PageObject\Exception\ElementNotFoundException;
use Behat\Mink\Session;

/**
 * Admin General Settings page.
 */
class AdminAdditionalSettingsPage extends OwncloudPage {
	/**
	 *
	 * @var string $path
	 */
	protected $path = '/index.php/settings/admin?sectionid=additional';

	protected $additionalPanelXpath = '//div[@id="OC\\Settings\\Panels\\Admin\\Legacy"]';
	protected $secureViewCheckboxId = 'enable_secure_view_option_cb-richdocuments';

	protected $idSuffix = '_default_option_cb-richdocuments';
	protected $idPrefix = 'secure_view_';

	public const CAN_PRINT = 'can_print';
	public const HAS_WATERMARK = 'has_watermark';

	/**
	 * Toggle status of the secure view checkbox
	 *
	 * @param Session $session
	 *
	 * @return bool the new status of the checkbox after toggling
	 */
	public function toggleSecureView(Session $session): bool {
		$checkbox = $this->getSecureViewCheckbox();
		$checkbox->click();
		$this->waitForAjaxCallsToStartAndFinish($session);

		// there's two ajax calls when the secure view is enabled
		// one for enabling secure view, another sets watermark templates
		$status = $this->isSecureViewEnabled();
		if ($status) {
			$this->waitForAjaxCallsToStartAndFinish($session);
		}

		return $status;
	}

	/**
	 * Check if given option is enabled
	 *
	 * @param string $option
	 *
	 * @return bool Status of the given secure view option
	 */
	public function isSecureViewOptionEnabled(string $option): bool {
		if (!$this->isSecureViewEnabled()) {
			return false;
		}
		$checkbox = $this->getSecureViewOptionsCheckbox($option);
		return $checkbox->isChecked();
	}

	/**
	 * Toggle secure view checkbox option
	 *
	 * @param Session $session
	 * @param string $option
	 *
	 * @return bool Current status of option
	 */
	public function toggleSecureViewOption(
		Session $session,
		string $option
	): bool {
		if (!$this->isSecureViewEnabled()) {
			throw new \Exception('Cannot enable/disable $option as secure view is not enabled.');
		}
		$checkbox = $this->getSecureViewOptionsCheckbox($option);
		$checkbox->click();
		$this->waitForAjaxCallsToStartAndFinish($session);
		return $checkbox->isChecked();
	}

	/**
	 * Returns status of secure view checkbox, i.e enabled or disabled
	 *
	 * @return bool
	 */
	public function isSecureViewEnabled(): bool {
		$checkbox = $this->getSecureViewCheckbox();

		if ($checkbox->hasAttribute('disabled')) {
			throw new \Exception('Secure view is not allowed for this installation, checkbox is disabled (most probably license missing)');
		}

		return $checkbox->isChecked();
	}

	/**
	 * Returns checkbox for secure view
	 *
	 * @throws ElementNotFoundException
	 * @return NodeElement
	 */
	private function getSecureViewCheckbox(): NodeElement {
		$checkbox = $this->findById($this->secureViewCheckboxId);
		$this->assertElementNotNull($checkbox, 'Secure view checkbox not found.');
		return $checkbox;
	}

	/**
	 * @param string $option
	 *
	 * @throws ElementNotFoundException
	 * @return NodeElement
	 */
	private function getSecureViewOptionsCheckbox(string $option): NodeElement {
		$checkbox = $this->findById($this->idPrefix . $option . $this->idSuffix);
		$this->assertElementNotNull($checkbox, "$option checkbox not found.");
		return $checkbox;
	}

	/**
	 * waits for the page to appear completely
	 *
	 * @param Session $session
	 * @param int $timeout_msec
	 *
	 * @return void
	 */
	public function waitTillPageIsLoaded(
		Session $session,
		int $timeout_msec = STANDARD_UI_WAIT_TIMEOUT_MILLISEC
	): void {
		$this->waitForAjaxCallsToStartAndFinish($session);
		$this->waitTillXpathIsVisible(
			$this->additionalPanelXpath,
			$timeout_msec
		);
	}
}
