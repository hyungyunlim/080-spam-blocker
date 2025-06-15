<!--Add number Modal -->
<div class="modal fade" id="webHookForm" tabindex="-1" role="dialog" aria-labelledby="webHookForm" aria-hidden="true">
    <div class="modal-dialog display modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="webHookForm"><?php echo _("Add or Edit Web Hook") ?></h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="id" value="" />
                <!--Web Hook Enabled-->
                <div class="element-container" style="margin-top: 1rem;">
                    <div class="row">
                        <div class="form-group">
                            <div class="col-md-4 col-lg-4 col-sm-12">
                                <label class="control-label" for="enablewebHook"><?php echo _("Web Hook Enabled") ?></label>
                                <i class="fa fa-question-circle fpbx-help-icon" data-for="enableHook"></i>
                            </div>
                            <div class="col-md-8 col-lg-8 col-sm-12">
                                <span class="radioset">
                                    <input type="radio" name="enablewebHook" id="enablewebHookyes" value="1">
                                    <label for="enablewebHookyes"><?php echo _("Yes"); ?></label>
                                    <input type="radio" name="enablewebHook" id="enablewebHookno" value="0">
                                    <label for="enablewebHookno"><?php echo _("No"); ?></label>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <span id="enableHook" class="help-block fpbx-help-block general-find"><?php echo _('Enable this web hook') ?></span>
                        </div>
                    </div>
                </div>
                <!--Web Hook Enabled-->
                <div class="element-container" style="margin-top: 1rem;">
                    <div class="row">
                        <div class="form-group">
                            <div class="col-md-4 col-lg-4 col-sm-12">
                                <label for="webHookBaseurl" class="control-label"><?php echo _("Webhook Base URL") ?></label>
                                <i class="fa fa-question-circle fpbx-help-icon" data-for="webHookUrl"></i>
                            </div>
                            <div class="col-md-8 col-lg-8 col-sm-12">
                                <input type="url" name="webHookBaseurl" class="form-control " id="webHookBaseurl" size="35" tabindex="" value="">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <span id="webHookUrl" class="help-block fpbx-help-block general-find"><?php echo _('The url for which sms data has to be sent.') ?></span>
                        </div>
                    </div>
                </div>
                <!--Send WebHooks-->
                <div class="element-container" style="margin-top: 1rem;">
                    <div class="row">
                        <div class="form-group">
                            <div class="col-md-4 col-lg-4 col-sm-12">
                                <label class="control-label" for="webhook"><?php echo _("When data has to be sent ?") ?></label>
                                <i class="fa fa-question-circle fpbx-help-icon" data-for="sendData"></i>
                            </div>
                            <div class="col-md-8 col-lg-8 col-sm-12">
                                <span class="radioset">
                                    <input type="radio" name="dataToBeSentOn" id="webhooksend" value="send">
                                    <label for="webhooksend"><?php echo _("Send"); ?></label>
                                    <input type="radio" name="dataToBeSentOn" id="webhookreceive" value="receive">
                                    <label for="webhookreceive"><?php echo _("Receive"); ?></label>
                                    <input type="radio" name="dataToBeSentOn" id="webhookboth" value="both">
                                    <label for="webhookboth"><?php echo _("Send and Receive"); ?></label>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <span id="sendData" class="help-block fpbx-help-block general-find"><?php echo _('When the data has to sent to web hook url. On sending message , On receiving mesage, Or Both') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _("Close") ?></button>
                <button type="button" class="btn btn-primary" id="submitForm"><?php echo _("Save changes") ?></button>
            </div>
        </div>
    </div>
</div>