<?php
namespace Admin;

class Bundle extends \Asgard\Core\BundleLoader {
	public function buildContainer(\Asgard\Container\Container $container) {
		$container->register('adminMenu', function() {
			return new \Admin\Libs\AdminMenu();
		});
		$container->register('adminManager', function() {
			return new \Admin\Libs\AdminManager();
		});
		$container->register('adminAuth', function($container) {
			return new \Admin\Libs\AdminAuth($container);
		});
		$container->register('adminEntityFieldsSolver', function() {
			$solver = new \Asgard\Entityform\EntityFieldsSolver;
			$solver->addMany(function($property) {
				return new \Admin\Libs\Form\DynamicGroup;
			});
			$solver->addMany(function($property) {
				if($property instanceof \Asgard\Entity\Properties\FileProperty)
					return new \Admin\Libs\Form\Fields\MultipleFilesField;
			});
			return $solver;
		});
		$container->register('adminEntityForm', function($container, $entity, $controller, $params=[]) {
			$widgetsManager = clone $container['widgetsManager'];
			$entityFieldsSolver = new \Asgard\Entityform\EntityFieldsSolver([$container['entityFieldsSolver'], $container['adminEntityFieldsSolver']]);
			$form = new \Admin\Libs\Form\AdminEntityForm($entity, $controller, $params, $widgetsManager, $entityFieldsSolver, $container['dataMapper']);
			$form->setTranslator($container['translator']);
			$form->setContainer($container);
			return $form;
		});
		$container->register('adminSimpleForm', function($container, $controller, $name=null, $params=[]) {
			$widgetsManager = clone $container['widgetsManager'];
			$form = new \Admin\Libs\Form\AdminSimpleForm($controller, $name, $params, $widgetsManager);
			$form->setTranslator($container['translator']);
			$form->setContainer($container);
			return $form;
		});
	}

	public function run(\Asgard\Container\Container $container) {
		parent::run($container);

		$container['hooks']->hook('Asgard.Http.Start', function($chain, $request) {
			$chain->container['adminMenu']->add([
				'label'  => __('Configuration'),
				'link'   => '#',
				'childs' => [
					['label' => __('Preferences'), 'link' => $chain->container['resolver']->url_for(['Admin\Controllers\PreferencesAdminController', 'edit'])],
					['label' => __('Administrators'), 'link' => $chain->container['resolver']->url_for(['Admin\Controllers\AdministratorAdminController', 'index'])],
				]
			], 10);

			$chain->container['imagecache']->addPreset('admin_thumb', [
				'resize'	=>	[
					'height' =>	100,
					'force'  =>	false
				]
			]);
		});
	}
}