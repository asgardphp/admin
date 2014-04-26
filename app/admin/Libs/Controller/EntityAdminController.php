<?php
namespace App\Admin\Libs\Controller;

abstract class EntityAdminController extends AdminParentController {
	protected $_entity = null;
	protected $_singular = null;
	protected $_plural = null;
	protected static $_hooks = array();
	
	public function __construct() {
		$this->__messages = array(
			'modified'			=>	__('Element updated with success.'),
			'created'				=>	__('Element created with success.'),
			'many_deleted'	=>	__('%s elements deleted.'),
			'deleted'				=>	__('Element deleted with success.'),
		);

		$_entity = $this->_entity;
		$definition = $_entity::getDefinition();
		$definition->trigger('asgardadmin', get_called_class());

		if($this->_singular === null)
			$this->_singular = strtolower(\Asgard\Utils\NamespaceUtils::basename($this->_entity));
		if($this->_plural === null)
			$this->_plural = $this->_singular.'s';
		if(isset($this->_messages))
			$this->_messages = array_merge($this->__messages, $this->_messages);
		else
			$this->_messages = $this->__messages;
	}
	
	public static function getEntity() {
		return $this->_entity;
	}
	
	public static function getIndexURL() {
		return static::url_for('index');
	}
	
	public static function getEditURL($id) {
		return static::url_for('edit', array('id'=>$id));
	}
	
	/**
	@Route('')
	*/
	public function indexAction($request) {
		$_entity = $this->_entity;
		$definition = $_entity::getDefinition();
		$_plural = $this->_plural;
		
		$this->searchForm = new \Asgard\Form\Form(null, array('method'=>'get'));
		$this->searchForm->search = new \Asgard\Form\Fields\TextField;
	
		//submitted
		$controller = $this;
		$this->globalactions = array();
		$definition->trigger('asgardadmin_globalactions', array(&$this->globalactions), function($chain, &$actions) use($_entity, $controller) {
			$actions[] = array(
				'text'	=>	__('Delete'),
				'value'	=>	'delete',
				'callback'	=>	function() use($_entity, $controller) {
					$i = 0;
					if(\Asgard\Core\App::get('post')->size()>1) {
						foreach(POST::get('id') as $id)
							$i += $_entity::destroyOne($id);
					
						Flash::addSuccess(sprintf($controller->_messages['many_deleted'], $i));
					}
				}
			);
		});
		foreach($this->globalactions as $action) {
			if(\Asgard\Core\App::get('post')->get('action') == $action['value']) {
				$cb = $action['callback'];
				$cb();
			}
		}
		
		$conditions = array();
		#Search
		if(\Asgard\Core\App::get('get')->get('search')) {
			$conditions['or'] = array();
			foreach($_entity::propertyNames() as $property) {
				if($property != 'id')
					$conditions['or']["`$property` LIKE ?"] = '%'.\Asgard\Core\App::get('get')->get('search').'%';
			}
		}
		#Filters
		elseif(\Asgard\Core\App::get('get')->get('filter')) {
			$conditions['and'] = array();
			foreach(\Asgard\Core\App::get('get')->get('filter') as $key=>$value) {
				if($value)
					$conditions['and']["`$key` LIKE ?"] = '%'.$value.'%';
			}
		}

		$pagination = $_entity::where($conditions);
		
		if(isset($this->_orderby))
			$pagination->orderBy($this->_orderby);

		$this->orm = $pagination;

		$definition->trigger('asgardadmin_index', array($this));

		$this->orm->paginate(
			\Asgard\Core\App::get('get')->get('page', 1),
			10
		);
		$this->$_plural = $this->orm->get();
		$this->paginator = $this->orm->getPaginator();
	}
	
