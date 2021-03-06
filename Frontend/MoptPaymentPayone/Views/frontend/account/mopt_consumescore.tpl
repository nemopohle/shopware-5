{extends file="frontend/account/payment.tpl"}

{block name="frontend_index_header_javascript_jquery" append}
    <script src="{link file='frontend/_resources/javascript/client_api.js'}"></script>
    <script src="{link file='frontend/_resources/javascript/mopt_payment.js'}"></script>
    <script src="{link file='frontend/_resources/javascript/mopt_account.js'}"></script>
{/block}

{block name="frontend_index_content" append}
    <input name="moptConsumerScoreCheckNeedsUserAgreement" type="hidden" 
           data-moptConsumerScoreCheckNeedsUserAgreement="{$moptConsumerScoreCheckNeedsUserAgreement}" 
           data-moptConsumerScoreCheckNeedsUserAgreementUrl="{url controller=moptAjaxPayone action=ajaxGetConsumerScoreUserAgreement forceSecure}" 
               id="moptConsumerScoreCheckNeedsUserAgreement"/>
{/block}