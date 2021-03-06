<?php

namespace Shopware\Plugins\MoptPaymentPayone\Subscribers;

use Enlight\Event\SubscriberInterface;

class FrontendPostDispatch implements SubscriberInterface
{

    /**
     * di container
     * 
     * @var \Shopware\Components\DependencyInjection\Container
     */
    private $container;

    /**
     * path to plugin files
     * 
     * @var string
     */
    private $path;

    /**
     * inject di container
     * 
     * @param \Shopware\Components\DependencyInjection\Container $container
     */
    public function __construct(Container $container, $path)
    {
        $this->container = $container;
        $this->path = $path;
    }

    /**
     * return array with all subsribed events
     * 
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Controller_Action_PostDispatch_Frontend' => 'onPostDispatchFrontend',
            'Enlight_Controller_Action_PostDispatch_Backend' => 'onPostDispatchBackend'
        );
    }

    /**
     * choose correct tpl folder
     * 
     * @param Enlight_Event_EventArgs $args
     */
    public function onPostDispatchBackend(Enlight_Event_EventArgs $args)
    {
        $request = $args->getSubject()->Request();
        $response = $args->getSubject()->Response();

        if (!$request->isDispatched() || $response->isException()) {
            return;
        }
        $this->container->get('Template')->addTemplateDir($this->path . 'Views/');
    }

