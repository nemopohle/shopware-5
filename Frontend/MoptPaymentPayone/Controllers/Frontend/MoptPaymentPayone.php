<?php

/**
 * mopt payone payment controller
 */
class Shopware_Controllers_Frontend_MoptPaymentPayone extends Shopware_Controllers_Frontend_Payment
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
     * check if everything is ok and proceed with payment
     * 
     * @return redirect to payment action or checkout controller 
     */
    public function indexAction()
    {
        if ($this->session->moptConsumerScoreCheckNeedsUserAgreement) {
            return $this->redirect(array('controller' => 'checkout'));
        }

        $action = $this->moptPayonePaymentHelper->getActionFromPaymentName($this->getPaymentShortName());

        if ($action === 'debitnote') {
            if($this->session->moptMandateAgreement === 'on') {
                $this->session->moptMandateAgreement = 1;
            }
            
            if ($this->session->moptMandateData['mopt_payone__showMandateText'] == true 
                    && (int) $this->session->moptMandateAgreement !== 1) {
                $this->session->moptMandateAgreementError = true;
                $action = false;
            }
        }

        if ($action) {
            return $this->redirect(array('action' => $action, 'forceSecure' => true));
        } else {
            return $this->redirect(array('controller' => 'checkout'));
        }
    }

    public function creditcardAction()
    {
        $response = $this->mopt_payone__creditcard();
        if ($response->isRedirect()) {
            $this->mopt_payone__handleRedirectFeedback($response);
        } else {
            $this->mopt_payone__handleDirectFeedback($response);
        }
    }

    public function instanttransferAction()
    {
        $response = $this->mopt_payone__instanttransfer();
        $this->mopt_payone__handleRedirectFeedback($response);
    }

    public function paypalAction()
    {
        $response = $this->mopt_payone__paypal();

        if (Shopware()->Session()->moptPaypalEcsWorkerId) {
            $this->mopt_payone__handleDirectFeedback($response);
        } else {
            $this->mopt_payone__handleRedirectFeedback($response);
        }
    }

    public function debitnoteAction()
    {
        $response = $this->mopt_payone__debitnote();
        $this->mopt_payone__handleDirectFeedback($response);
    }

    public function standardAction()
    {
        $response = $this->mopt_payone__standard();
        $this->mopt_payone__handleDirectFeedback($response);
    }

    public function cashondelAction()
    {
        $response = $this->mopt_payone__cashondel();
        $this->mopt_payone__handleDirectFeedback($response);
    }

    public function klarnaAction()
    {
        $response = $this->mopt_payone__klarna();
        $this->mopt_payone__handleDirectFeedback($response);
    }
    
    public function barzahlenAction()
    {
        $response = $this->mopt_payone__barzahlen();
        $this->mopt_payone__handleDirectFeedback($response);
    }

    public function financeAction()
    {
        $response = $this->mopt_payone__finance();
        $this->mopt_payone__handleRedirectFeedback($response);
    }
    
    public function paydirektAction()
    {
        $response = $this->mopt_payone__paydirekt();
        $this->mopt_payone__handleRedirectFeedback($response);
    }

    /**
     * @return $response 
     */
    protected function mopt_payone__creditcard()
    {
        $paymentData = $this->getPaymentData();
        $paymendId = $paymentData['mopt_payone__cc_paymentid'];
        $config = $this->moptPayoneMain->getPayoneConfig($paymendId);

        $payment = $this->moptPayoneMain->getParamBuilder()
                ->getPaymentCreditCard($this->Front()->Router(), $paymentData);
        if (Shopware()->Session()->moptOverwriteEcommerceMode) {
            $payment->setEcommercemode(Shopware()->Session()->moptOverwriteEcommerceMode);
            unset(Shopware()->Session()->moptOverwriteEcommerceMode);
        }
        $response = $this->buildAndCallPayment($config, 'cc', $payment);

        return $response;
    }
    
    /**
     * @return $response
     */
    protected function mopt_payone__barzahlen()
    {
        $paymendId = $this->getPaymentId();
        $config = $this->moptPayoneMain->getPayoneConfig($paymendId);
        $response = $this->buildAndCallPayment($config, 'csh', null);

        return $response;
    }

    /**
     * @return $response
     */
    protected function mopt_payone__instanttransfer()
    {
        $paymentData = $this->getPaymentData();
        $paymentShortName = $this->getPaymentShortName();

        $paymentData['mopt_payone__onlinebanktransfertype'] = $this->moptPayonePaymentHelper
                ->getOnlineBankTransferTypeFromPaymentName($paymentShortName);

        $config = $this->moptPayoneMain->getPayoneConfig($this->getPaymentId());
        $payment = $this->moptPayoneMain->getParamBuilder()
                ->getPaymentInstantBankTransfer($this->Front()->Router(), $paymentData);
        $response = $this->buildAndCallPayment($config, 'sb', $payment);

        return $response;
    }

  /**
     * @return $response 
     */
    protected function mopt_payone__paypal()
    {
        $config = $this->moptPayoneMain->getPayoneConfig($this->getPaymentId());
        $recurringOrder = false;
        $isInitialRecurringRequest = false;
        $forceAuthorize = false;

        if ($this->isRecurringOrder() || $this->moptPayoneMain->getHelper()->isAboCommerceArticleInBasket()) {
            $recurringOrder = true;
            $forceAuthorize = true;
        }

        if ($recurringOrder && !isset(Shopware()->Session()->moptIsPaypalRecurringOrder)) {
            $isInitialRecurringRequest = true;
            $forceAuthorize = false;
        }

        if (Shopware()->Session()->moptPaypalEcsWorkerId) {
            $moptPaypalEcsWorkerId = Shopware()->Session()->moptPaypalEcsWorkerId;
            $payment = $this->moptPayoneMain->getParamBuilder()->getPaymentPaypalEcs($this->Front()->Router());
        } else {
            $payment = $this->moptPayoneMain->getParamBuilder()->getPaymentPaypal($this->Front()->Router(), $isInitialRecurringRequest);
            $moptPaypalEcsWorkerId = false;
        }

        $response = $this->buildAndCallPayment($config, 'wlt', $payment, $moptPaypalEcsWorkerId, $recurringOrder, $isInitialRecurringRequest, $forceAuthorize);

        return $response;
    }
    
      /**
     * @return $response 
     */
    protected function mopt_payone__paydirekt()
    {
        $config = $this->moptPayoneMain->getPayoneConfig($this->getPaymentId());
        $recurringOrder = false;
        $isInitialRecurringRequest = false;
        $forceAuthorize = false;

        if ($this->isRecurringOrder() || $this->moptPayoneMain->getHelper()->isAboCommerceArticleInBasket()) {
            $recurringOrder = true;
            $forceAuthorize = true;
        }

        if ($recurringOrder && !isset(Shopware()->Session()->moptIsPaydirektRecurringOrder)) {
            $isInitialRecurringRequest = true;
            $forceAuthorize = false;
        }

        $payment = $this->moptPayoneMain->getParamBuilder()->getPaymentPaydirekt($this->Front()->Router(), $isInitialRecurringRequest);
        $response = $this->buildAndCallPayment($config, 'wlt', $payment, false, $recurringOrder, $isInitialRecurringRequest, $forceAuthorize);

        return $response;
    }
    
    /**
     * @return redirect 
     */
    public function paypalRecurringSuccessAction()
    {
        $config = $this->moptPayoneMain->getPayoneConfig($this->getPaymentId());
        $recurringOrder = true;
        $customerPresent = false;
        $forceAuthorize = true;

        $payment = $this->moptPayoneMain->getParamBuilder()->getPaymentPaypal($this->Front()->Router());

        $response = $this->buildAndCallPayment($config, 'wlt', $payment, $moptPaypalEcsWorkerId, $recurringOrder, $customerPresent, $forceAuthorize);

        $this->mopt_payone__handleDirectFeedback($response);
    }
    
    /**
     * @return redirect 
     */
    public function paydirektRecurringSuccessAction()
    {
        $config = $this->moptPayoneMain->getPayoneConfig($this->getPaymentId());
        $recurringOrder = true;
        $customerPresent = false;
        $forceAuthorize = true;

        $payment = $this->moptPayoneMain->getParamBuilder()->getPaymentPaydirekt($this->Front()->Router());
        $response = $this->buildAndCallPayment($config, 'wlt', $payment, false, $recurringOrder, $customerPresent, $forceAuthorize);
        $this->mopt_payone__handleDirectFeedback($response);
    }

    /**
     * @return $response 
     */
    protected function mopt_payone__debitnote()
    {
        $paymentData = Shopware()->Session()->moptPayment;

        $config = $this->moptPayoneMain->getPayoneConfig($this->getPaymentId());
        $payment = $this->moptPayoneMain->getParamBuilder()->getPaymentDebitNote($paymentData);
        $response = $this->buildAndCallPayment($config, 'elv', $payment);

        return $response;
    }

    /**
     * @return $response 
     */
    protected function mopt_payone__standard()
    {
        $paymentId = $this->getPaymentShortName();

        if ($this->moptPayonePaymentHelper->isPayoneInvoice($paymentId)) {
            $clearingType = Payone_Enum_ClearingType::INVOICE;
        } else {
            $clearingType = Payone_Enum_ClearingType::ADVANCEPAYMENT;
        }

        $config = $this->moptPayoneMain->getPayoneConfig($this->getPaymentId());
        $response = $this->buildAndCallPayment($config, $clearingType, null);

        return $response;
    }

    /**
     * @return $response 
     */
    protected function mopt_payone__cashondel()
    {
        $config = $this->moptPayoneMain->getPayoneConfig($this->getPaymentId());
        $payment = $this->moptPayoneMain->getParamBuilder()->getPaymentCashOnDelivery($this->getUserData());
        $response = $this->buildAndCallPayment($config, 'cod', $payment);

        return $response;
    }

    /**
     * @return $response 
     */
    protected function mopt_payone__klarna()
    {
        if ($this->moptPayonePaymentHelper->isPayoneKlarnaInstallment($this->getPaymentShortName())) {
            $financeType = Payone_Api_Enum_FinancingType::KLS;
        } else {
            $financeType = Payone_Api_Enum_FinancingType::KLV;
        }


        $config = $this->moptPayoneMain->getPayoneConfig($this->getPaymentId());

        if ($config['klarnaCampaignCode']) {
            $campaignId = $config['klarnaCampaignCode'];
        } else {
            $campaignId = false;
        }

        $payment = $this->moptPayoneMain->getParamBuilder()->getPaymentKlarna($financeType, $campaignId);

        $response = $this->buildAndCallPayment($config, 'fnc', $payment);

        return $response;
    }

    /**
     * @return $response 
     */
    protected function mopt_payone__finance()
    {
        $paymentId = $this->getPaymentShortName();

        if ($this->moptPayonePaymentHelper->isPayoneBillsafe($paymentId)) {
            $financeType = Payone_Api_Enum_FinancingType::BSV;
        } else {
            $financeType = Payone_Api_Enum_FinancingType::CFR;
        }

        $config = $this->moptPayoneMain->getPayoneConfig($this->getPaymentId());
        $payment = $this->moptPayoneMain->getParamBuilder()->getPaymentFinance($financeType, $this->Front()->Router());
        $response = $this->buildAndCallPayment($config, 'fnc', $payment);

        return $response;
    }

    /**
     * this action is submitted to Payone with redirect payments
     * url is called when customer payment succeeds on 3rd party site
     */
    public function successAction()
    {
        $session = Shopware()->Session();
        $this->forward('finishOrder', 'MoptPaymentPayone', null, 
                array('txid' => $session->txId, 'hash' => $session->paymentReference));
    }

    /**
     * this action is submitted to Payone with redirect payments
     * url is called when customer payment fails on 3rd party site
     */
    public function failureAction()
    {
        $this->View()->errormessage = Shopware()->Snippets()->getNamespace('frontend/MoptPaymentPayone/errorMessages')
                ->get('generalErrorMessage', 'Es ist ein Fehler aufgetreten', true);
        $this->forward('error');
    }

    /**
     * this action is submitted to Payone with redirect payments
     * url is called when customer cancels redirect payment on 3rd party site
     */
    public function cancelAction()
    {
        $this->View()->errormessage = Shopware()->Snippets()->getNamespace('frontend/MoptPaymentPayone/messages')
                ->get('cancelMessage', 'Der Bezahlvorgang wurde abgebrochen', true);
        $this->forward('error');
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
     * Cancel action method
     * renders automatically error.tpl, errormessage is already assigned e.g. mopt_payone__handleDirectFeedback
     */
    public function errorAction()
    {
        
    }

    /**
     * acutally save order
     *
     * @param string $txId
     * @param string $moptPaymentReference
     * @return redirect to finish page 
     */
    public function finishOrderAction()
    {
        $txId = $this->Request()->getParam('txid');
        $moptPaymentReference = $this->Request()->getParam('hash');
        $session = Shopware()->Session();

        if (!$this->isOrderFinished($txId)) {
            $orderHash = md5(serialize($session['sOrderVariables']));
            if ($session->moptOrderHash !== $orderHash) {
                $this->saveOrder($txId, $moptPaymentReference, 21);
            } else {
                $this->saveOrder($txId, $moptPaymentReference);
            }
        }

        if ($session->moptClearingData) {
            $clearingData = json_encode($session->moptClearingData);
            unset($session->moptClearingData);
        }

        $sql = 'SELECT `id` FROM `s_order` WHERE transactionID = ?'; //get order id
        $orderId = Shopware()->Db()->fetchOne($sql, $txId);

        if ($clearingData) {
            $sql = 'UPDATE `s_order_attributes`' .
                    'SET mopt_payone_txid=?, mopt_payone_is_authorized=?, mopt_payone_payment_reference=?, '
                    . 'mopt_payone_order_hash=?, mopt_payone_clearing_data=? WHERE orderID = ?';
            Shopware()->Db()->query($sql, array($txId, $session->moptIsAuthorized, $session->paymentReference,
                $session->moptOrderHash, $clearingData, $orderId));
        } else {
            $sql = 'UPDATE `s_order_attributes`' .
                    'SET mopt_payone_txid=?, mopt_payone_is_authorized=?, mopt_payone_payment_reference=?, '
                    . 'mopt_payone_order_hash=? WHERE orderID = ?';
            Shopware()->Db()->query($sql, array($txId, $session->moptIsAuthorized, $session->paymentReference,
                $session->moptOrderHash, $orderId));
        }

        if (Shopware()->Session()->moptPayment) {
            $this->saveTransactionPaymentData($orderId, Shopware()->Session()->moptPayment);
        }

        unset($session->moptIsAuthorized);
        unset($session->moptAgbChecked);

        $this->redirect(array('controller' => 'checkout', 'action' => 'finish', 'sUniqueID' => $moptPaymentReference));
    }

    /**
     * handle direct feedback
     * on success save order
     *
     * @param type $response 
     */
    protected function mopt_payone__handleDirectFeedback($response)
    {
        $session = Shopware()->Session();

        if ($response->getStatus() == 'ERROR') {
            $this->View()->errormessage = $this->moptPayoneMain->getPaymentHelper()
                    ->moptGetErrorMessageFromErrorCodeViaSnippet(false, $response->getErrorcode());
            $this->forward('error');
        } else {
            //extract possible clearing data
            $barzahlenCode = $this->moptPayoneMain->getPaymentHelper()->extractBarzahlenCodeFromResponse($response);
            if ($barzahlenCode) {
                $session->moptBarzahlenCode = $barzahlenCode;
            }

            //extract possible clearing data
            $clearingData = $this->moptPayoneMain->getPaymentHelper()->extractClearingDataFromResponse($response);

            if ($clearingData) {
                $session->moptClearingData = $clearingData;
            }

            if ($session->moptPaypalEcsWorkerId) {
                unset($session->moptPaypalEcsWorkerId);
            }

            if ($session->moptMandateData) {
                $session->moptMandateDataDownload = $session->moptMandateData['mopt_payone__mandateIdentification'];
                unset($session->moptMandateData);
            }
            
            //save order
            $this->forward('finishOrder', 'MoptPaymentPayone', null, array('txid' => $response->getTxid(),
                'hash' => $session->paymentReference));
        }
    }

    /**
     * handles redirect feedback
     * on success redirect customer to submitted(from Pay1) redirect url
     *
     * @param type $response 
     */
    protected function mopt_payone__handleRedirectFeedback($response)
    {
        if ($response->getStatus() == 'ERROR') {
            $this->View()->errormessage = $this->moptPayoneMain->getPaymentHelper()
                    ->moptGetErrorMessageFromErrorCodeViaSnippet(false, $response->getErrorcode());
            $this->forward('error');
        } else {
            $session = Shopware()->Session();
            $session->txId = $response->getTxid();
            $session->txStatus = $response->getStatus();
            $this->redirect($response->getRedirecturl());
        }
    }

    /**
     * prepare and do payment server api call
     *
     * @param array $config
     * @param string $clearingType
     * @param string $payment
     * @param bool|string $workerId
     * @param bool $isPaypalRecurring
     * @param bool $isPaypalRecurringInitialRequest
     * @param bool $forceAuthorize
     * @return type $response
     */
    protected function buildAndCallPayment($config, $clearingType, $payment, $workerId = false, $isPaypalRecurring = false, $isPaypalRecurringInitialRequest = false, $forceAuthorize = false)
    {
        $paramBuilder = $this->moptPayoneMain->getParamBuilder();
        $session = Shopware()->Session();

        //create hash
        $orderVariables = $session['sOrderVariables'];
        $orderHash = md5(serialize($orderVariables));
        $session->moptOrderHash = $orderHash;
        $user = $this->getUser();
        $paymentName = $user['additional']['payment']['name'];
        
        if (!$forceAuthorize && ($config['authorisationMethod'] == 'preAuthorise' 
                || $config['authorisationMethod'] == 'Vorautorisierung' 
                || $this->moptPayonePaymentHelper->isPayoneBarzahlen($paymentName) 
                || $isPaypalRecurringInitialRequest)) {
            $session->moptIsAuthorized = false;
        } else {
            $session->moptIsAuthorized = true;
        }
        
        $request = $this->mopt_payone__prepareRequest($config['paymentId'], $session->moptIsAuthorized);

        $request->setAmount($this->getAmount());
        $request->setCurrency($this->getCurrencyShortName());

        //get shopware temporary order id - session id
        $shopwareTemporaryId = $this->admin->sSYSTEM->sSESSION_ID;
        $paymentReference = $paramBuilder->getParamPaymentReference();

        $request->setReference($paymentReference);
        $transactionStatusPushCustomParam = 'session-' . Shopware()->Shop()->getId()
                . '|' . $this->admin->sSYSTEM->sSESSION_ID . '|' . $orderHash;
        $request->setParam($transactionStatusPushCustomParam);
        
        if ($workerId) {
            $request->setWorkorderId($workerId);
        }
        
        if ($isPaypalRecurring && $isPaypalRecurringInitialRequest) {
            $request->setAmount(0.01);
            $request->setRecurrence('recurring');
        }

        if ($isPaypalRecurring && !$isPaypalRecurringInitialRequest) {
            $request->setCustomerIsPresent('no');
            $request->setRecurrence('recurring');
        }

        $session->paymentReference = $paymentReference;
        $session->shopwareTemporaryId = $shopwareTemporaryId;

        $personalData = $paramBuilder->getPersonalData($this->getUserData());
        $request->setPersonalData($personalData);
        $deliveryData = $paramBuilder->getDeliveryData($this->getUserData());
        $request->setDeliveryData($deliveryData);

        $request->setClearingtype($clearingType);

        if (!$isPaypalRecurringInitialRequest && ($config['submitBasket'] || $clearingType === 'fnc')) {
            $request->setInvoicing($paramBuilder->getInvoicing($this->getBasket(), 
                    $this->getShipment(), $this->getUserData()));
        }

        if ($payment) {
            $request->setPayment($payment);
        }

        if (!$forceAuthorize && ($config['authorisationMethod'] == 'preAuthorise' || $config['authorisationMethod'] == 'Vorautorisierung' || $this->moptPayonePaymentHelper->isPayoneBarzahlen($paymentName) || $isPaypalRecurringInitialRequest)) {
            $response = $this->service->preauthorize($request);
        } else {
            $response = $this->service->authorize($request);
        }

        return $response;
    }

    /**
     * initialize and return request object for authorize/preauthorize api call
     *
     * @param string $paymentId
     * @param bool $isAuthorized
     * @return \Payone_Api_Request_Preauthorization
     */
    protected function mopt_payone__prepareRequest($paymentId = 0, $isAuthorized = false)
    {
        $params = $this->moptPayoneMain->getParamBuilder()->buildAuthorize($paymentId);
        $user = $this->getUser();
        $paymentName = $user['additional']['payment']['name'];
        if ($isAuthorized && !$this->moptPayonePaymentHelper->isPayoneBarzahlen($paymentName)) {
            $request = new Payone_Api_Request_Authorization($params);
            $this->service = $this->payoneServiceBuilder->buildServicePaymentAuthorize();
        } else {
            $request = new Payone_Api_Request_Preauthorization($params);
            $this->service = $this->payoneServiceBuilder->buildServicePaymentPreauthorize();
        }
        if ($this->moptPayonePaymentHelper->isPayoneBarzahlen($paymentName)) {
            $request->setCashType(Payone_Api_Enum_CashType::BARZAHLEN);
            $request->setApiVersion('3.10');
        }
        $this->service->getServiceProtocol()->addRepository(Shopware()->Models()->getRepository(
                        'Shopware\CustomModels\MoptPayoneApiLog\MoptPayoneApiLog'
        ));

        return $request;
    }

    /**
     * Get complete user-data as an array to use in view
     *
     * @return array
     */
    public function getUserData()
    {
        if ($this->isRecurringOrder()) {
            $orderVars = Shopware()->Session()->sOrderVariables;
            return $orderVars['sUserData'];
        }

        $system = Shopware()->System();
        $userData = $this->admin->sGetUserData();
        if (!empty($userData['additional']['countryShipping'])) {
            $sTaxFree = false;
            if (!empty($userData['additional']['countryShipping']['taxfree'])) {
                $sTaxFree = true;
            } elseif (
                    !empty($userData['additional']['countryShipping']['taxfree_ustid']) 
                    && !empty($userData['billingaddress']['ustid'])
            ) {
                $sTaxFree = true;
            }

            $system->sUSERGROUPDATA = Shopware()->Db()->fetchRow("
                SELECT * FROM s_core_customergroups
                WHERE groupkey = ?
            ", array($system->sUSERGROUP));

            if (!empty($sTaxFree)) {
                $system->sUSERGROUPDATA['tax'] = 0;
                $system->sCONFIG['sARTICLESOUTPUTNETTO'] = 1; //Old template
                Shopware()->Session()->sUserGroupData = $system->sUSERGROUPDATA;
                $userData['additional']['charge_vat'] = false;
                $userData['additional']['show_net'] = false;
                Shopware()->Session()->sOutputNet = true;
            } else {
                $userData['additional']['charge_vat'] = true;
                $userData['additional']['show_net'] = !empty($system->sUSERGROUPDATA['tax']);
                Shopware()->Session()->sOutputNet = empty($system->sUSERGROUPDATA['tax']);
            }
        }

        return $userData;
    }

    /**
     * get actual payment method id
     * 
     * @return string
     */
    protected function getPaymentId()
    {
        $user = $this->getUser();
        return $user['additional']['payment']['id'];
    }

    /**
     *  this action is called when sth. goes wrong during SEPA mandate PDF download
     */
    public function downloadErrorAction()
    {
        $this->View()->errormessage = Shopware()->Snippets()->getNamespace('frontend/MoptPaymentPayone/errorMessages')
                ->get('generalErrorMessage', 'Es ist ein Fehler aufgetreten');
    }

    /**
     * retrieve payment data
     * 
     * @return array
     */
    protected function getPaymentData()
    {
        $userId = $this->session->sUserId;

        if ($this->isRecurringOrder()) {
            $paymentData = Shopware()->Session()->moptPayment;
        } else {
            $sql = 'SELECT `moptPaymentData` FROM s_plugin_mopt_payone_payment_data WHERE userId = ?';
            $paymentData = unserialize(Shopware()->Db()->fetchOne($sql, $userId));
            Shopware()->Session()->moptPayment = $paymentData;
        }

        return $paymentData;
    }

    /**
     * Returns the full basket data as array
     *
     * @return array
     */
    public function getShipment()
    {
        $session = Shopware()->Session();
        
        if (!empty($session->sOrderVariables['sDispatch'])) {
            return $session->sOrderVariables['sDispatch'];
        } else {
            return null;
        }
    }

    /**
     * Recurring payment action method.
     */
    public function recurringAction()
    {
        if (!$this->getAmount() || $this->getOrderNumber()) {
            $this->redirect(array(
                'controller' => 'checkout'
            ));
            return;
        }

        $this->Front()->Plugins()->ViewRenderer()->setNoRender();

        $orderId = $this->Request()->getParam('orderId');
        Shopware()->Session()->moptPayment = $this->getPaymentDataFromOrder($orderId);

        if ($this->moptPayonePaymentHelper->isPayoneCreditcard($this->getPaymentShortName())) {
            Shopware()->Session()->moptOverwriteEcommerceMode = Payone_Api_Enum_Ecommercemode::INTERNET;
        }
        
        if ($this->moptPayonePaymentHelper->isPayonePaypal($this->getPaymentShortName())) {
            Shopware()->Session()->moptIsPaypalRecurringOrder = true;
        }
        
        if ($this->moptPayonePaymentHelper->isPayonePaydirekt($this->getPaymentShortName())) {
            Shopware()->Session()->moptIsPaydirektRecurringOrder = true;
        }        

        $action = 'mopt_payone__' . $this->moptPayonePaymentHelper
                        ->getActionFromPaymentName($this->getPaymentShortName());

        $response = $this->$action();
        $errorMessage = false;
        if ($response->isRedirect()) {
            $errorMessage = 'Tried to use redirect payment for abo order';
        }

        $session = Shopware()->Session();

        if ($response->getStatus() == 'ERROR') {
            $errorMessage = $response->getErrorcode();
        }

        if (!$errorMessage) {
            $clearingData = $this->moptPayoneMain->getPaymentHelper()->extractClearingDataFromResponse($response);
            $orderNr = $this->saveOrder($response->getTxid(), $session->paymentReference);

            $sql = 'SELECT `id` FROM `s_order` WHERE ordernumber = ?'; // get order id
            $orderId = Shopware()->Db()->fetchOne($sql, $orderNr);

            if ($clearingData) {
                $sql = 'UPDATE `s_order_attributes`' .
                        'SET mopt_payone_txid=?, mopt_payone_is_authorized=?, '
                        . 'mopt_payone_clearing_data=? WHERE orderID = ?';
                Shopware()->Db()->query($sql, array($response->getTxid(),
                    $session->moptIsAuthorized, json_encode($clearingData), $orderId));
            } else {
                $sql = 'UPDATE `s_order_attributes`' .
                        'SET mopt_payone_txid=?, mopt_payone_is_authorized=? WHERE orderID = ?';
                Shopware()->Db()->query($sql, array($response->getTxid(), $session->moptIsAuthorized, $orderId));
            }

            if (Shopware()->Session()->moptPayment) {
                $this->saveTransactionPaymentData($orderId, Shopware()->Session()->moptPayment);
            }

            unset($session->moptPayment);
            unset($session->moptIsAuthorized);
        }

        if ($this->Request()->isXmlHttpRequest()) {
            if ($errorMessage) {
                $data = array(
                    'success' => false,
                    'message' => $errorMessage
                );
            } else {
                $data = array(
                    'success' => true,
                    'data' => array(array(
                            'orderNumber' => $orderNr,
                            'transactionId' => $response->getTxid(),
                        ))
                );
            }
            echo Zend_Json::encode($data);
        } else {
            if ($errorMessage) {
                $this->View()->errormessage = $this->moptPayoneMain->getPaymentHelper()
                        ->moptGetErrorMessageFromErrorCodeViaSnippet(false, $response->getErrorcode());
                $this->forward('error');
            } else {
                $this->redirect(array(
                    'controller' => 'checkout',
                    'action' => 'finish',
                    'sUniqueID' => $session->paymentReference
                ));
            }
        }
    }

    /**
     * save payment data from actual transaction, used for abo commerce
     * 
     * @param string $orderId
     * @param array $paymentData
     */
    protected function saveTransactionPaymentData($orderId, $paymentData)
    {
        $sql = 'UPDATE `s_order_attributes` SET mopt_payone_payment_data=? WHERE orderID = ?';
        Shopware()->Db()->query($sql, array(serialize($paymentData), $orderId));
    }

    /**
     * get payment data from order, used for abo commerce
     * 
     * @param string $orderId
     * @return array
     */
    protected function getPaymentDataFromOrder($orderId)
    {
        $sql = 'SELECT `mopt_payone_payment_data` FROM `s_order_attributes` WHERE orderID = ?';
        $paymentData = Shopware()->Db()->fetchOne($sql, $orderId);

        return unserialize($paymentData);
    }

    /**
     * check if a recurring order is processed, used fpr abo commerce
     * 
     * @return bool
     */
    protected function isRecurringOrder()
    {
        return isset(Shopware()->Session()->isRecuringAboOrder);
    }

    /**
     * determine wether order is already finished
     * 
     * @param string $transactionId
     * @return boolean
     */
    protected function isOrderFinished($transactionId)
    {
        $sql = '
            SELECT ordernumber FROM s_order
            WHERE transactionID=? AND status!=-1';

        $orderNumber = Shopware()->Db()->fetchOne($sql, array($transactionId));

        if (empty($orderNumber)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * action for creditcard iframe
     */
    public function creditcardIframeAction()
    {
        $paramBuilder = $this->moptPayoneMain->getParamBuilder();

        $params = $paramBuilder->buildIframeParameters($this->getBasket(), $this->getShipment(), $this->getUserData());
        $txId = $params['reference'];

        // set txid and check if txid is already set to prevent reload errors
        if (!$this->moptSetTransactionIdForTempOrder($txId)) {
            return $this->redirect(array('controller' => 'checkout', 'action' => 'confirm'));
        }

        $iFrameUrlWithParams = 'https://secure.pay1.de/frontend/?' . http_build_query($params);
        $this->View()->gatewayUrl = $iFrameUrlWithParams;
    }

    /**
     * success url
     */
    public function creditcardIframeSuccessAction()
    {
        $txId = $this->Request()->getParam('reference');
        $sessionId = $this->admin->sSYSTEM->sSESSION_ID;

        //load temporary order
        $tempOrder = Shopware()->Models()->getRepository('Shopware\Models\Order\Order')
                ->findOneBy(array('transactionId' => $txId, 'temporaryId' => $sessionId));

        if (!$tempOrder) {
            //order already finished (push request) ?
            if (Shopware()->Models()->getRepository('Shopware\Models\Order\Order')->findOneBy(array(
                        'temporaryId' => $txId))) {
                return $this->redirect(array('controller' => 'checkout', 'action' => 'finish', 'sUniqueID' => $txId));
            }
            //error
            return $this->redirect(array('controller' => 'checkout', 'action' => 'confirm'));
        }

        //finalize order - new temporary/unique ID must not be the curent sessionID, since those orders are deleted in 
        //order-save process (sOrder::sDeleteTemporaryOrder) => create new ID
        $newId = $txId;
        $this->saveOrder($txId, $newId);
        return $this->redirect(array('controller' => 'checkout', 'action' => 'finish', 'sUniqueID' => $newId));
    }

    /**
     * check if TxId is already set and equals session var
     * 
     * @return boolean 
     */
    protected function moptSetTransactionIdForTempOrder($txId)
    {
        $sql = 'SELECT `transactionID` FROM `s_order` WHERE temporaryID = ?';
        $result = Shopware()->Db()->fetchOne($sql, $this->admin->sSYSTEM->sSESSION_ID);

        //not set
        if ($result === '') {
            //set transaction id
            $sql = 'UPDATE `s_order`' .
                    'SET transactionID=? WHERE temporaryID = ?';
            Shopware()->Db()->query($sql, array($txId, $this->admin->sSYSTEM->sSESSION_ID));
            Shopware()->Session()->paymentReference = $txId;
            return true;
        }

        if (isset(Shopware()->Session()->paymentReference) && $result === Shopware()->Session()->paymentReference) {
            return true;
        }

        return false;
    }

}