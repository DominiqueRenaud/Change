<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Theme\Documents;

/**
 * @name \Rbs\Theme\Documents\Theme
 */
class Theme extends \Compilation\Rbs\Theme\Documents\Theme implements \Change\Presentation\Interfaces\Theme
{
	/**
	 * @var \Change\Presentation\Themes\ThemeManager
	 */
	protected $themeManager;

	/**
	 * @var \Change\Plugins\PluginManager
	 */
	protected $pluginManager;

	/**
	 * @return \Change\Workspace
	 */
	protected function getWorkspace()
	{
		return $this->getApplication()->getWorkspace();
	}

	/**
	 * @param \Change\Events\Event $event
	 */
	public function onDefaultInjection(\Change\Events\Event $event)
	{
		parent::onDefaultInjection($event);
		$applicationServices = $event->getApplicationServices();
		$this->themeManager = $applicationServices->getThemeManager();
		$this->pluginManager = $applicationServices->getPluginManager();
	}

	/**
	 * @param \Change\Presentation\Themes\ThemeManager $themeManager
	 */
	public function setThemeManager(\Change\Presentation\Themes\ThemeManager $themeManager)
	{
		$this->themeManager = $themeManager;
	}

	/**
	 * @throws \RuntimeException
	 * @return \Change\Presentation\Themes\ThemeManager
	 */
	protected function getThemeManager()
	{
		if ($this->themeManager === null)
		{
			throw new \RuntimeException('themeManager not set', 999999);
		}
		return $this->themeManager;
	}

	/**
	 * @throws \RuntimeException
	 * @return \Change\Plugins\PluginManager
	 */
	public function getPluginManager()
	{
		if ($this->pluginManager === null)
		{
			throw new \RuntimeException('pluginManager not set', 999999);
		}
		return $this->pluginManager;
	}

	/**
	 * @var string $templateBasePath
	 */
	protected $templateBasePath;

	/**
	 * @return string
	 */
	public function getTemplateBasePath()
	{
		if ($this->templateBasePath === null)
		{
			list ($themeVendor, $shortThemeName) = explode('_', $this->getName());
			$this->templateBasePath = $this->getWorkspace()->compilationPath('Themes', $themeVendor, $shortThemeName);
			if ($this->getApplication()->inDevelopmentMode() && $this->themeManager)
			{
				$pluginManager = $this->getPluginManager();
				$plugin = $pluginManager->getTheme($themeVendor, $shortThemeName);
				$this->themeManager->installPluginTemplates($plugin, $this);
			}
		}
		return $this->templateBasePath;
	}

	/**
	 * @return string
	 */
	public function getAssetBasePath()
	{
		list ($themeVendor, $shortThemeName) = explode('_', $this->getName());
		if ($themeVendor == 'Project')
		{
			return $this->getWorkspace()->projectThemesPath('Project', $shortThemeName, 'Assets');
		}
		return $this->getWorkspace()->pluginsThemesPath($themeVendor, $shortThemeName, 'Assets');
	}

	/**
	 * @param string $moduleName
	 */
	public function removeTemplatesContent($moduleName)
	{
		$basePath = $this->getWorkspace()->composePath($this->getTemplateBasePath(), $moduleName);
		\Change\Stdlib\File::rmdir($basePath);
	}

	/**
	 * @param string $moduleName
	 * @param string $pathName
	 * @param string $content
	 * @return void
	 */
	public function installTemplateContent($moduleName, $pathName, $content)
	{
		$path = $this->getWorkspace()->composePath($this->getTemplateBasePath(), $moduleName, $pathName);
		\Change\Stdlib\File::mkdir(dirname($path));
		file_put_contents($path, $content);
	}

	/**
	 * @param string $moduleName
	 * @param string $fileName
	 * @return string
	 */
	public function getTemplateRelativePath($moduleName, $fileName)
	{
		return $this->getWorkspace()->composePath($moduleName, $fileName);
	}

	/**
	 * @param string $name
	 * @return \Change\Presentation\Interfaces\Template
	 */
	public function getPageTemplate($name)
	{
		$pageTemplate = null;
		if (is_numeric($name))
		{
			$pageTemplate = $this->getDocumentManager()->getDocumentInstance($name, 'Rbs_Theme_Template');
		}

		if ($pageTemplate === null)
		{
			$parentTheme = ($this->getParentTheme()) ? $this->getParentTheme() : $this->getThemeManager()->getDefault();
			return $parentTheme->getPageTemplate($name);
		}
		return $pageTemplate;
	}

	/**
	 * @param string $resourcePath
	 * @return \Change\Presentation\Interfaces\ThemeResource
	 */
	public function getResource($resourcePath)
	{
		$path = $this->getWorkspace()->composePath($this->getAssetBasePath(), $resourcePath);

		$res = null;
		if (substr($resourcePath, -4) === '.css')
		{
			$res = new \Rbs\Theme\Std\CssFileResource($path, []);
		}
		if ($res === null)
		{
			$res = new \Change\Presentation\Themes\FileResource($path);
		}

		if ($res->isValid())
		{
			return $res;
		}

		$parentTheme = ($this->getParentTheme()) ? $this->getParentTheme() : $this->getThemeManager()->getDefault();
		return $parentTheme->getResource($resourcePath);
	}

	/**
	 * @return array
	 */
	public function getAssetConfiguration()
	{
		$resource = $this->getResourceFilePath('assets.json');
		if (file_exists($resource))
		{
			$resourceConfig = json_decode(\Change\Stdlib\File::read($resource), true);
			if (is_array($resourceConfig))
			{
				return $resourceConfig;
			}
			$this->getApplication()->getLogging()->error("Invalid JSON file : " . $resource);
		}

		return [];
	}

	/**
	 * @param string $resourcePath
	 * @return string
	 */
	public function getResourceFilePath($resourcePath)
	{
		list ($themeVendor, $shortThemeName) = explode('_', $this->getName());
		if ($themeVendor == 'Project')
		{
			return $this->getWorkspace()->projectThemesPath($themeVendor, $shortThemeName, 'Assets', $resourcePath);
		}
		else
		{
			return $this->getWorkspace()->pluginsThemesPath($themeVendor, $shortThemeName, 'Assets', $resourcePath);
		}
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultUpdateRestResult(\Change\Documents\Events\Event $event)
	{
		parent::onDefaultUpdateRestResult($event);

		$document = $event->getDocument();
		if (!$document instanceof Theme)
		{
			return;
		}

		$restResult = $event->getParam('restResult');
		if ($restResult instanceof \Change\Http\Rest\V1\Resources\DocumentResult)
		{
			$restResult->removeRelAction('delete');
		}
		elseif ($restResult instanceof \Change\Http\Rest\V1\Resources\DocumentLink)
		{
			$restResult->removeRelAction('delete');
		}
	}
}