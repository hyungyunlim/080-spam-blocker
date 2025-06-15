<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2015 Sangoma Technologies.
?>
<div id="toolbar-all">
	<a href="?display=directory&amp;view=form" class="btn btn-default"><i class="fa fa-plus"></i>&nbsp;<?php echo _("Add Directory")?></a>
</div>

<table id="dirgrid"
			data-url="ajax.php?module=directory&amp;command=getJSON&amp;jdata=grid"
			data-cache="false"
			data-toggle="table"
			data-search="true"
			data-pagination="true"
			data-toolbar="#toolbar-all"
			class="table table-striped">
	<thead>
			<tr>
			<th data-field="name"><?php echo _("Directory")?></th>
			<th data-field="default" data-formatter="defaultFormatter"><?php echo _("Default Directory")?></th>
			<th data-field="link" data-formatter="linkFormatter"><?php echo _("Actions")?></th>
		</tr>
	</thead>
</table>
