<div id="toolbar-sshgrid">
  <a href='?display=filestore&driver=SSH&view=form' class='btn btn-default'><i class="fa fa-plus"></i>&nbsp;<?php echo _("Add SSH Server")?></a>
</div>
<table id="sshgrid"
    data-url="ajax.php?module=filestore&driver=SSH&command=grid"
    data-cache="false"
    data-cookie="true"
    data-cookie-id-table="sshgrid"
    data-toolbar="#toolbar-sshgrid"
    data-maintain-selected="true"
    data-show-columns="true"
    data-show-toggle="true"
    data-toggle="table"
    data-pagination="true"
    data-search="true"
    data-show-refresh="true"
    class="table table-striped">
  <thead>
    <tr>
      <th data-field="name"><?php echo _("Server")?></th>
      <th data-field="desc"><?php echo _("Description")?></th>
      <th data-field="enabled" data-formatter="GridEnabledFormatter" class="col_enabled"><?php echo _("Enabled")?></th>
      <th data-field="id" data-formatter="SSHLinkFormatter" class="col_actions"><?php echo _("Actions")?></th>
    </tr>
  </thead>
</table>
<script>
function SSHLinkFormatter(value, row, index){
    var html = '<a href="?display=filestore&driver=SSH&view=form&id='+value+'"><i class="fa fa-pencil"></i></a>';
    html += '&nbsp;<a href="?display=filestore&driver=SSH&action=delete&id='+value+'" class="delAction"><i class="fa fa-trash-o"></i></a>';
    return html;
}
</script>
