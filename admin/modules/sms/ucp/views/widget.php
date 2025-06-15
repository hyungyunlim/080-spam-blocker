<div id="sms-toolbar-<?php echo $did?>">
	<button class="btn btn-default start-conversation" data-did="<?php echo $did?>">
		<i class="fa fa-share"></i> <span><?php echo _('Start Conversation')?></span>
	</button>
	<button class="btn btn-danger delete-selection" disabled>
		<i class="fa fa-remove"></i> <span><?php echo _('Delete')?></span>
	</button>
</div>
<table id="sms-grid-<?php echo $did?>"
			data-url="ajax.php?module=sms&amp;command=grid&amp;did=<?php echo $did?>"
			data-toolbar="#sms-toolbar-<?php echo $did?>"
			data-cache="false"
			data-cookie="true"
			data-cookie-id-table="ucp-sms-<?php echo $did?>-table"
			data-maintain-selected="true"
			data-show-columns="true"
			data-show-toggle="true"
			data-toggle="table"
			data-pagination="true"
			data-search="true"
			data-sort-order="desc"
			data-sort-name="utime"
			data-side-pagination="server"
			data-show-refresh="true"
			data-silent-sort="false"
			data-mobile-responsive="true"
			data-check-on-init="true"
			data-min-width="992"
			class="table table-hover">
	<thead>
				<tr class="sms-header">
					<th data-checkbox="true"></th>
					<th data-field="utime" data-sortable="true" data-formatter="UCP.Modules.Sms.dateFormatter"><?php echo _("Last Message Date")?></th>
					<th data-field="recipient" data-formatter="UCP.Modules.Sms.toFormatter"><?php echo _("To")?></th>
					<th data-field="controls" data-formatter="UCP.Modules.Sms.actionFormatter"><?php echo _("Controls")?></th>
			</tr>
	</thead>
</table>
<div class="hidden sms-detail-table-container">
	<table class="sms-detail-table table"
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
			<th data-field="body" data-sortable="true" data-formatter="UCP.Modules.Sms.bodyFormatter" style="width: 201px;"><?php echo _("Body")?></th>
		</thead>
	</table>
</div>
