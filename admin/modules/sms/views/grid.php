<div id="toolbar-all">
    <button id="blkDelete" class="btn btn-danger"><?php echo _("Delete Selected") ?></button>
    <a href="#" class="btn btn-default" data-toggle="modal" data-target="#webHookForm"><i class="fa fa-plus"></i>&nbsp;&nbsp;<?php echo _("Add New Web Hook") ?></a>
</div>
<table id="blGrid" data-escape="true" data-toolbar="#toolbar-all" data-url="ajax.php?module=sms&command=getJSON&jdata=grid" data-cache="false" data-maintain-selected="true" data-show-columns="true" data-show-toggle="true" data-toggle="table" data-pagination="true" data-search="true" class="table table-striped">
    <thead>
        <tr>
            <th data-checkbox="true" data-formatter="cbFormatter"></th>
            <th data-field="webhookUrl"><?php echo _("Web Hook Url") ?></th>
            <th data-field="enablewebHook" data-formatter="formatter"><?php echo _("Status") ?></th>
            <th data-field="dataToBeSentOn"><?php echo _("SMS data to be sent on") ?></th>
            <th data-formatter="linkFormatter"><?php echo _("Actions") ?></th>
        </tr>
    </thead>
</table>


<script type="text/javascript">
    var cbrows = [];

    function cbFormatter(val, row, i) {
        cbrows[i] = row['id'];
    }

    function linkFormatter(value, row, idx) {
        var html = '<a href="#" data-toggle="modal" data-target="#webHookForm" data-id="' + row['id'] + '" data-webhookUrl="' + row['webhookUrl'] + '" data-enablewebHook="' + row['enablewebHook'] + '" data-dataToBeSentOn="' + row['dataToBeSentOn'] + '" ><i class="fa fa-pencil"></i></a>';
        html += '&nbsp;<a href="#" data-id="' + row['id'] + '" id="del" data-idx="' + idx + '" ><i class="fa fa-trash"></i></a>';
        return html;
    }

    function formatter(value, row) {
        if (value == 1) {
            return "Enabled";
        } else {
            return "Disabled";
        }
    }
</script>