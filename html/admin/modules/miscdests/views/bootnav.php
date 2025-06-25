<div id="toolbar-md">
<a href="config.php?display=miscdests" class="btn btn-default"><i class="fa fa-list"></i>&nbsp; <?php echo _("List Misc Destinations") ?></a>
<a href="config.php?display=miscdests&view=form" class="btn-default btn" ><i class="fa fa-plus"></i>&nbsp; <?php echo _("Add Misc Destination") ?></a>
</div>
<table data-url="ajax.php?module=miscdests&amp;command=getJSON&amp;jdata=grid" data-escape="true" data-cache="false" data-toggle="table" data-toolbar="#toolbar-md" data-search="true" class="table" id="table-all-side">
    <thead>
        <tr>
            <th data-sortable="true" data-field="description"><?php echo _('Extension')?></th>
        </tr>
    </thead>
</table>
<script type="text/javascript">
	function rlformatter(v,r){
		return '<a href="?display=miscdests&view=form&extdisplay='+r['id']+'">'+v+'</a>';
	}
  $("#table-all-side").on('click-row.bs.table',function(e,row,elem){
  window.location = '?display=miscdests&view=form&extdisplay='+row['id'];
})
</script>
