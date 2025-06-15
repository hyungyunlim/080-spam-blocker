<div id="toolbar-ftpgrid">
  <a href='?display=filestore&driver=FTP&view=form' class='btn btn-default'><i class="fa fa-plus"></i>&nbsp;<?php echo _("Add FTP Instance")?></a>
</div>
<table id="ftpgrid"
    data-url="ajax.php?module=filestore&driver=FTP&command=grid"
    data-cache="false"
    data-cookie="true"
    data-cookie-id-table="ftpgrid"
    data-toolbar="#toolbar-ftpgrid"
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
      <th data-field="name"><?php echo _("Name")?></th>
      <th data-field="desc"><?php echo _("Description")?></th>
      <th data-field="enabled" data-formatter="GridEnabledFormatter" class="col_enabled"><?php echo _("Enabled")?></th>
      <th data-field="id" data-formatter="FTPLinkFormatter" class="col_actions"><?php echo _("Actions")?></th>
    </tr>
  </thead>
</table>
<script>
function FTPLinkFormatter(value, row, index){
    var html = '<a href="?display=filestore&driver=FTP&view=form&id='+value+'"><i class="fa fa-pencil"></i></a>';
    html += '&nbsp;<a href="?display=filestore&driver=FTP&action=delete&id='+value+'" class="delAction"><i class="fa fa-trash-o"></i></a>';
    return html;
}
</script>
