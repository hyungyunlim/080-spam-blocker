<?php
$disabled = (isset($readonly) && !empty($readonly))?' disabled ':'';
if (!isset($id)) {
	$id = "";
}
$fstype = isset($fstype)?$fstype:'auto';
?>
<div class="container-fluid">
	<h1><?php echo _('Email List')?></h1>
	<div class = "display full-border">
		<div class="row">
			<div class="col-sm-12">
				<div class="fpbx-container">
					<div class="display full-border">
						<form class="fpbx-submit" action="?display=filestore" method="post" id="server_form" name="server_form" data-fpbx-delete="?display=filestore&action=delete&id=<?php echo isset($_GET['id']) ? $_GET['id'] : '' ?>">
						<input type="hidden" name="action" value="<?php echo empty($id)?'add':'edit'?>">
						<input type="hidden" name="id" value="<?php echo $id?>">
						<input type="hidden" name="driver" value="Email">
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
											<input type="radio" name="enabled" id="enabledyes" value="yes" <?php echo $enabled != "no" ? "CHECKED" : "" ?>>
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
												<label class="control-label" for="name"><?php echo _("Email List Name") ?></label>
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
									<span id="name-help" class="help-block fpbx-help-block"><?php echo _("Name this email list")?></span>
								</div>
							</div>
						</div>
						<!--END-->
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
									<span id="desc-help" class="help-block fpbx-help-block"><?php echo _("Description or notes for this server. This will be used as the email subject.")?></span>
								</div>
							</div>
						</div>
						<!--END Description-->
						<!--From Email-->
						<div class="element-container">
							<div class="row">
								<div class="form-group">
									<div class="col-md-3">
										<label class="control-label" for="from"><?php echo _("From Email") ?></label>
										<i class="fa fa-question-circle fpbx-help-icon" data-for="from"></i>
									</div>
									<div class="col-md-9">
										<input type="text" class="form-control" id="from" name="from" value="<?php echo isset($from)?$from:''?>">
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-md-12">
									<span id="from-help" class="help-block fpbx-help-block"><?php echo _("From Email. If this is blank the system will try and determine an appropriate from address")?></span>
								</div>
							</div>
						</div>
						<!--END From Email-->
						<!--Email Address-->
						<div class="element-container">
							<div class="row">
								<div class="col-md-12">
									<div class="row">
										<div class="form-group">
											<div class="col-md-3">
												<label class="control-label" for="addr"><?php echo _("Email Address") ?></label>
												<i class="fa fa-question-circle fpbx-help-icon" data-for="addr"></i>
											</div>
											<div class="col-md-9">
												<textarea class="form-control" id="addr" rows="8" name="addr"><?php echo $addr?></textarea>
											</div>
										</div>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-md-12">
									<span id="addr-help" class="help-block fpbx-help-block"><?php echo _("Email address where backups should be emailed to").'<br/>'._("You may enter 1 address per line.")?></span>
								</div>
							</div>
						</div>
						<!--END Email Address-->
						<!--Max Email Size-->
						<div class="element-container">
							<div class="row">
								<div class="col-md-12">
									<div class="row">
										<div class="form-group">
											<div class="col-md-3">
												<label class="control-label" for="maxsize"><?php echo _("Max Email Size") ?></label>
												<i class="fa fa-question-circle fpbx-help-icon" data-for="maxsize"></i>
											</div>
											<div class="col-md-9">
												<?php
												$maxtype = isset($maxtype)?$maxtype:'mb';
												?>
												<input type="number" class="form-control" id="maxsize" name="maxsize" value="<?php echo isset($maxsize)?$maxsize:10?>"<?php echo $disabled?>>
												<div class="radioset">
													<input type="radio" name="maxtype" id="maxtypeb" value="b" <?php echo $maxtype =='b'?'CHECKED':''?><?php echo $disabled?>>
													<label for="maxtypeb"><?php echo _("B")?></label>
													<input type="radio" name="maxtype" id="maxtypekb" value="kb" <?php echo $maxtype =='kb'?'CHECKED':''?><?php echo $disabled?>>
													<label for="maxtypekb"><?php echo _("KB")?></label>
													<input type="radio" name="maxtype" id="maxtypemb" value="mb" <?php echo $maxtype =='mb'?'CHECKED':''?><?php echo $disabled?>>
													<label for="maxtypemb"><?php echo _("MB")?></label>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-md-12">
									<span id="maxsize-help" class="help-block fpbx-help-block"><?php
						echo _('The maximum size a backup can be and still be emailed. '
							. 'Some email servers limit the size of email attachments, '
							. 'this will make sure that files larger than the max size '
							. 'are not sent.');
						echo "<br>\n";
						echo _('This has a maximum size of 25MB.');
						?>
									</span>
								</div>
							</div>
						</div>
						<!--END Max Email Size-->
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
				warnInvalid($("#host"),_("The name cannot be empty"));
				return false;
			}
			/*
			//Validate email textarea.
			var pattern = /^([\w-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|(([\w-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/;
			var lines = $('#addr').val().split(/\r?\n/);
			var pass=true;
			var valid = new Promise( (resolve, reject) => {
					for (var i = 0, len = lines.length; i < len; i++) {
						if(!$.trim(i).match(pattern)){
							pass = false;
						}
						if(i == len-1){
							if(pass){
								resolve('true');
							}else{
								reject('false');
							}
						}
					}
				});
				valid.then(function(){return true;},
				function(){
					warnInvalid($("#addr"),_("Please check that the email addresses are valid. Make sure you only have 1 email per line. Note these will be BCC and not see eachother"));
					return false;
				});
				*/
	});
</script>