    /**
     * choose correct tpl folder and extend shopware templates
     * 
     * @param Enlight_Event_EventArgs $args
     */
    public function onPostDispatchFrontend(Enlight_Event_EventArgs $args)
    {
        $request = $args->getSubject()->Request();
        $response = $args->getSubject()->Response();
        $view = $args->getSubject()->View();

        if (!$request->isDispatched() || $response->isException()) {
            return;
        }
        
        $moptPayoneMain = $this->container->get('MoptPayoneMain');
        $config = $moptPayoneMain->getPayoneConfig();

        $controllerName = $request->getControllerName();

        $this->setCorrectViewsFolder();

        $session = Shopware()->Session();

        if ($session->moptMandateData) {
            $view->assign('moptMandateData', $session->moptMandateData);
        }

        $templateSuffix = '';
        if ($this->container->get('MoptPayoneMain')->getHelper()->isResponsive()) {
            $templateSuffix = '_responsive';
        }

        $view->extendsTemplate('frontend/checkout/mopt_confirm_payment' . $templateSuffix . '.tpl');
        $view->extendsTemplate('frontend/checkout/mopt_confirm' . $templateSuffix . '.tpl');
        $view->extendsTemplate('frontend/checkout/mopt_finish' . $templateSuffix . '.tpl');

        unset($session->moptMandateAgreement);
        if ($request->getParam('mandate_status')) {
            $session->moptMandateAgreement = $request->getParam('mandate_status');
        }
        if ($request->getParam('moptMandateConfirm')) {
            $session->moptMandateAgreement = $request->getParam('moptMandateConfirm');
        }

        if (in_array($controllerName, array('account', 'checkout', 'register'))) {
            $moptPayoneData = $this->moptPayoneCheckEnvironment($controllerName);
            $view->assign('moptCreditCardCheckEnvironment', $moptPayoneData);
            $moptPayoneFormData = array_merge($view->sFormData, $moptPayoneData['sFormData']);
            $moptPaymentHelper = $this->container->get('MoptPayoneMain')->getPaymentHelper();
            $mpotPaymentName = $moptPaymentHelper->getPaymentNameFromId($moptPayoneFormData['payment']);
            if ($moptPaymentHelper->isPayoneCreditcardNotGrouped($mpotPaymentName)) {
                $moptPayoneFormData['payment'] = 'mopt_payone_creditcard';
            }
            $view->assign('sFormData', $moptPayoneFormData);
            $view->assign('moptPaymentConfigParams', $this->moptPaymentConfigParams($session->moptMandateDataDownload));
            $view->assign('moptMandateAgreementError', $session->moptMandateAgreementError);
            unset($session->moptMandateAgreementError);
        }

        if ($controllerName == 'account' && $request->getActionName() == 'index') {
            if ($session->moptAddressCheckNeedsUserVerification) {
                $view->assign('moptAddressCheckNeedsUserVerification', $session->moptAddressCheckNeedsUserVerification);
                $view->extendsTemplate('frontend/account/mopt_billing' . $templateSuffix . '.tpl');
            }
            if ($session->moptShippingAddressCheckNeedsUserVerification) {
                $view->assign('moptShippingAddressCheckNeedsUserVerification', $session->moptShippingAddressCheckNeedsUserVerification);
                $view->extendsTemplate('frontend/account/mopt_shipping' . $templateSuffix . '.tpl');
            }
        }

        if ($controllerName == 'account' && $request->getActionName() == 'payment') {
            if ($_SESSION['moptConsumerScoreCheckNeedsUserAgreement']) {
                $view->assign('moptConsumerScoreCheckNeedsUserAgreement', $session->moptConsumerScoreCheckNeedsUserAgreement);
            } else {
                $view->assign('moptConsumerScoreCheckNeedsUserAgreement', false);
            }
            $view->extendsTemplate('frontend/account/mopt_consumescore' . $templateSuffix . '.tpl');
        }

        if (($controllerName == 'checkout' && $request->getActionName() == 'confirm')) {
            if ($session->moptAddressCheckNeedsUserVerification) {
                $view->assign('moptAddressCheckNeedsUserVerification', $session->moptAddressCheckNeedsUserVerification);
                $view->extendsTemplate('frontend/checkout/mopt_confirm' . $templateSuffix . '.tpl');
            }
            if ($session->moptShippingAddressCheckNeedsUserVerification) {
                $view->assign('moptShippingAddressCheckNeedsUserVerification', $session->moptShippingAddressCheckNeedsUserVerification);
                $view->extendsTemplate('frontend/checkout/mopt_shipping_confirm' . $templateSuffix . '.tpl');
            }
            $request = $args->getSubject()->Request();

            if ($request->getParam('moptAddressCheckNeedsUserVerification')) {
                $view->assign('moptAddressCheckNeedsUserVerification', $request->getParam('moptAddressCheckNeedsUserVerification'));
                $session->moptAddressCheckOriginalAddress = $request->getParam('moptAddressCheckOriginalAddress');
                $session->moptAddressCheckCorrectedAddress = $request->getParam('moptAddressCheckCorrectedAddress');
                $session->moptAddressCheckTarget = $request->getParam('moptAddressCheckTarget');
                $view->extendsTemplate('frontend/checkout/mopt_confirm' . $templateSuffix . '.tpl');
            }

            if ($request->getParam('moptShippingAddressCheckNeedsUserVerification')) {
                $view->assign('moptShippingAddressCheckNeedsUserVerification', $request->getParam('moptShippingAddressCheckNeedsUserVerification'));
                $session->moptShippingAddressCheckOriginalAddress = $request->getParam('moptShippingAddressCheckOriginalAddress');
                $session->moptShippingAddressCheckCorrectedAddress = $request->getParam('moptShippingAddressCheckCorrectedAddress');
                $session->moptShippingAddressCheckTarget = $request->getParam('moptShippingAddressCheckTarget');
                $view->extendsTemplate('frontend/checkout/mopt_shipping_confirm' . $templateSuffix . '.tpl');
            }
            
            if ($session->moptConsumerScoreCheckNeedsUserAgreement) {
                $view->assign('moptConsumerScoreCheckNeedsUserAgreement', $session->moptConsumerScoreCheckNeedsUserAgreement);
                $view->extendsTemplate('frontend/account/mopt_consumescore' . $templateSuffix . '.tpl');
            }
        }
        if (($controllerName == 'checkout' && $request->getActionName() == 'confirm')) {
            unset($session->moptBarzahlenCode);
        }
        if (($controllerName == 'checkout' && $request->getActionName() == 'finish')) {
            if ($session->moptBarzahlenCode) {
                $view->assign('moptBarzahlenCode', $session->moptBarzahlenCode);
            }
        }
    }

