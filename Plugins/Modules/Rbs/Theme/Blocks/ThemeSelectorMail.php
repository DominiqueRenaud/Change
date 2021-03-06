<?php
/**
 * Copyright (C) 2014 Proximis
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Theme\Blocks;

/**
 * @name \Rbs\Theme\Blocks\ThemeSelectorMail
 */
class ThemeSelectorMail extends \Change\Presentation\Blocks\Standard\Block
{
	/**
	 * Event Params 'website', 'document', 'page'
	 * @api
	 * Set Block Parameters on $event
	 * @param \Change\Presentation\Blocks\Event $event
	 * @return \Change\Presentation\Blocks\Parameters
	 */
	protected function parameterize($event)
	{
		$parameters = parent::parameterize($event);
		$parameters->setLayoutParameters($event->getBlockLayout());

		$substitutions = $event->getParam('substitutions');
		if (is_array($substitutions) && isset($substitutions['themeName']))
		{
			$themeName = $substitutions['themeName'];
			$theme = $event->getApplicationServices()->getThemeManager()->getByName($themeName);
			if ($theme)
			{
				$event->getApplicationServices()->getThemeManager()->setCurrent($theme);
			}
			else
			{
				$event->getApplication()->getLogging()->warn(__METHOD__ . ' Unknown theme name: ' . $themeName);
			}
		}

		return $parameters;
	}

	/**
	 * Set $attributes and return a twig template file name OR set HtmlCallback on result
	 * @param \Change\Presentation\Blocks\Event $event
	 * @param \ArrayObject $attributes
	 * @return string|null
	 */
	protected function execute($event, $attributes)
	{
		return null;
	}
}