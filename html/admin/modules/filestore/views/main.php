<?php
$tabs = '';
$tabcontent = '';
$active_driver = !empty($_POST['driver']) && in_array($_POST['driver'], $drivers) ? $_POST['driver'] : '';
$first = empty($active_driver);
sort($drivers);

foreach ($drivers as $driver) {
	$class = $first || $active_driver == $driver ? "active" : "";
	$tabs .= sprintf('<li role="presentation" class="%2$s"><a href="#%1$s" data-toggle="tab">%1$s</a></li>', $driver, $class);
	$tabcontent .= sprintf('<div id="%s" class="tab-pane display %s">%s</div>', $driver, $class, $fs->getDisplay($driver));
	$first = false;
}
?>
<div class="container-fluid">
	<div class="row">
		<div class="col-sm-12">
			<div class="fpbx-container">
				<div class="display no-border">
					<h1><?php echo _("File Store")?></h1>
					<?php
					// At some point we can probably kill this... Maybe make is a 1 time panel that may be dismissed
					echo show_help(sprintf("<p>%s</p>", _('Filestore is a filesystem abstraction module for your PBX. You can use it to setup file system destinations that can be used in other modules')), _('What is File Store?'), false, true, "info");
					?>
					<ul class="nav nav-tabs">
						<?php echo $tabs ?>
					</ul>
					<div class="tab-content">
						<?php echo $tabcontent;?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