    /**
     * call responsive check method and set views folder according to result
     */
    public function setCorrectViewsFolder()
    {
        /** @var $shopContext \Shopware\Models\Shop\Shop */
        $shopContext = $this->container->get('bootstrap')->getResource('shop');
        $templateVersion = $shopContext->getTemplate()->getVersion();

        if ($templateVersion >= 3) {
            $this->container->get('Template')->addTemplateDir($this->path . 'Views/');
        } elseif ($this->container->get('MoptPayoneMain')->getHelper()->isResponsive()) {
            $this->container->get('Template')->addTemplateDir($this->path . 'Views/conecxoResponsive/');
        } else {
            $this->container->get('Template')->addTemplateDir($this->path . 'Views/shopware4/');
        }
    }

    protected function moptPayoneCheckEnvironment($controllerName = false)
    {
        $data = array();
        $moptPayoneMain = $this->container->get('MoptPayoneMain');
        $userId = Shopware()->Session()->sUserId;
        $shopLanguage = explode('_', Shopware()->Shop()->getLocale()->getLocale());

        $sql = 'SELECT `moptPaymentData` FROM s_plugin_mopt_payone_payment_data WHERE userId = ?';
        $paymentData = unserialize(Shopware()->Db()->fetchOne($sql, $userId));

        $paymentMeans = Shopware()->Modules()->Admin()->sGetPaymentMeans();
        $groupedPaymentMeans = false;

        if ($controllerName && $controllerName === 'checkout') {
            $groupedPaymentMeans = $moptPayoneMain->getPaymentHelper()->groupCreditcards($paymentMeans);
        }

        if ($groupedPaymentMeans) {
            $paymentMeans = $groupedPaymentMeans;
        }

        foreach ($paymentMeans as $paymentMean) {
            if ($paymentMean['id'] == 'mopt_payone_creditcard') {
                $paymentMean['mopt_payone_credit_cards'] = $moptPayoneMain->getPaymentHelper()
                        ->mapCardLetter($paymentMean['mopt_payone_credit_cards']);
                $data['payment_mean'] = $paymentMean;
            }

            //prepare additional Klarna information and retrieve birthday and phone nr from user data
            if ($moptPayoneMain->getPaymentHelper()->isPayoneKlarna($paymentMean['name'])) {
                $klarnaConfig = $moptPayoneMain->getPayoneConfig($paymentMean['id']);
                $data['moptKlarnaInformation'] = $moptPayoneMain->getPaymentHelper()
                        ->moptGetKlarnaAdditionalInformation($shopLanguage[1], $klarnaConfig['klarnaStoreId']);
                $userData = Shopware()->Modules()->Admin()->sGetUserData();
                $birthday = explode('-', $userData['billingaddress']['birthday']);
                $data['mopt_payone__klarna_birthday'] = $birthday[2];
                $data['mopt_payone__klarna_birthmonth'] = $birthday[1];
                $data['mopt_payone__klarna_birthyear'] = $birthday[0];
                $data['mopt_payone__klarna_telephone'] = $userData['billingaddress']['phone'];
                $data['mopt_payone__klarna_inst_birthday'] = $birthday[2];
                $data['mopt_payone__klarna_inst_birthmonth'] = $birthday[1];
                $data['mopt_payone__klarna_inst_birthyear'] = $birthday[0];
                $data['mopt_payone__klarna_inst_telephone'] = $userData['billingaddress']['phone'];
            }
        }

        $payoneParams = $moptPayoneMain->getParamBuilder()->getBasicParameters();
        $creditCardConfig = $this->getCreditcardConfig(); //retrieve additional creditcardconfig

        $payoneParams['mid'] = $creditCardConfig['merchant_id'];
        $payoneParams['portalid'] = $creditCardConfig['portal_id'];
        $payoneParams['key'] = $creditCardConfig['api_key'];
        $payoneParams['aid'] = $creditCardConfig['subaccount_id'];
        if ($creditCardConfig['live_mode']) {
            $payoneParams['mode'] = 'live';
        } else {
            $payoneParams['mode'] = 'test';
        }
        $payoneParams['language'] = $shopLanguage[0];
        $payoneParams['errorMessages'] = json_encode($moptPayoneMain->getPaymentHelper()
                        ->getCreditCardCheckErrorMessages());

        $generateHashService = $this->container->get('MoptPayoneBuilder')->buildServiceClientApiGenerateHash();

        $request = new \Payone_ClientApi_Request_CreditCardCheck();
        $params = array(
            'aid' => $payoneParams['aid'],
            'mid' => $payoneParams['mid'],
            'portalid' => $payoneParams['portalid'],
            'mode' => $payoneParams['mode'],
            'encoding' => 'UTF-8',
            'language' => $payoneParams['language'],
            'solution_version' => \Shopware_Plugins_Frontend_MoptPaymentPayone_Bootstrap::getVersion(),
            'solution_name' => \Shopware_Plugins_Frontend_MoptPaymentPayone_Bootstrap::getSolutionName(),
            'integrator_version' => Shopware()->Config()->Version,
            'integrator_name' => 'Shopware',
            'storecarddata' => 'yes',
        );
        $request->init($params);
        $request->setResponsetype('JSON');

        $payoneParams['hash'] = $generateHashService->generate($request, $creditCardConfig['api_key']);

        $data['moptPayoneCheckCc'] = $creditCardConfig['check_cc'];
        $data['moptCreditcardMinValid'] = (int) $creditCardConfig['creditcard_min_valid'];
        
        // remove the api key; only ['hash'] ist used 
        $creditCardConfig['api_key'] = "";
        // to be safe also remove key in $payoneParams
        $payoneParams['key'] = "";
        // also remove key from array [jsonconfig]
        $json_tmp = json_decode($creditCardConfig['jsonConfig'],true);
        unset($json_tmp['api_key']);
        $creditCardConfig['jsonConfig'] = json_encode($json_tmp);

        $data['moptCreditcardConfig'] = $creditCardConfig;
        $data['moptPayoneParams'] = $payoneParams;

        if ($paymentData) {
            $data['sFormData'] = $paymentData;
        } else {
            $data['sFormData'] = array();
        }

        return $data;
    }

