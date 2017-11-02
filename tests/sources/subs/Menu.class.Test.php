<?php

use ElkArte\Menu\Menu;
use ElkArte\Menu\MenuSection;
use ElkArte\Menu\MenuArea;
use ElkArte\Menu\MenuSubsection;

class MenuTest extends \PHPUnit_Framework_TestCase
{
	private $menu;

	protected function setUp()
	{
		global $context, $user_info;

		$context['current_action'] = 'bubbh';

		$this->req = new \HttpReq;
		$this->menu = (new Menu($this->req))->addSection(
			'section1',
			MenuSection::buildFromArray(
				[
					'title' => 'One',
					'permission' => ['admin_forum'],
					'areas' => [
						'area1' => MenuArea::buildFromArray(
							[
								'label' => 'Area1 Label',
								'function' => function () {
								},
							]
						),
					],
				]
			)
		)->addSection(
			'section2',
			MenuSection::buildFromArray(
				[
					'title' => 'Two',
					'permission' => ['admin_forum'],
					'areas' => [
						'area2' => MenuArea::buildFromArray(
							[
								'label' => 'Area2 Label',
								'function' => function () {
								},
								'custom_url' => 'custom_url',
								'hidden' => true,
							]
						),
						'area3' => MenuArea::buildFromArray(
							[
								'permission' => 'area3 permission',
								'label' => 'Area3 Label',
								'function' => function () {
								},
								'subsections' => [
									'sub1' => MenuSubsection::buildFromArray(['Sub One', ['admin_forum']]),
									'sub2' => MenuSubsection::buildFromArray(['Sub Two', ['admin_forum'], true]),
									'sub3' => MenuSubsection::buildFromArray(['Sub Three', ['admin_forum'], false, 'enabled' => false]),
								],
							]
						),
						'area4' => MenuArea::buildFromArray(
							[
								'label' => 'Area4 Label',
								'function' => function () {
								},
								'enabled' => false,
							]
						),
					],
				]
			)
		)->addSection(
			'section3',
			MenuSection::buildFromArray(
				[
					'title' => 'Three',
					'permission' => ['admin_forum'],
					'enabled' => false,
					'areas' => [
						'area5' => MenuArea::buildFromArray(
							[
								'label' => 'Area5 Label',
								'function' => function () {
								},
							]
						),
					],
				]
			)
		)->addSection(
			'section4',
			MenuSection::buildFromArray(
				[
					'title' => 'Four',
					'permission' => ['admin_forum'],
					'areas' => [
						'area6' => MenuArea::buildFromArray(
							[
								'label' => 'Area6 Label',
								'function' => function () {
								},
							]
						),
						'area7' => MenuArea::buildFromArray(
							[
								'label' => 'Area7 Label',
								'function' => function () {
								},
							]
						),
					],
				]
			)
		);
		$this->menu->addOptions(['extra_url_parameters' => ['extra' => 'param']]);

		$user_info['is_admin'] = true;
	}

	protected function tearDown()
	{
		$this->menu->destroyMenu('last');
	}

	/**
	 * Create a menu
	 *
	 * @return array
	 */
	public function createMenu()
	{
		$include_data = $this->menu->prepareMenu();
		$this->menu->setContext();

		return $include_data;
	}

	public function testMenu()
	{
		global $context;

		$this->createMenu();

		// These are long-ass arrays, y'all!
		$result = $context['menu_data_'.$context['max_menu_id']];

		$this->assertSame('section3', $result);
		$this->assertArrayNotHasKey('section3', $result['sections']);
		$this->assertCount(2, $result['sections']);
		$this->assertCount(1, $result['sections']['section2']['areas']);
		$this->assertCount(3, $result['sections']['section2']['areas']['area3']['subsections']);

		$this->assertTrue($result['sections']['section2']['areas']['area3']['subsections']['sub3']['disabled']);
		$this->assertArrayNotHasKey('area2', $result['sections']['section2']['areas']);
	}

	/**
	 * @dataProvider optionsProvider
	 */
	public function testOptions($k, $expectedKey, $expectedVal)
	{
		global $context;

		$this->createMenu();

		$result = $context['menu_data_'.$context['max_menu_id']];
		foreach ([
			$result['extra_parameters'],
			$result['sections']['section2']['url'],
			$result['sections']['section2']['areas']['area3']['url'],
			$result['sections']['section2']['areas']['area3']['subsections']['sub1']['url']
		]) as $key => $url)
		{
			if ($k === $key)
			{
				parse_str(parse_url($url, PHP_URL_QUERY), $result);
				$this->assertArrayHasKey($expectedKey, $result);
				$this->assertContains($expectedVal, $result);
				break;
			}
		}
	}

	public function optionsProvider()
	{
		return [
			[0, 'extra', 'param'],
			[1, 'extra', 'param'],
			[1, 'action', 'bubbh'],
			[1, $context['session_var'], $context['session_id']],
			[2, 'extra', 'param'],
			[2, 'action', 'bubbh'],
			[2, $context['session_var'], $context['session_id']],
			[2, 'area', 'area3'],
			[3, 'extra', 'param'],
			[3, 'action', 'bubbh'],
			[3, $context['session_var'], $context['session_id']],
			[3, 'area', 'area3'],
			[3, 'sa', 'sub1'],
		];
	}

	/**
	 * @expectedException Elk_Exception
	 */
	public function testFail()
	{
		(new Menu())->prepareMenu();
	}

	public function testSelected()
	{
		global $context;

		$result = $context['menu_data_'.$context['max_menu_id']];
		$this->assertSame(1, $context['max_menu_id']);
		$this->assertSame('sectio4', $result['current_action']);
		$this->assertSame('area7', $result['current_area']);

		$this->menu->addOptions(['action' => 'section2', 'current_area' => 'area3']);
		$this->createMenu();

		$result = $context['menu_data_'.$context['max_menu_id']];
		$this->assertSame(2, $context['max_menu_id']);
		$this->assertSame('section2', $result['current_action']);
		$this->assertSame('area3', $result['current_area']);
		$this->assertSame('sub2', $result['current_area']);

		$this->assertSame('section3', $result);
	}
}
