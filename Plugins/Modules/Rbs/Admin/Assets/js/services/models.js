(function() {

	"use strict";

	var app = angular.module('RbsChange');

	/**
	 * Models service.
	 */
	app.provider('RbsChange.Models', function RbsModelsProvider() {
		var allModels = [], filterStack = [], loaded = false;

		this.$get = ['$filter', 'RbsChange.REST', 'RbsChange.ArrayUtils', 'RbsChange.i18n',
			function($filter, REST, ArrayUtils, i18n) {
				function ChangeModel() {
					this.META$ = {
						'loaded': false,
						'links': {}
					};
				}

				function getByFilter(filter) {
					var models = [];
					if (allModels.length) {
						applyFilter(models, filter)
					}
					else {
						filterStack.push(function() {applyFilter(models, filter);});

						if (!loaded) {
							loaded = true;
							var loadedModels = [];
							REST.call(REST.getBaseUrl('Rbs/ModelsInfo')).then(function(results) {
								angular.forEach(results, function(result) {
									var model = new ChangeModel();
									angular.extend(model, result);
									model.META$.loaded = true;
									loadedModels.push(model);
								});
								allModels = $filter('orderBy')(loadedModels, ['plugin', 'label']);
								angular.forEach(filterStack, function(func) {
									func();
								});
							});
						}
					}
					return models;
				}

				function getAll() {
					return getByFilter();
				}

				function applyFilter(models, filter) {
					if (!angular.isObject(filter)) {
						angular.forEach(allModels, function(testModel) {
							models.push(testModel);
						});
						return;
					}

					angular.forEach(allModels, function(testModel) {
						var valid = true;
						angular.forEach(filter, function(value, attr) {
							if (testModel.hasOwnProperty(attr)) {
								if (angular.isArray(value)) {
									if (angular.isArray(testModel[attr])) {
										if (ArrayUtils.intersect(testModel[attr], value).length == 0) {
											valid = false;
										}
									}
									else if (ArrayUtils.inArray(testModel[attr], value) == -1) {
										valid = false;
									}
								}
								else if (angular.isArray(testModel[attr])) {
									if (ArrayUtils.inArray(value, testModel[attr]) == -1) {
										valid = false;
									}
								}
								else if (testModel[attr] != value) {
									valid = false;
								}
							}
							else {
								valid = false;
							}
						});
						if (valid) {
							models.push(testModel);
						}
					});
				}

				function getModelLabel(name) {
					if (allModels.length) {
						for (var i = 0; i < allModels.length; i++) {
							if (allModels[i].name == name) {
								return allModels[i].label;
							}
						}
					}
					getByFilter({ name: name });
					var labArray = name.toLowerCase().split('_');
					return $filter('ucf')(i18n.trans('m.' + labArray[0] + '.' + labArray[1] + '.documents.' + labArray[2]));
				}

				// Public API.
				return {
					getAll: getAll,
					getByFilter: getByFilter,
					getModelLabel: getModelLabel
				};
			}
		]
	});
})();