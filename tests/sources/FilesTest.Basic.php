<?php

/**
 * TestCase class for file integrity
 */
class TestFiles extends PHPUnit_Framework_TestCase
{
	protected $_ourFiles = array();

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		$this->_ourFiles = array();

		$directory = new RecursiveDirectoryIterator(BOARDDIR);
		$iterator = new RecursiveIteratorIterator($directory);
		$regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

		foreach ($regex as $fileo)
		{
			$file = $fileo[0];

			// We do not care about non-project files
			if (strpos($file, '/sources/ext/') !== false)
				continue;
			if (strpos($file, '/vendor/') !== false)
				continue;

			$this->_ourFiles[] = $file;
		}
	}

	/**
	 * Test that the headers are in place
	 * @todo distinguish between SMF-derived and non-SMF-derived
	 */
	public function testHeaders()
	{
		$header_styles = array(
			// Pure ElkArte
			'^<\?php

\/\*\*
(?: \*.{0,200}\n)* \* @name\s{1,10}ElkArte Forum
 \* @copyright ElkArte Forum contributors
 \* @license\s+?BSD http:\/\/opensource\.org\/licenses\/BSD-3-Clause
 \*
 \* @version \d+\.\d+(?:\.\d+|\sdev|\s(beta|RC|Release Candidate)\s\d+)
(?:(?: \*\n)?|(?: \*(?:\s.{0,200})?\n))+ \*\/',
			// Pure ElkArte with php path
			'^#!\/usr\/bin\/php -q
<\?php

\/\*\*
(?: \*.{0,200}\n)* \* @name\s{1,10}ElkArte Forum
 \* @copyright ElkArte Forum contributors
 \* @license\s+?BSD http:\/\/opensource\.org\/licenses\/BSD-3-Clause
 \*
 \* @version \d+\.\d+(?:\.\d+|\sdev|\s(beta|RC|Release Candidate)\s\d+)
(?:(?: \*\n)?|(?: \*(?:\s.{0,200})?\n))+ \*\/',
			// SMF-derived
			'^<\?php

\/\*\*
(?: \*.{0,200}\n)* \* @name\s{1,10}ElkArte Forum
 \* @copyright ElkArte Forum contributors
 \* @license\s+?BSD http:\/\/opensource\.org\/licenses\/BSD-3-Clause
 \*
 \* This file contains code covered by:
 \* copyright:\s+?20\d\d Simple Machines (?:Forum )?\(http:\/\/www\.simplemachines\.org\)
 \* license:\s+?BSD, See included LICENSE\.TXT for terms and conditions\.(
 \*
 \* copyright:\s+?2004-2011, GreyWyvern - All rights reserved\.
 \* license:\s+?BSD, See included LICENSE.TXT for terms and conditions.)?
 \*
 \* @version \d+\.\d+(?:\.\d+|\sdev|\s(beta|RC|Release Candidate)\s\d+)
(?:(?: \*\n)?|(?: \*(?:\s.{0,200})?\n))+ \*\/',
		);
		foreach ($this->_ourFiles as $file)
		{
			// We do not care about some project files
			if (strpos($file, BOARDDIR . '/cache/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/addons/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/attachments/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/avatars/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/docs/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/packages/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/release_tools/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/smileys/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/tests/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/wiki/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/install/patch') !== false)
				continue;
			if (strpos($file, '/vendor/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/themes/default/languages/') !== false)
				continue;
			if (strpos($file, 'phpunit_coverage.php') !== false)
				continue;
			if (basename($file) == 'index.php' && $file != BOARDDIR . '/index.php')
				continue;
			if ($file == BOARDDIR . '/Settings.php'
				|| $file == BOARDDIR . '/Settings_bak.php'
				|| $file == BOARDDIR . '/Settings.sample.php'
				|| $file == BOARDDIR . '/Settings_bak.sample.php')
				continue;
			if (is_link($file))
				continue;

			$file_content = @file_get_contents($file);
			if (empty($file_content))
				continue;

			$found = false;

			foreach ($header_styles as $style)
			{
				if (preg_match('%' . strtr($style, array("\n" => '\n')) . '%', $file_content) == 1)
				{
					$found = true;
					continue;
				}
			}
			$this->assertTrue($found, $file);
		}
	}
}