<?php

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

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\RawMinkContext;
use Page\AdminAdditionalSettingsPage;
use TestHelpers\SetupHelper;

require_once 'bootstrap.php';

/**
 * SecureViewContext context.
 */
class SecureViewContext extends RawMinkContext implements Context {
	/*
	* @var AdminAdditionalSettingsPage $adminAdditionalSettingsPage
	*/
	private $adminAdditionalSettingsPage;

	/**
	 * @var array
	 */
	private $appConfig = [];

	/**
	 *
	 * @var WebUIGeneralContext
	 */
	private $webUIGeneralContext;

	/**
	 *
	 * @var FeatureContext
	 */
	private $featureContext;

	/**
	 * SecureViewContext constructor.
	 *
	 * @param AdminAdditionalSettingsPage $adminAdditionalSettingsPage
	 */
	public function __construct(
		AdminAdditionalSettingsPage $adminAdditionalSettingsPage
	) {
		$this->adminAdditionalSettingsPage = $adminAdditionalSettingsPage;
	}

	/**
	 * @Given the administrator has browsed to the admin additional settings page
	 * @When the administrator browses to the admin additional settings page
	 *
	 * @return void
	 */
	public function theAdministratorHasBrowsedToTheAdminAdditionalSettingsPage() {
		$this->webUIGeneralContext->adminLogsInUsingTheWebUI();
		$this->adminAdditionalSettingsPage->open();
		$this->adminAdditionalSettingsPage->waitTillPageIsLoaded($this->getSession());
	}

	/**
	 * @When the administrator enables secure view using the webUI
	 *
	 * @return void
	 */
	public function theAdministratorEnablesSecureViewUsingTheWebUI() {
		if (!$this->adminAdditionalSettingsPage->isSecureViewEnabled()) {
			$this->adminAdditionalSettingsPage->toggleSecureView($this->getSession());
		}
	}

	/**
	 * @When the administrator enables print permission in secure view using the webUI
	 *
	 * @return void
	 */
	public function administratorEnablesCanPrintSecureView() {
		if (!$this->adminAdditionalSettingsPage->isSecureViewOptionEnabled(
			AdminAdditionalSettingsPage::CAN_PRINT
		)
		) {
			$this->adminAdditionalSettingsPage->toggleSecureViewOption(
				$this->getSession(),
				AdminAdditionalSettingsPage::CAN_PRINT
			);
		}
	}

	/**
	 * @When the administrator enables watermark permission in secure view using the webUI
	 *
	 * @return void
	 */
	public function administratorEnablesCanWatermarkSecureView() {
		if (!$this->adminAdditionalSettingsPage->isSecureViewOptionEnabled(
			AdminAdditionalSettingsPage::HAS_WATERMARK
		)
		) {
			$this->adminAdditionalSettingsPage->toggleSecureViewOption(
				$this->getSession(),
				AdminAdditionalSettingsPage::HAS_WATERMARK
			);
		}
	}

	/**
	 * @When the administrator disables print permission in secure view using the webUI
	 *
	 * @return void
	 */
	public function administratorDisablesCanPrintSecureView() {
		if ($this->adminAdditionalSettingsPage->isSecureViewOptionEnabled(
			AdminAdditionalSettingsPage::CAN_PRINT
		)
		) {
			$this->adminAdditionalSettingsPage->toggleSecureViewOption(
				$this->getSession(),
				AdminAdditionalSettingsPage::CAN_PRINT
			);
		}
	}

	/**
	 * @When the administrator disables watermark permission in secure view using the webUI
	 *
	 * @return void
	 */
	public function administratorDisablesCanWatermarkSecureView() {
		if ($this->adminAdditionalSettingsPage->isSecureViewOptionEnabled(
			AdminAdditionalSettingsPage::HAS_WATERMARK
		)
		) {
			$this->adminAdditionalSettingsPage->toggleSecureViewOption(
				$this->getSession(),
				AdminAdditionalSettingsPage::HAS_WATERMARK
			);
		}
	}

	/**
	 * @Then the additional sharing attributes for the response should be empty
	 *
	 * @return void
	 */
	public function theAdditionalSharingAttributesForTheResponseShouldHaveNoData() {
		try {
			$value = $this->featureContext->getSharingAttributesFromLastResponse();
		} catch (Exception $e) {
			return;
		}

		throw new Exception(
			'was expected to not have attributes inside the response. ' .
			"Found $value"
		);
	}

	/**
	 * @param string $server
	 *
	 * @return void
	 */
	public function saveOldConfigsForRichdocuments($server) {
		$this->featureContext->runOcc(['config:list', 'richdocuments', '--output json']);
		$appConfig = \json_decode($this->featureContext->getStdOutOfOccCommand(), true);
		if ($appConfig['apps'] && $appConfig['apps']['richdocuments']) {
			$this->appConfig[$server] = $appConfig['apps']['richdocuments'];
			return;
		}
		$this->appConfig[$server] = [];
	}

	/**
	 * @param string $server
	 *
	 * @return void
	 * @throws Exception
	 */
	public function revertToOldConfigsRichdocuments($server) {
		$appConfig = $this->appConfig[$server];
		foreach ($appConfig as $key => $value) {
			$this->featureContext->runOcc(
				[
					'config:app:set',
					'richdocuments',
					(string) $key,
					"--value='$value'"
				]
			);
			PHPUnit\Framework\Assert::assertEquals(
				0,
				$this->featureContext->getExitStatusCodeOfOccCommand(),
				"Setting $value for $key in richdocuments failed." .
				"\n Actual Output: {$this->featureContext->getStdOutOfOccCommand()}"
			);
		}
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function disableSecureView() {
		$cmd = ['config:app:set', 'richdocuments', 'secure_view_option', '--value=false'];
		$this->featureContext->runOcc($cmd);

		PHPUnit\Framework\Assert::assertEquals(
			0,
			$this->featureContext->getExitStatusCodeOfOccCommand(),
			'Could not disable secure_view_option.'
		);
	}

	/**
	 * This will run before EVERY scenario.
	 * It will set the properties for this object.
	 *
	 * @BeforeScenario @webUI
	 *
	 * @param BeforeScenarioScope $scope
	 *
	 * @return void
	 */
	public function before(BeforeScenarioScope $scope) {
		// Get the environment
		$environment = $scope->getEnvironment();
		// Get all the contexts you need in this context
		$this->webUIGeneralContext = $environment->getContext('WebUIGeneralContext');
		$this->featureContext = $environment->getContext('FeatureContext');

		// FeatureContext's beforeScenario does not run at this point to init SetupHelper
		$this->featureContext->runFunctionOnEveryServer(
			function ($server) {
				SetupHelper::init(
					$this->featureContext->getAdminUsername(),
					$this->featureContext->getAdminPassword(),
					$this->featureContext->getBaseUrl(),
					$this->featureContext->getOcPath()
				);
				$this->saveOldConfigsForRichdocuments($server);
				$this->disableSecureView();
			}
		);
	}

	/**
	 * This will run after EVERY scenario.
	 *
	 * @AfterScenario @webUI
	 *
	 * @param AfterScenarioScope $scope
	 *
	 * @return void
	 */
	public function after(AfterScenarioScope $scope) {
		$this->featureContext->runFunctionOnEveryServer(
			function ($server) {
				$this->revertToOldConfigsRichdocuments($server);
			}
		);
	}
}
