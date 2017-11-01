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

		$_GET['action'] = 'section2';

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
		);
		$this->menu->addOptions(['extra_url_parameters' => ['extra' => 'param']]);

		$user_info['is_admin'] = true;
		//~ $context['right_to_left'] = false;
		//~ $context['session_var'] = 'abcde';
		//~ $context['session_id'] = '123456789';
		//~ $context['current_action'] = 'section1';
		//~ $context['current_subaction'] = '';
	}

	/**
	 * Create a menu
	 *
	 * @return array|bool
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

		$this->assertArrayNotHasKey('section3', $result['sections']);
		$this->assertCount(2, $result['sections']);
		$this->assertCount(1, $result['sections']['section2']['areas']);
		$this->assertCount(3, $result['sections']['section2']['areas']['area3']['subsections']);

		$this->assertTrue($result['sections']['section2']['areas']['area3']['subsections']['sub3']['disabled']);
		$this->assertTrue($result['sections']['section2']['areas']['area3']['subsections']['sub1']['is_first']);
		$this->assertArrayNotHasKey('area2', $result['sections']['section2']['areas']);
		var_dump('area2', $result, $context, $result);
	}

	public function testOptions()
	{
		global $context;

		$this->createMenu();

		$result = $context['menu_data_'.$context['max_menu_id']];
		$this->assertStringStartsWith(';extra=param', $result['extra_parameters']);
	}

	/**
	 * @expectedException Elk_Exception
	 */
	public function testFail()
	{
		(new Menu())->prepareMenu();
	}

	public function testLinktree()
	{
		global $context;

		$this->menu->addOption('action', 'section2');
		$this->menu->addOption('current_area', 'area3');
		$this->createMenu();

		$this->assertContains('sub2', $context['linktree'][2]['url']);
		$this->assertCount(3, $context['linktree']);
	}
}
