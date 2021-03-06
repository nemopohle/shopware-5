{extends file="frontend/checkout/finish.tpl"}

{block name="frontend_index_header_css_screen" append}
   <link rel="stylesheet" type="text/css" href="{link file="frontend/_resources/styles/barzahlen.css"}" />
{/block}

{block name="frontend_checkout_finish_teaser_actions" append}
    {if $moptPaymentConfigParams.moptMandateDownloadEnabled}
        <p class="teaser--actions">
            {strip}
                <a href="{url controller=moptAjaxPayone action=downloadMandate forceSecure}" 
                   class="btn is--primary teaser--btn-print" 
                   target="_blank" 
                   title="{"{s name='mandateDownload' namespace='frontend/MoptPaymentPayone/payment'}Download Mandat{/s}"|escape}">
                    {s name='mandateDownload' namespace='frontend/MoptPaymentPayone/payment'}Download Mandat{/s}
                </a>
            {/strip}
        </p>
    {/if}
    {if $moptBarzahlenCode}
        <div class="barzahlencode">
        {$moptBarzahlenCode}
        </div>
    {/if}
{/block}