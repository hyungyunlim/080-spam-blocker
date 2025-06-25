<?php if( empty($hotelwakeup->getLanguages())) { ?>
	<div class="alert alert-danger">
		<?php echo sprintf(_("You have no sound packages installed in the %s module. Please install at least one language to use System Recordings"),"<a href='?display=soundlang'>"._("Sound Languages")."</a>")?>
	</div>
<?php } ?>

<div id="toolbar-all">
	<h2><i class="fa fa-language">&nbsp;</i> <?php echo _("Message Settings") ?></h2>
</div>
<table id="message_lang" class="table table-striped"
	   data-url="ajax.php?module=hotelwakeup&amp;command=gettablemessagelang"
       data-cache="false"
       data-toolbar="#toolbar-all"
	   data-maintain-selected="true"
	   data-show-columns="false"
	   data-show-toggle="true"
	   data-toggle="table"
	   data-pagination="true"
	   data-search="true"
	   data-show-refresh="true">
    <thead>
        <tr>
            <th data-field="language"><?php echo _("Language")?></th>
            <th data-field="description"><?php echo _("Description")?></th>
            <th data-field="action"><?php echo _("Action")?></th>
        </tr>
    </thead>
</table>