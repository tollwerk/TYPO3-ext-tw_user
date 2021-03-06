<?php
/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2019 Klaus Fiedler <klaus@tollwerk.de>, tollwerk® GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

namespace Tollwerk\TwUser\Domain\Factory\FrontendUser;

use Tollwerk\TwBase\Domain\Validator\UniqueObjectValidator;
use Tollwerk\TwBase\Utility\LocalizationUtility;
use Tollwerk\TwUser\Controller\FrontendUserController;
use Tollwerk\TwUser\Domain\Factory\AbstractFormFactory;
use Tollwerk\TwUser\Domain\Finisher\FrontendUser\RegistrationFinisher;
use Tollwerk\TwUser\Hook\FrontendUserHookInterface;
use Tollwerk\TwUser\Hook\RegistrationFormHook;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Exception;
use TYPO3\CMS\Extbase\Validation\Validator\NotEmptyValidator;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;

/**
 * RegistrationFormFactory
 *
 * Frontend user registration
 *
 * @package    Tollwerk\TwUser
 * @subpackage Tollwerk\TwUser\Domain\Factory\FrontendUser
 */
class RegistrationFormFactory extends AbstractFormFactory
{
    protected $identifier = 'frontendUserRegistration';

    /**
     * Build a form definition, depending on some configuration.
     *
     * The configuration array is factory-specific; for example a YAML or JSON factory
     * could retrieve the path to the YAML / JSON file via the configuration array.
     *
     * @param array $configuration  factory-specific configuration array
     * @param string $prototypeName The name of the "PrototypeName" to use; it is factory-specific to implement this.
     *
     * @return FormDefinition a newly built form definition
     * @throws InvalidConfigurationTypeException
     * @throws PrototypeNotFoundException
     * @throws TypeDefinitionNotFoundException
     * @throws TypeDefinitionNotValidException
     * @throws FinisherPresetNotFoundException
     * @api
     */
    public function build(array $configuration, string $prototypeName = null): FormDefinition
    {
        // Create form
        /** @var FormDefinition $form */
        $form = parent::build($configuration, $prototypeName);
        $form->setRenderingOption('controllerAction', 'registration');
        $form->setRenderingOption('submitButtonLabel', $this->translate('feuser.registration.form.submit'));
        $form->setRenderingOption('elementClassAttribute', 'UserRegistration__form Form');

        // Create page and form fields
        $page = $form->createPage('registration');
        $email = $page->createElement('email', 'Email');
        $email->setLabeL($this->translate('feuser.registration.form.email'));
        $email->setProperty('fluidAdditionalAttributes', ['placeholder' => $this->translate('feuser.registration.form.email.placeholder')]);
        $email->addValidator($this->objectManager->get(NotEmptyValidator::class));
        $email->addValidator($this->objectManager->get(UniqueObjectValidator::class, [
            'table' => 'fe_users',
            'fieldname' => 'username',
            'errorMessage' => LocalizationUtility::translate('feuser.registration.form.email.error.unique','TwUser')
        ]));

        // Add registration finisher for creating frontend user, triggering double-opt-in email etc.
        $form->addFinisher($this->objectManager->get(RegistrationFinisher::class));

        // Hook for manipulating the form definition
        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/tw_user']['registrationForm'] ?? [] as $className) {
            $_procObj = GeneralUtility::makeInstance($className);
            if (!($_procObj instanceof RegistrationFormHook)) {
                throw new Exception(
                    sprintf('The registered class %s for hook [ext/tw_user][registrationForm] does not implement the RegistrationFormHook interface', $className),
                    1556279202
                );
            }
            $_procObj->registrationFormHook($form);
        }

        // Add other finishers after calling the hook
        // so injected finishers always win against defined here.
        $form->createFinisher('Redirect', [
            'pageUid' => (!empty($this->settings['feuser']['registration']['confirmPid'])) ? $this->settings['feuser']['registration']['confirmPid'] : $GLOBALS['TSFE']->id,
            'additionalParameters' => 'tx_twuser_feuserregistration[status]='.FrontendUserController::REGISTRATION_SUBMITTED
        ]);

        // Return everything
        $this->triggerFormBuildingFinished($form);
        return $form;
    }
}
