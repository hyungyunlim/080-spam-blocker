<?php
$disabled = (isset($readonly) && !empty($readonly))?' disabled ':'';
$id = isset($_GET['id'])?$_GET['id']:'';
?>
<div class="container-fluid">
	<h1><?php echo _('Local Directory')?></h1>
	<div class="alert alert-info">
		<p><h3><?php echo _("Paths supports parameter substitution such as the examples below")?></h3></p>
		<ul class='fa-ul'>
			<li><i class="fa-li fa fa-folder-o"></i>'__ASTAGIDIR__' = <?php echo _('AGI directory'); ?></li>
			<li><i class="fa-li fa fa-folder-o"></i>'__ASTVARLIBDIR__' = <?php echo _('lib directory'); ?></li>
			<li><i class="fa-li fa fa-folder-o"></i>'__ASTETCDIR__' = <?php echo _('etc directory'); ?></li>
			<li><i class="fa-li fa fa-folder-o"></i>'__ASTLOGDIR__' = <?php echo _('log direcrory'); ?></li>
			<li><i class="fa-li fa fa-folder-o"></i>'__ASTSPOOLDIR__' = <?php echo _('spool directory'); ?></li>
			<li><i class="fa-li fa fa-folder-o"></i>'__AMPWEBROOT__' = <?php echo _('Webroot'); ?></li>
		</ul>

	</div>
	<div class = "display full-border">
		<div class="row">
			<div class="col-sm-12">
				<div class="fpbx-container">
					<div class="display full-border">
						<form class="fpbx-submit" action="?display=filestore" method="post" id="server_form" name="server_form" data-fpbx-delete="?display=filestore&action=delete&id=<?php echo isset($_GET['id']) ? $_GET['id'] : '' ?>">
							<input type="hidden" name="action" value="<?php echo empty($id)?'add':'edit'?>">
							<input type="hidden" name="id" value="<?php echo $id?>">
							<input type="hidden" name="driver" value="Local">
							<!--Enabled-->
							<div class="element-container">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="enabled"><?php echo _("Enabled") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="enabled"></i>
										</div>
										<div class="col-md-9">
											<span class="radioset">
												<input type="radio" name="enabled" id="enabledyes" value="yes" <?php $enabled = $enabled ?? ''; echo $enabled != "no" ? "CHECKED" : "" ?>>
												<label for="enabledyes"><?php echo _("Yes"); ?></label>
												<input type="radio" name="enabled" id="enabledno" value="no" <?php echo $enabled == "no" ? "CHECKED" : "" ?>>
												<label for="enabledno"><?php echo _("No"); ?></label>
											</span>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="enabled-help" class="help-block fpbx-help-block"><?php echo _("We define if this storage is enabled or disabled.") ?></span>
									</div>
								</div>
							</div>
							<!--END Enabled-->
							<!--Server Name-->
							<div class="element-container">
								<div class="row">
									<div class="col-md-12">
										<div class="row">
											<div class="form-group">
												<div class="col-md-3">
													<label class="control-label" for="name"><?php echo _("Path Name") ?></label>
													<i class="fa fa-question-circle fpbx-help-icon" data-for="name"></i>
												</div>
												<div class="col-md-9">
													<input type="text" class="form-control" id="name" name="name" value="<?php echo isset($name)?$name:''?>"<?php echo $disabled?>>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="name-help" class="help-block fpbx-help-block"><?php echo _("Provide the name for this File Store")?></span>
									</div>
								</div>
							</div>
							<!--END Server Name-->
							<!--Description-->
							<div class="element-container">
								<div class="row">
									<div class="col-md-12">
										<div class="row">
											<div class="form-group">
												<div class="col-md-3">
													<label class="control-label" for="desc"><?php echo _("Description") ?></label>
													<i class="fa fa-question-circle fpbx-help-icon" data-for="desc"></i>
												</div>
												<div class="col-md-9">
													<input type="text" class="form-control" id="desc" name="desc" value="<?php echo isset($desc)?$desc:''?>"<?php echo $disabled?>>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="desc-help" class="help-block fpbx-help-block"><?php echo _("Description or notes for this File Store")?></span>
									</div>
								</div>
							</div>
							<!--END Description-->
							<!--Path-->
							<div class="element-container">
								<div class="row">
									<div class="col-md-12">
										<div class="row">
											<div class="form-group">
												<div class="col-md-3">
													<label class="control-label" for="path"><?php echo _("Path") ?></label>
													<i class="fa fa-question-circle fpbx-help-icon" data-for="path"></i>
												</div>
												<div class="col-md-9">
													<input type="text" class="form-control" id="path" name="path" value="<?php echo isset($path)?$path:''?>"<?php echo $disabled?>>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<span id="path-help" class="help-block fpbx-help-block"><?php echo _("Path on this local system")?></span>
									</div>
								</div>
							</div>
							<!--END Path-->
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script type="text/javascript">
var immortal = <?php echo (isset($immortal) && !empty($immortal))?'true':'false';?>;
$('#server_form').on('submit', function(e) {
	if($("#name").val().length === 0 ) {
		warnInvalid($("#host"),_("The host cannot be empty"));
		return false;
	}else{
		return true;
	}
});
</script>
