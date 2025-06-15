<div class="col-md-12">
	<?php if(!empty($message)) { ?>
		<div class="alert alert-<?php echo $message['type']?>"><?php echo $message['message']?></div>
	<?php } ?>
	<table id="sms-grid"
        data-url="index.php?quietmode=1&amp;module=sms&amp;command=grid"
        data-cache="false"
        data-cookie="true"
        data-cookie-id-table="ucp-sms-table"
        data-maintain-selected="true"
        data-show-columns="true"
        data-show-toggle="true"
        data-toggle="table"
        data-pagination="true"
        data-search="true"
				data-sort-order="desc"
				data-sort-name="timestamp"
				data-side-pagination="server"
				data-show-refresh="true"
				data-silent-sort="false"
				data-mobile-responsive="true"
				data-check-on-init="true"
				data-min-width="992"
        class="table table-hover">
    <thead>
            <tr class="sms-header">
            <th data-field="timestamp" data-sortable="true" data-formatter="UCP.Modules.Sms.dateFormatter"><?php echo _("Last Message Date")?></th>
            <th data-field="localdid" data-sortable="true"><?php echo _("DID")?></th>
						<th data-field="remotedid" data-sortable="true" data-formatter="UCP.Modules.Sms.toFormatter"><?php echo _("To")?></th>
						<th data-field="controls" data-formatter="UCP.Modules.Sms.actionFormatter"><?php echo _("Controls")?></th>
        </tr>
    </thead>
</table>
	<div class="modal fade" id="smspreview" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="myModalLabel"><?php echo sprintf(_("Conversation Detail with %s"),"<span id='cnam'></span>")?></h4>
				</div>
				<div class="modal-body">
					<table id="sms-detail-grid"
						data-cookie="true"
						data-cookie-id-table="ucp-sms-detail-table"
						data-show-columns="true"
		        data-show-toggle="true"
		        data-toggle="table"
		        data-pagination="true"
		        data-search="true"
						data-sort-order="desc"
						data-sort-name="utime"
						data-mobile-responsive="true"
						data-check-on-init="true"
						data-min-width="992"
						>
						<thead>
							<th data-field="utime" data-sortable="true" data-formatter="UCP.Modules.Sms.dateFormatter"><?php echo _("Time")?></th>
							<th data-field="direction" data-sortable="true" data-formatter="UCP.Modules.Sms.directionFormatter"><?php echo _("Direction")?></th>
							<th data-field="body" data-sortable="true" data-formatter="UCP.Modules.Sms.bodyFormatter"><?php echo _("Body")?></th>
						</thead>
					</table>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _("Close")?></button>
				</div>
			</div>
		</div>
	</div>
</div>
