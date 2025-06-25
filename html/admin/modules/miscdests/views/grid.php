<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2015 Sangoma Technologies.

 $dataurl = "ajax.php?module=miscdests&command=getJSON&jdata=grid";
?>
<div id="toolbar-all">
	<a href="config.php?display=miscdests&amp;view=form" class="btn btn-default"><i class="fa fa-plus"></i>&nbsp; <?php echo _("Add Misc Destination") ?></a>
</div>
<table id="miscdestgrid" data-escape="true" data-toolbar="#toolbar-all" data-maintain-selected="true" data-url="<?php echo $dataurl?>" data-show-columns="true" data-show-toggle="true" data-toggle="table" data-pagination="true" data-search="true" class="table table-striped">
	<thead>
		<th data-field='description'><?php echo _("Destination")?></th>
		<th data-field='id' data-formatter="linkFormatter"><?php echo _("Actions")?></th>
	</thead>
</table>

<script>

function linkFormatter(value, row, index){
    var html = '<a href="?display=miscdests&view=form&extdisplay='+value+'"><i class="fa fa-edit"></i></a>';
    html += '&nbsp;<a href="?display=miscdests&action=delete&extdisplay='+value+'" class="delAction"><i class="fa fa-trash"></i></a>';
    return html;
}
</script>
