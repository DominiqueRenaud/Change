(function (jQuery) {
	"use strict";

	var app = angular.module('RbsChange');

	app.directive('rbsCartFilterDefinitions', function() {
		return {
			restrict: 'A',
			require: '^rbsDocumentFilterContainer',
			templateUrl : 'Rbs/Commerce/cartFiltersDefinition.twig',
			replace: true,
			link: function(scope, element, attrs, filterContainerCtrl) {
				if (attrs.hasOwnProperty('definitions')) {
					var definitions = angular.fromJson(attrs['definitions']);
					angular.forEach(definitions, function(definition) {
						if (definition.hasOwnProperty('name') &&
							definition.hasOwnProperty('directiveName') &&
							definition.hasOwnProperty('config')) {
							if (!definition.hasOwnProperty('parameters')) {
								definition.parameters = {};
							}
							definition.label = definition.config.listLabel || definition.config.label;
							definition.group = definition.config.group;
							filterContainerCtrl.addFilterDefinition(definition);
						}
					});
				}
			}
		};
	});

	app.directive('rbsCommerceFilterHasCreditNote', function() {
		return {
			restrict: 'A',
			require: '^rbsDocumentFilterContainer',
			templateUrl : 'Rbs/Commerce/filter-has-credit-note.twig',
			scope: {
				filter : '=', contextKey: "@"
			},
			link: function(scope, element, attrs, containerController) {
				containerController.linkNode(scope);

				scope.filter.parameters.operator = 'gt';

				if (!scope.filter.parameters.hasOwnProperty('value')){
					scope.filter.parameters.value = 0.0;
				}

				scope.isConfigured = function() {
					return true;
				};

				scope.$on('countAllFilters', function(event, args) {
					args.all++;
					if (scope.isConfigured()) {
						args.configured++;
					}
				});
			}
		};
	});

	app.directive('rbsCommerceFilterBrandProductCount', function() {
		return {
			restrict: 'A',
			require: '^rbsDocumentFilterContainer',
			templateUrl : 'Rbs/Commerce/filter-brand-count.twig',
			scope: {
				filter : '=', contextKey: "@"
			},
			link: function(scope, element, attrs, containerController) {
				containerController.linkNode(scope);
				if (!scope.filter.parameters.hasOwnProperty('operator')) {
					scope.filter.parameters.operator = 'gte';
				}
				if (!scope.filter.parameters.hasOwnProperty('quantity')) {
					scope.filter.parameters.quantity = 1;
				}
				if (!scope.filter.parameters.hasOwnProperty('brand')) {
					scope.filter.parameters.brand = 0;
				}
				scope.isConfigured = function() {
					var op = scope.filter.parameters.operator;
					return op && scope.filter.parameters.quantity && scope.filter.parameters.brand ;
				};

				scope.$on('countAllFilters', function(event, args) {
					args.all++;
					if (scope.isConfigured()) {
						args.configured++;
					}
				});
			}
		};
	});

	app.directive('rbsCommerceFilterSingleProductCount', function() {
		return {
			restrict: 'A',
			require: '^rbsDocumentFilterContainer',
			templateUrl : 'Rbs/Commerce/filter-product-count.twig',
			scope: {
				filter : '=', contextKey: "@"
			},
			link: function(scope, element, attrs, containerController) {
				containerController.linkNode(scope);
				if (!scope.filter.parameters.hasOwnProperty('operator')) {
					scope.filter.parameters.operator = 'gte';
				}
				if (!scope.filter.parameters.hasOwnProperty('quantity')) {
					scope.filter.parameters.quantity = 1;
				}
				if (!scope.filter.parameters.hasOwnProperty('product')) {
					scope.filter.parameters.product = 0;
				}
				scope.isConfigured = function() {
					var op = scope.filter.parameters.operator;
					return op && scope.filter.parameters.quantity && scope.filter.parameters.product ;
				};

				scope.$on('countAllFilters', function(event, args) {
					args.all++;
					if (scope.isConfigured()) {
						args.configured++;
					}
				});
			}
		};
	});
})(window.jQuery);