<?php
global $amp_conf;
$html = '';
$version	 = get_framework_version();
$version = $version ?: getversion();
$version_tag = '?load_version=' . urlencode((string) $version);
if ($amp_conf['FORCE_JS_CSS_IMG_DOWNLOAD']) {
	$this_time_append	= '.' . time();
	$version_tag 		.= $this_time_append;
} else {
	$this_time_append = '';
}

// BRAND_IMAGE_FREEPBX_FOOT based on condtion 
$footer_img ='';
if(isset($amp_conf['BRAND_IMAGE_FREEPBX_FOOT']) && !empty($amp_conf['BRAND_IMAGE_FREEPBX_FOOT'])){
$footer_img = $amp_conf['BRAND_IMAGE_FREEPBX_FOOT'];
}else{
	$footer_img = 'images/freepbx_small.png';
}


// Brandable logos in footer
//fpbx logo
$html .= '<div class="col-md-4">
	<a target="_blank" href="'
	. $amp_conf['BRAND_IMAGE_FREEPBX_LINK_FOOT']
	. '" >'
	. '<img id="footer_logo1" src="' . $footer_img. $version_tag
	. '" alt="' . $amp_conf['BRAND_FREEPBX_ALT_FOOT'] . '"/>
	</a>
	</div>';

//text
$html .= '<div class="col-md-4" id="footer_text">';
$html .= sprintf(_('%s is a registered trademark of'), '<a href="http://www.freepbx.org" target="_blank">FreePBX</a>') . br() . '<a href="http://www.freepbx.org/copyright.html" target="_blank"> Sangoma Technologies Inc.</a>' . br();
$html .= sprintf(_('%s %s is licensed under the %s'), 'FreePBX', $version, '<a href="http://www.gnu.org/copyleft/gpl.html" target="_blank"> GPL</a>') . br();
$html .= '<a href="http://www.freepbx.org/copyright.html" target="_blank">Copyright&copy; 2007-' . date('Y', time()) . '</a>';

//module license
$module_name??='';
if (!empty($active_modules[$module_name]['license'])) {
	$html .= br() . sprintf(
		_('Current module licensed under %s'),
		trim((string) $active_modules[$module_name]['license'])
	);
}
$benchmark_time??=0;
$benchmark_starttime??=0;
//benchmarking
if (isset($amp_conf['DEVEL']) && $amp_conf['DEVEL']) {
	$benchmark_time = number_format(microtime_float() - $benchmark_starttime, 4);
	$html .= '<br><span id="benchmark_time">Page loaded in ' . $benchmark_time . 's</span>';
}
$html .= '</div>';

$html .= '<div class="col-md-4">
	<a target="_blank" href="' . $amp_conf['BRAND_IMAGE_SPONSOR_LINK_FOOT']
	. '" >'
	. '<img id="footer_logo" src="images/sangoma-horizontal_thumb.png" '
	. 'alt="' . $amp_conf['BRAND_SPONSOR_ALT_FOOT'] . '"/>
	</a>
	</div>';
echo $html;
