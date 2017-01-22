<?php

/**
 * General exception handler. Has support for throwing errors that
 * are specifically targeted at users, and even placing multiple messages
 * in one exception.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version   1.1 beta 4
 *
 */

use ElkArte\Errors\Errors as E;

/**
 * Class Elk_Exception
 */
class Elk_Exception extends Exception
{
	/**
	 * The log under which the error should be displayed.
	 *
	 * @var string
	 */
	private $log = '';

	/**
	 * Values to use in vsprintf.
	 *
	 * @var string[]
	 */
	private $sprintf = [];

	/**
	 * Elk_Exception constructor.
	 *
	 * Extended exception rules because we need more stuff
	 *
	 * @param string    $message  index of $txt or message
	 * @param string    $log      type of error, defines under which "log" is it shown.
	 *                            - If it is empty, the error is not logged.
	 * @param string[]  $sprintf  optional array of values to use in vsprintf with the $txt
	 * @param int       $code
	 * @param Exception $previous
	 */
	public function __construct($message, $log = '', $sprintf = [], $code = 0, Exception $previous = null)
	{
		global $txt;

		$this->log = $log;
		$this->sprintf = $sprintf;

		// Make sure everything is assigned properly.
		parent::__construct($this->loadMessage($message), $code, $previous);
	}

	/**
	 * Cleans up the message param passed to the constructor.
	 *
	 * @param string $message Can be several different things:
	 *                        - The index of $txt string
	 *                        - A plain text message
	 *                        - A namespaced index in the form:
	 *                        - language.index
	 *                        - a "language" followed by a "dot" followed by the "index"
	 *                        - "language" can be any character matched by \w
	 *                        - "index" can be anything
	 *                        - "language" is loaded by theme()->getTemplates()->loadLanguageFile.
	 *
	 * @return string[]
	 */
	private function parseMessage($message)
	{
		// Load message with language support
		if (is_array($message))
		{
			$language = $message[0];
			$msg = $message[1];
		}
		else
		{
			// language.messageIndex
			if (preg_match('/^(\w+)\.(.+)$/', $message, $matches) !== 0)
			{
				$language = $matches[1];
				$msg = $matches[2];
			}
			// Simple Error message
			else
			{
				$language = 'Errors';
				$msg = $message;
			}
		}

		return [$msg, $language];
	}

	/**
	 * Loads the language file specified in Elk_Exception::parseMessage()
	 * and replaces the index received in the constructor.
	 *
	 * @param string $message
	 *
	 * @return string The index or the message.
	 */
	private function loadMessage($message)
	{
		global $txt;

		list ($msg, $lang) = $this->parseMessage($message);
		theme()->getTemplates()->loadLanguageFile($lang);
		$this->logMessage($msg, $lang);

		return !isset($txt[$msg]) ? $msg : vsprintf($txt[$msg], $this->sprintf);
	}

	/**
	 * Loads the language file specified in Elk_Exception::parseMessage()
	 * and replaces the index received in the constructor.  Logs the message if needed.
	 *
	 * @param string $msg
	 * @param string $lang
	 */
	private function logMessage($msg, $lang)
	{
		global $language, $txt;

		theme()->getTemplates()->loadLanguageFile($lang, $language);

		if (!empty($this->log))
		{
			$msg = !isset($txt[$msg]) ? $msg : vsprintf($txt[$msg], $this->sprintf);
			E::instance()->log_error($msg, $this->log, $this->getFile(), $this->getLine());
		}
	}

	/**
	 * The log under which the error should be displayed.
	 *
	 * @return string
	 */
	public function getLog()
	{
		return $this->log;
	}
}
