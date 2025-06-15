<div class="container-fluid">
	<h1><?php echo _('SMS Web Hooks') ?></h1>

	<div class="panel panel-info">
		<div class="panel-heading">
			<div class="panel-title">
				<a href="#" data-toggle="collapse" data-target="#moreinfo-smswebhook" aria-expanded="true"><i class="fa fa-info-circle"></i></a>&nbsp;&nbsp;&nbsp;<?php echo _('What is SMS Web Hooks ?') ?>
			</div>
		</div>
		<div class="panel-body collapse" id="moreinfo-smswebhook" aria-expanded="true">
			<p><?php echo _('This section is used to add web hook urls. The sms data will be sent to this web hook based the web hook settings.') ?></p>
		</div>
	</div>

	<div class="display full-border">
		<!-- Grid -->
		<div class="row">
			<div class="col-sm-12">
				<div class="fpbx-container">
					<form class="fpbx-submit" name="frm_sms_web_hooks" action="" method="post" role="form">
						<form autocomplete="off" name="edit" action="" method="post" onsubmit="return edit_onsubmit();">
							<?php echo load_view(__DIR__ . '/grid.php', ['settings' => $settings]); ?>
						</form>
					</form>
				</div>
			</div>
		</div>
		<!-- Grid -->
		<!--Modals-->
		<?php echo load_view(__DIR__ . '/form.php', []); ?>
		<!--Modals-->
	</div>
</div>
</div>