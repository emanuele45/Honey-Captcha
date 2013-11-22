<?php

function add_honey_captcha(&$known_verifications)
{
	// Because we are reusing all the settings, better unset it first
	$key = array_search('captcha', $known_verifications);
	unset($known_verifications[$key]);

	$known_verifications[] = 'HoneyCaptcha';
	loadLanguage('HoneyCaptcha');
}

class Control_Verification_HoneyCaptcha implements Control_Verifications
{
	private $_options = null;
	private $_show_captcha = false;
	private $_text_value = null;
	private $_image_href = null;
	private $_tested = false;
	private $_use_graphic_library = false;
	private $_standard_captcha_range = array();

	public function __construct($verificationOptions = null)
	{
		$this->_use_graphic_library = in_array('gd', get_loaded_extensions());

		// Keep the standard range, because bots are already good at solving it
		// Skip I, J, L, O, Q, S and Z.
		$this->_standard_captcha_range = array_merge(range('A', 'H'), array('K', 'M', 'N', 'P', 'R'), range('T', 'Y'));

		if (!empty($verificationOptions))
			$this->_options = $verificationOptions;
	}

	public function showVerification($isNew, $force_refresh = true)
	{
		global $context, $scripturl;

		// a bit of a trick, just to load only once the js
		if (!isset($context['captcha_js_loaded']))
		{
			$context['captcha_js_loaded'] = false;

			// The templates
			loadTemplate('GenericControls');
			loadTemplate('HoneyCaptcha');
		}

		// Some javascript ma'am? (But load it only once)
		if (!empty($this->_options['override_visual']) || !isset($this->_options['override_visual']) && empty($context['captcha_js_loaded']))
		{
			loadJavascriptFile('captcha.js');
			$context['captcha_js_loaded'] = true;
			loadCSSFile('captcha.css');
		}

		$this->_tested = false;

		if ($isNew)
		{
			$this->_show_captcha = !empty($this->_options['override_visual']) || !isset($this->_option['override_visual']);
			$this->_text_value = '';
			$this->_image_href = $scripturl . '?action=verificationcode;vid=' . $this->_options['id'] . ';rand=' . md5(mt_rand());

			addInlineJavascript('
				var verification' . $this->_options['id'] . 'Handle = new elkCaptcha("' . $this->_image_href . '", "' . $this->_options['id'] . '", ' . ($this->_use_graphic_library ? 1 : 0) . ');', true);
		}

		if ($isNew || $force_refresh)
			$this->createTest($force_refresh);

		return $this->_show_captcha;
	}

	public function createTest($refresh = true)
	{
		if (!$this->_show_captcha)
			return;

		if ($refresh)
		{
			$_SESSION[$this->_options['id'] . '_vv']['code'] = '';

			// Are we overriding the range?
			$character_range = !empty($this->_options['override_range']) ? $this->_options['override_range'] : $this->_standard_captcha_range;

			for ($i = 0; $i < 5; $i++)
				$_SESSION[$this->_options['id'] . '_vv']['code'] .= $character_range[array_rand($character_range)];
		}
		else
			$this->_text_value = !empty($_REQUEST[$this->_options['id'] . '_vv']['code']) ? Util::htmlspecialchars($_REQUEST[$this->_options['id'] . '_vv']['code']) : '';
	}

	public function prepareContext()
	{
		return array(
			'template' => 'honeycaptcha',
			'values' => array(
				'image_href' => $this->_image_href,
				'text_value' => $this->_text_value,
				'use_graphic_library' => $this->_use_graphic_library,
				'is_error' => $this->_tested && !$this->_verifyCode(),
			)
		);
	}

	public function doTest()
	{
		$this->_tested = true;

		if ($this->_verifyCode())
			return 'correct_verification_code';

		return true;
	}

	public function settings()
	{
		global $txt, $scripturl, $modSettings;

		// Generate a sample registration image.
		$verification_image = $scripturl . '?action=verificationcode;rand=' . md5(mt_rand());

		// Visual verification.
		$config_vars = array(
			array('title', 'configure_honeycaptcha'),
			array('desc', 'configure_honeycaptcha_desc'),
			array('desc', 'configure_honeycaptcha_desc2'),
		);

		if (isset($_GET['save']))
		{
			if (isset($_POST['visual_verification_num_chars']) && $_POST['visual_verification_num_chars'] < 6)
				$_POST['visual_verification_num_chars'] = 5;
		}

		$_SESSION['visual_verification_code'] = '';
		for ($i = 0; $i < $modSettings['visual_verification_num_chars']; $i++)
			$_SESSION['visual_verification_code'] .= $this->_standard_captcha_range[array_rand($this->_standard_captcha_range)];

		// Show the image itself, or text saying we can't.
		if ($this->_use_graphic_library)
			$config_vars['vv']['postinput'] = '<br /><img src="' . $verification_image . ';type=2" alt="' . $txt['setting_image_verification_sample'] . '" id="verification_image" /><br />';
		else
			$config_vars['vv']['postinput'] = '<br /><span class="smalltext">' . $txt['setting_image_verification_nogd'] . '</span>';

		return $config_vars;
	}

	private function _verifyCode()
	{
		return !$this->_show_captcha || (!empty($_REQUEST[$this->_options['id'] . '_vv']['code']) && !empty($_SESSION[$this->_options['id'] . '_vv']['code']));
	}
}
