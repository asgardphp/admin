<?php
namespace App\Admin\Libs\Controller;

abstract class AdminParentController extends \Asgard\Core\Controller {
	public function configure() {
		$this->layout = array('\App\Admin\Controllers\AdminController', 'layout');
		$this->htmlLayout = false;
		if(!\Asgard\Core\App::get('session')->get('admin_id')) {
			\Asgard\Core\App::get('session')->set('redirect_to', \Asgard\Core\App::get('url')->full());
			return \Asgard\Core\App::get('response')->setCode(401)->redirect('admin/login', true);
		}
	}
}