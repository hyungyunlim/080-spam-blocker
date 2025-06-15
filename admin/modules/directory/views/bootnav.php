<div id="toolbar-dirbootnav">
	<a href="?display=directory&amp;view=form" class="btn btn-default"><i class="fa fa-plus"></i>&nbsp;<?php echo _("Add Directory")?></a>
	<a href="config.php?display=directory" class="btn btn-default"><i class="fa fa-list"></i>&nbsp; <?php echo _("List Directories") ?></a>
</div>
<table id="dirgridrnav"
 data-url="ajax.php?module=directory&amp;command=getJSON&amp;jdata=grid"
 data-cache="false"
 data-toggle="table"
 data-search="true"
 data-toolbar="#toolbar-dirbootnav"
 class="table">
	<thead>
			<tr>
			<th data-field="name"><?php echo _("Directory")?></th>
		</tr>
	</thead>
</table>
<script type="text/javascript">
	$("#dirgridrnav").on('click-row.bs.table',function(e,row,elem){
		window.location = '?display=directory&view=form&id='+row['id'];
	});
</script>
