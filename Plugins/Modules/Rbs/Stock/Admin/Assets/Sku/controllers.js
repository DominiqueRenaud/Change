(function ()
{
	"use strict";

	var app = angular.module('RbsChange');

	/**
	 * Controller for list.
	 *
	 * @param $scope
	 * @param Breadcrumb
	 * @param MainMenu
	 * @param i18n
	 * @constructor
	 */
	function ListController($scope, Breadcrumb, MainMenu, i18n)
	{
		Breadcrumb.resetLocation([
			[i18n.trans('m.rbs.stock.admin.js.module-name | ucf'), "Rbs/Stock"],
			[i18n.trans('m.rbs.stock.admin.js.sku-list | ucf'), "Rbs/Stock/Sku"]
		]);

		MainMenu.loadModuleMenu('Rbs_Stock');
	}

	ListController.$inject = ['$scope', 'RbsChange.Breadcrumb', 'RbsChange.MainMenu', 'RbsChange.i18n'];
	app.controller('Rbs_Stock_Sku_ListController', ListController);

	/**
	 * Controller for form.
	 *
	 * @param $scope
	 * @param Breadcrumb
	 * @param FormsManager
	 * @param i18n
	 * @constructor
	 */
	function FormController($scope, Breadcrumb, FormsManager, i18n)
	{
		Breadcrumb.setLocation([
			[i18n.trans('m.rbs.stock.admin.js.module-name | ucf'), "Rbs/Stock"],
			[i18n.trans('m.rbs.stock.admin.js.sku-list | ucf'), "Rbs/Stock/Sku"]
		]);
		FormsManager.initResource($scope, 'Rbs_Stock_Sku');
	}

	FormController.$inject = ['$scope', 'RbsChange.Breadcrumb', 'RbsChange.FormsManager', 'RbsChange.i18n'];
	app.controller('Rbs_Stock_Sku_FormController', FormController);
})();