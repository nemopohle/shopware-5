{extends file="parent:backend/_base/layout.tpl"}

{block name="content/breadcrump"}
    <li>
        {$breadcrump.0}
    </li>
    <li>
        {$breadcrump.1}
    </li>
    <li class="active">
        <a href="{url controller="FcPayone" action="{$breadcrump.2}"}">{$breadcrump.3}</a> <span class="divider">/</span>
    </li> 
{/block}

{block name="content/main"}
    <div class="col-md-12">
        <h3>{s name=global-form/fieldset2}Wallet Einstellungen{/s}</h3>
        <div>
            Stellen Sie hier die Konfiguration zu den Zahlarten Paypal, Paypal ECS und Paydirekt ein.
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="btn-group">
                    <button id="paymentmethodsdropdown" type="button" class="btn-payone-fixed btn-payone btn dropdown-toggle" data-toggle="dropdown">
                        <span class="selection">{s name=paymentMethod/label}Gilt für Zahlart:{/s}</span><span class="caret"></span>
                    </button>
                    <ul class="dropdown-menu" role="menu">
                        {foreach from=$payonepaymentmethods item=paymentmethod}
                            <li><a href="#" id="{$paymentmethod.id}">{$paymentmethod.description}</a></li>
                            {/foreach}   
                    </ul>
                </div>
            </div>
        </div>
        <div class='col-md-12'>
            <form role="form" id="ajaxwalletform" class="form-horizontal">
                <div class="form-group has-feedback has-error  menu-level-standard  menu-level-experte">

                    <label for="description" class="text-left col-md-3 control-label">{s name=formpanel_description_label}Bezeichnung{/s}</label>
                    <div class="col-md-6">
                        <input type="text" class="form-control " pattern='^[_ .()+-?,:;"!@#$%^&*ÄÖÜäöüa-zA-Z0-9]*' minlength="1" maxlength="200" id="description" name="description" aria-describedby="description-status" >
                        <span class="glyphicon form-control-feedback glyphicon-remove" aria-hidden="true"></span>
                        <span id="description-status" class="sr-only">(success)</span>
                        <div class="help-block with-errors"></div>
                    </div>
                </div>
                <div class="form-group has-feedback has-error  menu-level-standard  menu-level-experte">

                    <label for="additionalDescription" class="text-left col-md-3 control-label">{s name=formpanel_additional-description_label}Zusätzliche Beschreibung{/s}</label>
                    <div class="col-md-6">
                        <textarea rows="3" class="form-control " pattern='^[_ .()+-?,:;"!@#$%^&*ÄÖÜäöüa-zA-Z0-9]*' minlength="1" maxlength="200" id="additionalDescription" name="additionalDescription" aria-describedby="additionalDescription-status" >
                        </textarea>
                        <span class="glyphicon form-control-feedback glyphicon-remove" aria-hidden="true"></span>
                        <span id="additionalDescription-status" class="sr-only">(success)</span>
                        <div class="help-block with-errors"></div>
                    </div>
                </div>  
                <div class="form-group has-feedback has-error  menu-level-standard  menu-level-experte">

                    <label for="debitPercent" class="text-left col-md-3 control-label">{s name=formpanel_surcharge_label}Aufschlag/Abschlag (in %){/s}</label>
                    <div class="col-md-6">
                        <input type="text" class="form-control " pattern='^[,.0-9]*' minlength="1" maxlength="200" id="debitPercent" name="debitPercent" aria-describedby="debitPercent-status" >
                        <span class="glyphicon form-control-feedback glyphicon-remove" aria-hidden="true"></span>
                        <span id="debitPercent-status" class="sr-only">(success)</span>
                        <div class="help-block with-errors"></div>
                    </div>
                </div>
                <div class="form-group has-feedback has-error  menu-level-standard  menu-level-experte">

                    <label for="surcharge" class="text-left col-md-3 control-label">{s name=formpanel_generalSurcharge_label}Pauschaler Aufschlag{/s}</label>
                    <div class="col-md-6">
                        <input type="text" class="form-control " pattern='^[,.0-9]*' minlength="1" maxlength="200" id="surcharge" name="surcharge" aria-describedby="surcharge-status" >
                        <span class="glyphicon form-control-feedback glyphicon-remove" aria-hidden="true"></span>
                        <span id="surcharge-status" class="sr-only">(success)</span>
                        <div class="help-block with-errors"></div>
                    </div>
                </div>
                <div class="form-group has-feedback has-error  menu-level-standard  menu-level-experte">

                    <label for="position" class="text-left col-md-3 control-label">{s name=formpanel_position_surcharge}Position{/s}</label>
                    <div class="col-md-6">
                        <input type="text" class="form-control " pattern='^[0-9]*' minlength="1" maxlength="200" id="position" name="position" aria-describedby="position-status" >
                        <span class="glyphicon form-control-feedback glyphicon-remove" aria-hidden="true"></span>
                        <span id="position-status" class="sr-only">(success)</span>
                        <div class="help-block with-errors"></div>
                    </div>
                </div>
                <div class="form-group has-feedback has-error  menu-level-standard  menu-level-experte">

                    <label for="active" class="text-left col-md-3 control-label">{s name=formpanel_active_label}Aktiv{/s}</label>
                    <div class="col-md-6">
                        <input type="checkbox" class="form-control " pattern='^[_ .()+-?,:;"!@#$%^&*ÄÖÜäöüa-zA-Z0-9]*' minlength="1" maxlength="200" id="active" name="active" aria-describedby="active-status" >
                        <span class="glyphicon form-control-feedback glyphicon-remove" aria-hidden="true"></span>
                        <span id="active-status" class="sr-only">(success)</span>
                        <div class="help-block with-errors"></div>
                    </div>
                </div>
                <div class="form-group has-feedback has-error menu-level-experte">

                    <label for="esdActive" class="text-left col-md-3 control-label">{s name=formpanel_esdActive_label}Aktiv für ESD-Produkte{/s}</label>
                    <div class="col-md-6">
                        <input type="checkbox" class="form-control " pattern='^[_ .()+-?,:;"!@#$%^&*ÄÖÜäöüa-zA-Z0-9]*' minlength="1" maxlength="200" id="esdActive" name="esdActive" aria-describedby="esdActive-status" >
                        <span class="glyphicon form-control-feedback glyphicon-remove" aria-hidden="true"></span>
                        <span id="esdActive-status" class="sr-only">(success)</span>
                        <div class="help-block with-errors"></div>
                    </div>
                </div>
                <div class="form-group has-feedback has-error menu-level-experte">

                    <label for="mobileInactive" class="text-left col-md-3 control-label">{s name=formpanel_mobileInactive_label}Inaktiv für Smartphone{/s}</label>
                    <div class="col-md-6">
                        <input type="checkbox" class="form-control " pattern='^[_ .()+-?,:;"!@#$%^&*ÄÖÜäöüa-zA-Z0-9]*' minlength="1" maxlength="200" id="mobileInactive" name="mobileInactive" aria-describedby="mobileInactive-status" >
                        <span class="glyphicon form-control-feedback glyphicon-remove" aria-hidden="true"></span>
                        <span id="mobileInactive-status" class="sr-only">(success)</span>
                        <div class="help-block with-errors"></div>
                    </div>
                </div>                
                <div id="paypalecs" class="form-group has-feedback has-error  menu-level-standard  menu-level-experte" >

                    <label for="paypalEcsActive" class="text-left col-md-3 control-label">{s name=fieldlabel/paypalEcsActive}PayPal ECS Button auf Warenkorbseite anzeigen?{/s}</label>
                    <div class="col-md-6">
                        <select class="form-control " pattern='^[_ .()+-?,:;"!@#$%^&*ÄÖÜäöüa-zA-Z0-9]*' minlength="1" maxlength="200" id="paypalEcsActive" name="paypalEcsActive" aria-describedby="paypalEcsActive-status">
                            <option value="true">Ja</option>
                            <option value="false">Nein</option>
                        </select>  
                        <span class="glyphicon form-control-feedback glyphicon-remove" aria-hidden="true"></span>
                        <span id="paypalEcsActive-status" class="sr-only">(success)</span>
                        <div class="help-block with-errors"></div>
                    </div>
                </div>                    
                <button type="submit" class="btn-payone btn " >{s name=global-form/button}Speichern{/s}</button>
            </form>
        </div>
       
        <a style="font-size: 28px" href="#" data-toggle="collapse" data-target="#payonetable">Konfiguration PayPal ECS Logos</a>
        <div id="payonetable" class="collapse">
            <form role="form" id="ajaxpaypalecs" enctype="multipart/form-data">
                <table class="table-condensed">
                    <tr>
                        <th>Sprache</th>
                        <th>Logo</th>
                        <th>Hochladen</th>
                        <th>Standard</th>
                    </tr>
                    <tr class="form-group">
                        <td><select name=localeId" id="localeId" style="max-width:125px;" class="form-control">
                                <option value ="1" >Deutsch</option>
                                <option value ="2" >Englisch</option>
                            </select></td>
                        <td><output id="list"></output></td>
                        <td><input type="file" id="files" name="files"></td>
                        <td><input name="isDefault" id="isDefault" type="checkbox"  class="form-control"></td>
                    </tr>
                </table>
                <input name="image" id="image" type="hidden">
                <button type="submit" class="btn-payone btn " >{s name=global-form/button}Speichern{/s}</button>
            </form>                
        </div>                
    </div>
{/block}

