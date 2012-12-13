<?php
/**
 * Pixmicat! Language module loader
 *
 * @package PMCLibrary
 * @version $Id$
 */

class LanguageLoader {
	private $locale;
	private $language;

	private function __construct($locale, array $language) {
		$this->locale = $locale;
		$this->language = $language;
	}

	/**
	 * 取得語言物件之單例。
	 *
	 * @return LanguageLoader 語言物件
	 */
	public static function getInstance() {
		static $inst = null;
		if ($inst == null) {
			$locale = PIXMICAT_LANGUAGE;
			$langFile = "./lib/lang/$locale.php";
			if (file_exists($langFile)){
				require $langFile;
			} else {
				$locale = 'en_US';
				require './lib/lang/en_US.php';
			}
			$inst = new LanguageLoader($locale, $language);
		}
		return $inst;
	}

	/**
	 * 取得語系設定。
	 *
	 * @return string 語系代表字串
	 */
	public function getLocale() {
		return $this->locale;
	}

	/**
	 * 取得翻譯資源字串陣列。
	 *
	 * @return array 翻譯字串陣列
	 */
	public function getLanguage() {
		return $this->language;
	}

	/**
	 * 自翻譯資源字串陣列取出對應文字。
	 *
	 * @param  string $index 翻譯資源索引
	 * @return string        對應文字
	 */
	private function getTranslationBody($index) {
		if (array_key_exists($index, $this->language)) {
			$str = $this->language[$index];
		} else {
			$str = $index;
		}
		return $str;
	}

	/**
	 * 取得指定項目之翻譯，並進行變數字串的替代。
	 *
	 * @param string arg1 翻譯資源索引字
	 * @param mixed  arg2 變數
	 * @return string 翻譯後之字串
	 */
	public function getTranslation(/*args[]*/) {
		if (!func_num_args()) {
			return '';
		}
		$argList = func_get_args();
		$argList[0] = $this->getTranslationBody($argList[0]);
		return call_user_func_array('sprintf', $argList);
	}

	/**
	 * 附加翻譯資源字串。
	 *
	 * @param  array  $language 翻譯資源字串陣列
	 */
	public function attachLanguage(array $language) {
		$this->language = $this->language + $language;
	}
}

/**
 * 取出翻譯資源檔對應字串。
 *
 * @param args 翻譯資源檔索引、其餘變數
 * @see LanguageLoader->getTranslation
 */
function _T(/*$args[]*/) {
	return call_user_func_array(
		array(LanguageLoader::getInstance(), 'getTranslation'),
		func_get_args());
}

/**
 * 動態附加翻譯資源。此函式已經由 {@link #LanguageLoader->attachLanguage} 取代。
 *
 * @deprecated 7th.Release. Use LanguageLoader->attachLanguage instead.
 * @param callable $fcall 附加翻譯資源字串的函式
 */
function AttachLanguage($fcall){
	$GLOBALS['language'] = array();
	call_user_func($fcall);
	LanguageLoader::getInstance()->attachLanguage($GLOBALS['language']);
}