	/**
	@Route(':id/edit')
	*/
	public function editAction($request) {
		$_singular = $this->_singular;
		$_entity = $this->_entity;
		
		if(!($this->{$_singular} = $_entity::load($request['id'])))
			throw new \Asgard\Core\Exceptions\NotFoundException;
		$this->original = clone $this->{$_singular};

		$this->form = $this->formConfigure($this->{$_singular});
	
		if($this->form->isSent()) {
			try {
				$this->form->save();
				\Asgard\Core\App::get('flash')->addSuccess($this->_messages['modified']);
				if(\Asgard\Core\App::get('post')->has('send'))
					return \Asgard\Core\App::get('server')->get('HTTP_REFERER') !== \Asgard\Core\App::get('url')->full()
						?
						$this->response->back()
						:$this->response->redirect($this->url_for('index'));
			} catch(\Asgard\Form\FormException $e) {
				\Asgard\Core\App::get('flash')->addError($this->form->getGeneralErrors());
				$this->response->setCode(400);
			}
		}
		elseif(!$this->form->uploadSuccess()) {
			\Asgard\Core\App::get('flash')->addError(__('Data exceeds upload size limit. Maybe your file is too heavy.'));
			$this->response->setCode(400);
		}
		
		$this->setRelativeView('form.php');
	}
	
	/**
	@Route('new')
	*/
	public function newAction($request) {
		$_singular = $this->_singular;
		$_entity = $this->_entity;
		
		$this->{$_singular} = new $_entity;
		$this->original = clone $this->{$_singular};
	
		$this->form = $this->formConfigure($this->{$_singular});
	
		if($this->form->isSent()) {
			try {
				$this->form->save();
				\Asgard\Core\App::get('flash')->addSuccess($this->_messages['created']);
				if(\Asgard\Core\App::get('post')->has('send'))
					return \Asgard\Core\App::get('server')->get('HTTP_REFERER') !== \Asgard\Core\App::get('url')->full()
						? $this->response->back()
						:$this->response->redirect($this->url_for('index'));
				else
					return $this->response->redirect($this->url_for('edit', array('id'=>$this->{$_singular}->id)));
			} catch(\Asgard\Form\FormException $e) {
				\Asgard\Core\App::get('flash')->addError($this->form->getGeneralErrors());
				$this->response->setCode(400);
			}
		}
		elseif(!$this->form->uploadSuccess()) {
			\Asgard\Core\App::get('flash')->addError(__('Data exceeds upload size limit. Maybe your file is too heavy.'));
			$this->response->setCode(400);
		}
		
		$this->setRelativeView('form.php');
	}
	
	/**
	@Route(':id/delete')
	*/
	public function deleteAction($request) {
		$_entity = $this->_entity;
		
		!$_entity::destroyOne($request['id']) ?
			\Asgard\Core\App::get('flash')->addError($this->_messages['unexisting']) :
			\Asgard\Core\App::get('flash')->addSuccess($this->_messages['deleted']);
			
		return $this->response->redirect($this->url_for('index'));
	}
	
	public static function addHook($hook) {
		static::$_hooks[] = $hook;
		
		$hook['route'] = str_replace(':route', $hook['route'], \Asgard\Core\Controller::getRouteFor(array(get_called_class(), 'hooks')));
		$hook['controller'] = get_called_class();
		$hook['action'] = 'hooks';
		\Asgard\Core\App::get('resolver')->addRoute($hook);
	}
	
	/**
	@Route(value = 'hooks/:route', requirements = {
		route = {
			type = 'regex',
			regex = '.+'
		}	
	})
	@Test(false)
	*/
	public function hooksAction($request) {
		$_entity = $this->_entity;

		$controller = get_called_class();

		foreach(static::$_hooks as $hook) {
			if($results = \Asgard\Core\App::get('resolver')->matchWith($hook['route'], $request['route'])) {
				$newRequest = new \Asgard\Core\Request;
				$newRequest->parentController = $controller;
				$newRequest->params = array_merge($request->params, $results);
				return Controller::run($hook['controller'], $hook['action'], $newRequest);
			}
		}
		throw new \Asgard\Core\Exceptions\NotFoundException('Page not found');
	}
}