    protected function moptPaymentConfigParams($mandateData)
    {
        $data = array();
        $moptPayoneMain = $this->container->get('MoptPayoneMain');
        $config = $moptPayoneMain->getPayoneConfig();

        $paymentMeans = Shopware()->Modules()->Admin()->sGetPaymentMeans();
        foreach ($paymentMeans as $paymentMean) {
            if ($moptPayoneMain->getPaymentHelper()->isPayoneDebitnote($paymentMean['name'])) {
                $data['moptDebitCountries'] = $moptPayoneMain->getPaymentHelper()
                        ->moptGetCountriesAssignedToPayment($paymentMean['id']);
                break;
            }
        }

        //get country via user object
        $userData = Shopware()->Modules()->Admin()->sGetUserData();

        $data['moptShowAccountnumber'] = (bool) ($config['showAccountnumber'] && $userData['additional']['country']['countryiso'] === 'DE');
        if (Shopware()->Config()->currency === 'CHF' && $userData['additional']['country']['countryiso'] === 'CH') {
            $data['moptIsSwiss'] = true;
        } else {
            $data['moptIsSwiss'] = false;
        }

        if ($mandateData) {
            $data['moptMandateDownloadEnabled'] = (bool) ($config['mandateDownloadEnabled']);
        } else {
            $data['moptMandateDownloadEnabled'] = false;
        }

        return $data;
    }

    protected function getCreditcardConfig()
    {
        $shopId = $this->container->get('shop')->getId();

        $sql = 'SELECT * FROM s_plugin_mopt_payone_creditcard_config WHERE shop_id = ?';
        $configData = Shopware()->Db()->fetchRow($sql, $shopId);

        if (!$configData) {
            $sql = 'SELECT * FROM s_plugin_mopt_payone_creditcard_config WHERE is_default = ?';
            $configData = Shopware()->Db()->fetchRow($sql, true);
        }

        if (!$configData) {
            $configData = array('integration_type' => '1');
        }

        if ($configData['show_errors']) {

            $langSql = 'SELECT locale FROM s_core_locales WHERE id = ?';
            $locale = Shopware()->Db()->fetchOne($langSql, $configData['error_locale_id']);
            $locale = explode('_', $locale);
            $configData['error_locale_id'] = $locale[0];
        }

        $configData['jsonConfig'] = json_encode($configData);

        return $configData;
    }

}
