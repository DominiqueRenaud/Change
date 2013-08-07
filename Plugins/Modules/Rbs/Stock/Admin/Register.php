<?php
namespace Rbs\Stock\Admin;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Rbs\Admin\Event;
/**
 * @name \Rbs\Stock\Admin\Register
 */
class Register implements ListenerAggregateInterface
{
	/**
	 * Attach one or more listeners
	 * Implementors may add an optional $priority argument; the EventManager
	 * implementation will pass this to the aggregate.
	 * @param EventManagerInterface $events
	 */
	public function attach(EventManagerInterface $events)
	{
		$events->attach(Event::EVENT_RESOURCES, function(Event $event)
		{
			/*$header =  array('<link href="Rbs/Price/css/admin.css" rel="stylesheet"/>');
			$event->setParam('header', array_merge($event->getParam('header'), $header));*/

			$body = array('
	<script type="text/javascript" src="Rbs/Stock/js/admin.js">​</script>
	<script type="text/javascript" src="Rbs/Stock/js/directives.js">​</script>
	<script type="text/javascript" src="Rbs/Stock/Sku/controllers.js"></script>
	<script type="text/javascript" src="Rbs/Stock/Sku/editor.js"></script>
	<script type="text/javascript" src="Rbs/Stock/InventoryEntry/controllers.js"></script>
	<script type="text/javascript" src="Rbs/Stock/InventoryEntry/editor.js"></script>');
			$event->setParam('body', array_merge($event->getParam('body'), $body));

			$i18nManager = $event->getManager()->getApplicationServices()->getI18nManager();
			$menu = array(
				'entries' => array(
					array('label' => $i18nManager->trans('m.rbs.stock.admin.js.module-name', array('ucf'))
					, 'url' => 'Rbs/Stock', 'section' => 'ecommerce', 'keywords' => $i18nManager->trans('m.rbs.stock.admin.js.module-keywords'))
				));

			$event->setParam('menu', \Zend\Stdlib\ArrayUtils::merge($event->getParam('menu', array()), $menu));
		});
	}

	/**
	 * Detach all previously attached listeners
	 * @param EventManagerInterface $events
	 */
	public function detach(EventManagerInterface $events)
	{
		// TODO: Implement detach() method.
	}
}
