<h2><?php echo _("Filestore")?></h2>
<div class="list-group">
<?php
sort($drivers);
foreach ($drivers as $driver)
{
    $class = $driver == $current ? "active" : "";
	echo sprintf('<a href="?display=filestore&driver=%1$s" class="%2$s list-group-item list-group-item-action">%1$s</a>', $driver, $class);
}
?>
</div>