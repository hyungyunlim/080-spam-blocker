<?php
/**
 * FreePBX module based gettext class
 *
 * short translates based on a modules domain self initializing
 *
 * @author Philippe Lindheimer
 */
namespace UCP;
#[\AllowDynamicProperties]
class Modgettext extends UCP {

	// This hash maps a given module name to the initialized txt domain it should use.
	// If the hash is not initialized then it will be setup the first time it is attempted.
	private array $tdhash = [];

	// stack for saved textdomains
	private array $textdomain_stack = [];


	public function __construct($UCP) {
		$this->lang = !empty($_COOKIE['lang']) ? $_COOKIE['lang'] : 'en_US';
		$this->UCP = $UCP;
		$this->root = $this->UCP->FreePBX->Config->get('AMPWEBROOT');
		//reset textdomain on startup
		textdomain("ucp");
	}

	/**
	 * _() translate a string given a module it comes from
	 * short translates a string given the string and the owning module
	 *
	 * @access	static public
	 * @param	string
	 * @param	string
	 * @return string
	 *
	 * Given a string and a module, this function will attempt to translate the string
	 * using the module's textdomain if translations exist. It depends on the private
	 * method _bindtextdomain to check if the domains have been initialized and if not
	 * it will do so. It expects the proper domain to use to be returned by this method
	 * so that it can simply attempt the tranlastion. If it finds the translated text
	 * is the same as the original text AND the domain is NOT amp (core/framework's default)
	 * then it will make a last attempt to lookup a translation in core since many old
	 * translations for some modules were put in amp by the translators back when
	 * this didn't work too well.
	 */
	public function _($string, $module) {
		// don't do anything if we don't have gettext present
		if (!extension_loaded('gettext')) {
			return $string;
		}
		// get the domain that we should use for this module and translate with that
		$domain = $this->_bindtextdomain($module);
		$tstring = dgettext($domain,$string);

		// if our translation didn't change and we aren't already using 'amp' then try with amp
		if ($tstring == $string && $domain != 'ucp') {
			$tstring = dgettext('ucp',$string);
		}
		return $tstring;
	}

	/**
	 * textdomain
	 * short sets the textdomain to the domain defined for this module
	 *
	 * @access static public
	 * @param string
	 * @return string
	 */
	public function textdomain($module) {
		// can't do anything without gettext
		if (!extension_loaded('gettext')) {
			return null;
		}
		// act like textdomain() would if passed null even though that is not the intended use
		if ($module === null) {
			return textdomain(null);
		}
		return textdomain($this->_bindtextdomain($module));
	}

	/**
	 *
	 * push_textdomain
	 * short sets the textdomain while saving the current domain on a stack
	 *
	 * @access static public
	 * @param string
	 * @return string
	 */
	public function push_textdomain($module) {
		array_push($this->textdomain_stack, textdomain(null));
		return $this->textdomain($module);
	}

	/**
	 *
	 * pop_textdomain
	 * short resets the textdomain to the previous value or leaves unchanged
	 *
	 * @access static public
	 * @return string
	 */
	public function pop_textdomain() {
		// if array is empty then null is returned to textdomain() which is the desired affect
		return textdomain(array_pop($this->textdomain_stack));
	}

	/**
	 *
	 * we use the static array $tdhash to determine if we have bound a directory
	 * to a domain yet. If not, we check to see if that i18n directory exists for the given
	 * module and if so we do the binding. If not, we set it to 'amp' which is core's domain.
	 * We also special case core's domain since currenlty FreePBX does that. (something that
	 * should be changed one of these days...).
	 */
	private function _bindtextdomain($module) {
		if (isset($this->tdhash[$module])) {
			return $this->tdhash[$module];
		} else {
			// We special case core and assume it is there since that is assumed throughout
			//
			switch($module) {
				case "settings":
				case "home":
				case "ucp":
					bindtextdomain('ucp', $this->root. '/admin/modules/ucp/i18n');
					bind_textdomain_codeset('ucp', 'utf8');
					$this->tdhash[$module] = 'ucp';
				break;
				default:
					if (isset($_COOKIE['lang']) && is_dir($this->root. '/admin/modules/' . $module . '/i18n')) {
						bindtextdomain($module, $this->root. '/admin/modules/'. $module . '/i18n');
						bind_textdomain_codeset($module, 'utf8');
						$this->tdhash[$module] = $module;
					} else {
						$this->tdhash[$module] = 'ucp';
					}
				break;
			}
			return $this->tdhash[$module];
		}
	}
}
