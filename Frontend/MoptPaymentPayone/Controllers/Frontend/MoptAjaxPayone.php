<?php

/**
 * integrate Payone protect and Ajax call handling
 */
class Shopware_Controllers_Frontend_MoptAjaxPayone extends Enlight_Controller_Action
{

    /**
     * Reference to sAdmin object (core/class/sAdmin.php)
     *
     * @var sAdmin
     */
    protected $admin;

    /**
     * PayoneMain
     * @var Mopt_PayoneMain 
     */
    protected $moptPayoneMain = null;

    /**
     * PayoneMain
     * @var Mopt_PayonePaymentHelper 
     */
    protected $moptPayonePaymentHelper = null;

    /**
     * PayOne Builder
     * @var PayoneBuilder 
     */
    protected $payoneServiceBuilder = null;
    protected $service = null;
    protected $session = null;

    /**
     * init payment controller
     */
    public function init()
    {
        $this->admin = Shopware()->Modules()->Admin();
        $this->payoneServiceBuilder = $this->Plugin()->Application()->MoptPayoneBuilder();
        $this->moptPayoneMain = $this->Plugin()->Application()->MoptPayoneMain();
        $this->moptPayonePaymentHelper = $this->moptPayoneMain->getPaymentHelper();
        $this->session = Shopware()->Session();
    }

    /**
     * ask user wether to keep original submittted or corrected values
     */
    public function ajaxGetConsumerScoreUserAgreementAction()
    {
        $session = Shopware()->Session();

        //get config
        if ($_SESSION['moptPaymentId']) {
            $paymentId = $_SESSION['moptPaymentId'];
        } else {
            $paymentId = $session->moptPaymentId;
        }

        $config = $this->moptPayoneMain->getPayoneConfig($paymentId);

        //add custom texts to view
        if ($config['consumerscoreNoteActive']) {
            $this->View()->consumerscoreNoteMessage = Shopware()->Snippets()
                    ->getNamespace('frontend/MoptPaymentPayone/messages')
                    ->get('consumerscoreNoteMessage');
        }
        if ($config['consumerscoreAgreementActive']) {
            $this->View()->consumerscoreAgreementMessage = Shopware()->Snippets()
                    ->getNamespace('frontend/MoptPaymentPayone/messages')
                    ->get('consumerscoreAgreementMessage');
        }

        unset($session->moptConsumerScoreCheckNeedsUserAgreement);
        unset($_SESSION['moptConsumerScoreCheckNeedsUserAgreement']);
    }

    /**
     * ask user wether to keep original submittted or newly chosen payment method
     */
    public function ajaxVerifyPaymentAction()
    {
        $this->View()->moptSelectedPayment = $this->Request()->getParam('moptSelectedPayment');
        $this->View()->moptOriginalPayment = $this->Request()->getParam('moptOriginalPayment');
        $this->View()->moptCheckedId = $this->Request()->getParam('moptCheckedId');
    }

    public function checkConsumerScoreAction()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
        $session = Shopware()->Session();
        $userId = $session->sUserId;

        unset($session->moptConsumerScoreCheckNeedsUserAgreement);
        unset($_SESSION['moptConsumerScoreCheckNeedsUserAgreement']);

        //get config
        if ($_SESSION['moptPaymentId']) {
            $paymentId = $_SESSION['moptPaymentId'];
        } else {
            $paymentId = $session->moptPaymentId;
        }

        //get payment data
        if ($_SESSION['moptPaymentData']) {
            $paymentData = $_SESSION['moptPaymentData'];
        } else {
            $paymentData = $session->moptPaymentData;
        }

        $config = $this->moptPayoneMain->getPayoneConfig($paymentId);
        $user = $this->admin->sGetUserData();
        $billingAddressData = $user['billingaddress'];
        $billingAddressData['country'] = $billingAddressData['countryID'];
        //perform consumerscorecheck
        $params = $this->moptPayoneMain->getParamBuilder()
                ->getConsumerscoreCheckParams($billingAddressData, $paymentId);
        $service = $this->payoneServiceBuilder->buildServiceVerificationConsumerscore();
        $service->getServiceProtocol()->addRepository(Shopware()->Models()->getRepository(
                        'Shopware\CustomModels\MoptPayoneApiLog\MoptPayoneApiLog'
        ));