{block name="resources/javascript" append}  
    <script type="text/javascript" src="{link file="backend/_resources/js/formhelper.js"}"></script>

    <script type="text/javascript">

        var form = $('#ajaxwalletform');
        var iframeform = $('#ajaxpaypalecs');
        var url = "{url controller=FcPayone action=ajaxgetWalletConfig forceSecure}";
        var iframeurl = "{url controller=FcPayone action=ajaxgetPaypalConfig forceSecure}";
        var paymentid = null;
        var imagelink = null;

        $(document).ready(function ()
        {
            var params = "paymentid=0";
            var call = url + '?' + params;
            var iframecall = iframeurl;
            var iframedata = "";

            form.validator('validate');
            if (paymentid != 21) {
                $('#paypalecs').hide();
            } else {
                $('#paypalecs').show();
            }

            $.ajax({
                url: call,
                type: 'POST',
                success: function (data) {
                    response = $.parseJSON(data);
                    if (response.status === 'success') {
                        populateForm(form, response.data);
                        form.validator('validate');
                    }
                    if (response.status === 'error') {
                    }
                }
            });

            $.ajax({
                url: iframecall,
                type: 'POST',
                success: function (iframedata) {
                    response = $.parseJSON(iframedata);
                    if (response.status === 'success') {
                        populateForm(iframeform, response.iframedata);
                    }
                    if (response.status === 'error') {
                    }
                    imagelink = response.iframedata.image;
                    changeImage(imagelink);
                }
            });

        });

        $(".dropdown-menu li a").click(function () {
            var params = "paymentid=" + this.id;
            var call = url + '?' + params;
            paymentid = this.id;

            $.ajax({
                url: call,
                type: 'POST',
                success: function (data) {
                    response = $.parseJSON(data);
                    if (response.status === 'success') {
                        populateForm(form, response.data);

                        form.validator('validate');
                        if (paymentid != 21) {
                            $('#paypalecs').hide();
                        } else {
                            $('#paypalecs').show();
                        }
                    }
                    if (response.status === 'error') {
                    }
                }
            });
        });

        form.on("submit", function (event) {
            event.preventDefault();
            var checkboxes = form.find('input[type="checkbox"]');
            $.each(checkboxes, function (key, value) {
                if (value.checked === false) {
                    value.value = 0;
                } else {
                    value.value = 1;
                }
                $(value).attr('type', 'hidden');
            });
            values = form.serialize();
            $.each(checkboxes, function (key, value) {
                $(value).attr('type', 'checkbox');
            });
            var url = 'ajaxSavePaymentConfig';
            values = values + '&paymentId=' + paymentid;
            $.post(url, values, function (response) {
                var data_array = $.parseJSON(response);
                showalert("Die Daten wurden gespeichert", "alert-success");
            });

        });

        iframeform.on("submit", function (event) {
            event.preventDefault();
            var checkboxes = iframeform.find('input[type="checkbox"]');
            $.each(checkboxes, function (key, value) {
                if (value.checked === false) {
                    value.value = 0;
                } else {
                    value.value = 1;
                }
                $(value).attr('type', 'hidden');
            });
            iframevalues = iframeform.serialize();
            $.each(checkboxes, function (key, value) {
                $(value).attr('type', 'checkbox');
            });
            var url = 'ajaxSavePaypalConfig';
            iframevalues = iframevalues + '&paymentId=' + paymentid;
            $.post(url, iframevalues, function (response) {
                var data_array = $.parseJSON(response);
                showalert("Die Daten wurden gespeichert", "alert-success");
            });

        });
        function handleFileSelect(evt) {
            var files = evt.target.files; // FileList object

            // Loop through the FileList and render image files as thumbnails.
            for (var i = 0, f; f = files[i]; i++) {

                // Only process image files.
                if (!f.type.match('image.*')) {
                    continue;
                }

                var reader = new FileReader();

                // Closure to capture the file information.
                reader.onload = (function (theFile) {
                    return function (e) {
                        var out = ['<img class="thumb" src="', e.target.result,
                            '" />'].join('');
                        $("#list").html(out);
                    };
                })(f);

                // Read in the image file as a data URL.
                reader.readAsDataURL(f);
            }
        }

        function changeImage(a) {
            var out = ['<img class="thumb" src="', a,
                '" />'].join('');
            $("#list").html(out);
        }

        document.getElementById('files').addEventListener('change', handleFileSelect, false);
    </script>
{/block}