        $request = new Payone_Api_Request_Consumerscore($params);

        $billingAddressChecktype = 'NO';
        $request->setAddresschecktype($billingAddressChecktype);
        $request->setConsumerscoretype($config['consumerscoreCheckMode']);

        $response = $service->score($request);

        if ($response->getStatus() == 'VALID') {
            //save result
            $this->moptPayoneMain->getHelper()->saveConsumerScoreCheckResult($userId, $response);
            unset($session->moptConsumerScoreCheckNeedsUserAgreement);
            unset($_SESSION['moptConsumerScoreCheckNeedsUserAgreement']);
            unset($session->moptPaymentId);
            echo json_encode(true);
        } else {
            //save error
            $this->moptPayoneMain->getHelper()->saveConsumerScoreError($userId, $response);
            unset($session->moptConsumerScoreCheckNeedsUserAgreement);
            unset($_SESSION['moptConsumerScoreCheckNeedsUserAgreement']);
            unset($session->moptPaymentId);
            //choose next action according to config
            if ($config['consumerscoreFailureHandling'] == 0) {
                //abort
                //delete payment data and set to payone prepayment
                $this->moptPayoneMain->getPaymentHelper()->deletePaymentData($userId);
                $this->moptPayoneMain->getPaymentHelper()->setConfiguredDefaultPaymentAsPayment($userId);
                echo json_encode(false);
            } else {
                //proceed 
                echo json_encode(true);
            }
        }
    }

    public function doNotCheckConsumerScoreAction()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
        $session = Shopware()->Session();

        unset($session->moptConsumerScoreCheckNeedsUserAgreement);
        unset($_SESSION['moptConsumerScoreCheckNeedsUserAgreement']);

        $userId = $session->sUserId;
        $config = $this->moptPayoneMain->getPayoneConfig($session->moptPaymentId);

        $this->moptPayoneMain->getHelper()->saveConsumerScoreDenied($userId);

        unset($session->moptConsumerScoreCheckNeedsUserAgreement);
        unset($_SESSION['moptConsumerScoreCheckNeedsUserAgreement']);
        unset($session->moptPaymentId);

        if ($config['consumerscoreFailureHandling'] == 0) {
            //abort
            //delete payment data and set to p1 prepayment
            $this->moptPayoneMain->getPaymentHelper()->deletePaymentData($userId);
            $this->moptPayoneMain->getPaymentHelper()->setConfiguredDefaultPaymentAsPayment($userId);
            echo json_encode(false);
        } else {
            //proceed
            echo json_encode(true);
        }
    }

    public function saveOriginalAddressAction()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
        $session = Shopware()->Session();

        $userId = $session->sUserId;
        $response = unserialize($session->moptAddressCheckCorrectedAddress);
        $config = $this->moptPayoneMain->getPayoneConfig();

        $mappedPersonStatus = $this->moptPayoneMain->getHelper()
                ->getUserScoringValue($response->getPersonstatus(), $config);
        $mappedPersonStatus = $this->moptPayoneMain->getHelper()->getUserScoringColorFromValue($mappedPersonStatus);
        $this->moptPayoneMain->getHelper()->saveAddressCheckResult('billing', $userId, $response, $mappedPersonStatus);

        unset($session->moptAddressCheckNeedsUserVerification);
        unset($session->moptAddressCheckOriginalAddress);
        unset($session->moptAddressCheckCorrectedAddress);
    }

    public function saveCorrectedAddressAction()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
        $session = Shopware()->Session();
        $userId = $session->sUserId;
        $response = unserialize($session->moptAddressCheckCorrectedAddress);
        $config = $this->moptPayoneMain->getPayoneConfig();

        $this->moptPayoneMain->getHelper()->saveCorrectedBillingAddress($userId, $response);
        $mappedPersonStatus = $this->moptPayoneMain->getHelper()
                ->getUserScoringValue($response->getPersonstatus(), $config);
        $mappedPersonStatus = $this->moptPayoneMain->getHelper()->getUserScoringColorFromValue($mappedPersonStatus);
        $this->moptPayoneMain->getHelper()->saveAddressCheckResult('billing', $userId, $response, $mappedPersonStatus);

        unset($session->moptAddressCheckNeedsUserVerification);
        unset($session->moptAddressCheckOriginalAddress);
        unset($session->moptAddressCheckCorrectedAddress);
    }

    public function saveOriginalShippingAddressAction()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
        $session = Shopware()->Session();

        $userId = $session->sUserId;
        $response = unserialize($session->moptShippingAddressCheckCorrectedAddress);
        $config = $this->moptPayoneMain->getPayoneConfig();

        $mappedPersonStatus = $this->moptPayoneMain->getHelper()
                ->getUserScoringValue($response->getPersonstatus(), $config);
        $mappedPersonStatus = $this->moptPayoneMain->getHelper()->getUserScoringColorFromValue($mappedPersonStatus);
        $this->moptPayoneMain->getHelper()->saveAddressCheckResult('shipping', $userId, $response, $mappedPersonStatus);

        unset($session->moptShippingAddressCheckNeedsUserVerification);
        unset($session->moptShippingAddressCheckOriginalAddress);
        unset($session->moptShippingAddressCheckCorrectedAddress);
    }

    public function saveCorrectedShippingAddressAction()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
        $session = Shopware()->Session();
        $userId = $session->sUserId;
        $response = unserialize($session->moptShippingAddressCheckCorrectedAddress);
        $config = $this->moptPayoneMain->getPayoneConfig();

        $this->moptPayoneMain->getHelper()->saveCorrectedShippingAddress($userId, $response);
        $mappedPersonStatus = $this->moptPayoneMain->getHelper()
                ->getUserScoringValue($response->getPersonstatus(), $config);
        $mappedPersonStatus = $this->moptPayoneMain->getHelper()->getUserScoringColorFromValue($mappedPersonStatus);
        $this->moptPayoneMain->getHelper()->saveAddressCheckResult('shipping', $userId, $response, $mappedPersonStatus);

        unset($session->moptShippingAddressCheckNeedsUserVerification);
        unset($session->moptShippingAddressCheckOriginalAddress);
        unset($session->moptShippingAddressCheckCorrectedAddress);
    }

    /**
     * ask user wether to keep original submittted or corrected values
     */
    public function ajaxVerifyShippingAddressAction()
    {
        $session = Shopware()->Session();
        $response = unserialize($session->moptShippingAddressCheckCorrectedAddress);
        $this->View()->moptShippingAddressCheckOriginalAddress = $session->moptShippingAddressCheckOriginalAddress;
        $this->View()->moptShippingAddressCheckCorrectedAddress = $response->toArray();

        if ($session->moptShippingAddressCheckTarget) {
            $this->View()->moptShippingAddressCheckTarget = $session->moptShippingAddressCheckTarget;
        } else {
            $this->View()->moptShippingAddressCheckTarget = 'checkout';
        }
    }

    /**
     * ask user wether to keep original submittted or corrected values
     */
    public function ajaxVerifyAddressAction()
    {
        $session = Shopware()->Session();
        $response = unserialize($session->moptAddressCheckCorrectedAddress);
        $this->View()->moptAddressCheckOriginalAddress = $session->moptAddressCheckOriginalAddress;
        $this->View()->moptAddressCheckCorrectedAddress = $response->toArray();

        if ($session->moptAddressCheckTarget) {
            $this->View()->moptAddressCheckTarget = $session->moptAddressCheckTarget;
        } else {
            $this->View()->moptAddressCheckTarget = 'checkout';
        }
    }

    /**
     * AJAX action called from creditcard layer, saves client api response
     */
    public function savePseudoCardAction()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
        $userId = Shopware()->Session()->sUserId;

        $paymentData['mopt_payone__cc_truncatedcardpan'] = $this->Request()->getPost('mopt_payone__cc_truncatedcardpan');
        $paymentData['mopt_payone__cc_pseudocardpan'] = $this->Request()->getPost('mopt_payone__cc_pseudocardpan');
        $paymentData['mopt_payone__cc_cardtype'] = $this->Request()->getPost('mopt_payone__cc_cardtype');
        $paymentData['mopt_payone__cc_accountholder'] = $this->Request()->getPost('mopt_payone__cc_accountholder');
        $paymentData['mopt_payone__cc_month'] = $this->Request()->getPost('mopt_payone__cc_month');
        $paymentData['mopt_payone__cc_year'] = $this->Request()->getPost('mopt_payone__cc_year');
        $paymentData['mopt_payone__cc_paymentname'] = $this->Request()->getPost('mopt_payone__cc_paymentname');
        $paymentData['mopt_payone__cc_paymentid'] = $this->Request()->getPost('mopt_payone__cc_paymentid');
        $paymentData['mopt_payone__cc_paymentdescription'] = $this->Request()->getPost('mopt_payone__cc_paymentdescription');

        $actualPaymentId = $paymentData['mopt_payone__cc_paymentid'];

        $sql = 'replace into `s_plugin_mopt_payone_payment_data`' .
                '(`userId`,`moptPaymentData`) values (?,?)';
        $paymentData = serialize($paymentData);
        Shopware()->Db()->query($sql, array($userId, $paymentData));

        $userData = $this->admin->sGetUserData();
        $previousPayment = $this->admin->sGetPaymentMeanById($userData['additional']['user']['paymentID']);

        if ($previousPayment['paymentTable']) {
            $deleteSQL = 'DELETE FROM ' . $previousPayment['paymentTable'] . ' WHERE userID=?';
            Shopware()->Db()->query($deleteSQL, array(Shopware()->Session()->sUserId));
        }

        $sqlPayment = "UPDATE s_user SET paymentID = ? WHERE id = ?";
        Shopware()->Db()->query($sqlPayment, array($actualPaymentId, $userId));
    }

    /**
     * download SEPA mandate PDF file on success page
     * 
     * @return mixed
     */
    public function downloadMandateAction()
    {
        if (!Shopware()->Session()->moptMandateDataDownload) {
            $this->forward('downloadError');
            return;
        }

        $params = $this->moptPayoneMain->getParamBuilder()->buildGetFile($this->getPaymentId(), Shopware()->Session()->moptMandateDataDownload);
        $service = $this->payoneServiceBuilder->buildServiceManagementGetFile();
        $request = new Payone_Api_Request_GetFile($params);

        try {
            $response = $service->getFile($request);
            $this->Front()->Plugins()->ViewRenderer()->setNoRender();

            $httpResponse = $this->Response();
            $httpResponse->setHeader('Cache-Control', 'public');
            $httpResponse->setHeader('Content-Description', 'File Transfer');
            $httpResponse->setHeader('Content-disposition', 'attachment; filename=' . "Payone_Mandate.pdf");
            $httpResponse->setHeader('Content-Type', 'application/pdf');
            $httpResponse->setHeader('Content-Transfer-Encoding', 'binary');
            $httpResponse->setHeader('Content-Length', strlen($response->getRawResponse()));
            echo $response->getRawResponse();
        } catch (Exception $exc) {
            $this->forward('downloadError');
        }
    }

    /**
     * Returns the payment plugin config data.
     *
     * @return Shopware_Plugins_Frontend_MoptPaymentPayone_Bootstrap
     */
    public function Plugin()
    {
        return Shopware()->Plugins()->Frontend()->MoptPaymentPayone();
    }

    /**
     * get actual payment method id
     * 
     * @return string
     */
    protected function getPaymentId()
    {
        return Shopware()->Session()->sOrderVariables['sUserData']['additional']['payment']['id'];
    }

}
