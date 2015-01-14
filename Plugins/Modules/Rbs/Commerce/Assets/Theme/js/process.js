(function(jQuery) {
	"use strict";
	var app = angular.module('RbsChangeApp');

	function rbsCommerceProcess($rootScope, $compile, AjaxAPI, $timeout) {
		var cacheCartDataKey = 'cartData';

		return {
			restrict: 'A',
			templateUrl: '/rbsCommerceProcess.tpl',
			scope: {},
			controller: ['$scope', '$element', function(scope, elem) {
				var self = this;
				var processInfo = null;
				scope.currentStep = null;
				scope.steps = ['identification', 'shipping', 'payment', 'confirm'];
				scope.processData = {};
				scope.loading = false;

				function setObjectData(cartData) {
					scope.cartData = cartData;
					if (cartData.processData) {
						processInfo = cartData.processData;
						if (!scope.currentStep && processInfo.common && processInfo.common.currentStep) {
							self.setCurrentStep(processInfo.common.currentStep);
						}
					}
					if (self.showPrices()) {
						scope.currencyCode = cartData.common.currencyCode;
					}
					else {
						scope.currencyCode = null;
					}
					$rootScope.$broadcast('rbsRefreshCart', { 'cart': cartData });
				}

				this.loading = function(loading) {
					if (angular.isDefined(loading) && scope.loading != (loading == true)) {
						scope.loading = (loading == true);
						if (scope.loading) {
							AjaxAPI.openWaitingModal();
						}
						else {
							AjaxAPI.closeWaitingModal();
						}
					}
					return scope.loading;
				};

				function getCartParams() {
					return {
						detailed: true,
						visualFormats: scope.parameters['imageFormats'],
						URLFormats: 'canonical'
					};
				}

				/**
				 * In alternative implementation of this controller, this method should return an HttpPromise or null (when there
				 * is no AJAX call to do). So make sure to check if there is a returned promise before calling methods on it.
				 */
				this.loadObjectData = function(withProcessData) {
					var controller = this;
					controller.loading(true);
					var params = getCartParams();
					if (withProcessData) {
						params.dataSets = "process";
					}
					var request = AjaxAPI.getData('Rbs/Commerce/Cart', null, params);
					request.success(function(data) {
						var cartData = data.dataSets;
						if (cartData && !angular.isArray(cartData)) {
							setObjectData(cartData);
						}
						controller.loading(false);
					}).error(function(data, status) {
						console.log('loadObjectData error', data, status);
						controller.loading(false);
					});
					return request;
				};

				/**
				 * In alternative implementation of this controller, this method should return an HttpPromise or null (when there
				 * is no AJAX call to do). So make sure to check if there is a returned promise before calling methods on it.
				 */
				this.updateObjectData = function(actions) {
					var controller = this;
					controller.loading(true);
					var request = AjaxAPI.putData('Rbs/Commerce/Cart', actions, getCartParams());
					request.success(function(data) {
						var cartData = data.dataSets;
						if (cartData && !angular.isArray(cartData)) {
							setObjectData(cartData);
						}
						controller.loading(false);
					}).error(function(data, status) {
						console.log('updateObjectData error', data, status);
						controller.loading(false);
					});
					return request;
				};

				this.getObjectData = function() {
					return scope.cartData;
				};

				this.showPrices = function(asObject) {
					var showPrices = (scope.parameters &&
					(scope.parameters.displayPricesWithTax || scope.parameters.displayPricesWithoutTax));
					if (asObject && showPrices) {
						return {
							currencyCode: this.getCurrencyCode(),
							displayPricesWithTax: this.parameters('displayPricesWithTax'),
							displayPricesWithoutTax: this.parameters('displayPricesWithoutTax')
						}
					}
					return showPrices;
				};

				this.getCurrencyCode = function() {
					return scope.currencyCode;
				};

				this.parameters = function(name) {
					if (scope.parameters) {
						if (angular.isUndefined(name)) {
							return scope.parameters;
						}
						else {
							return scope.parameters[name];
						}
					}
					return null;
				};

				this.getProcessInfo = function() {
					return processInfo;
				};

				this.replaceChildren = function(parentNode, scope, html) {
					var collection = parentNode.children();
					collection.each(function() {
						var isolateScope = angular.element(this).isolateScope();
						if (isolateScope) {
							isolateScope.$destroy();
						}
					});
					collection.remove();
					if (html != '') {
						$compile(html)(scope, function(clone) {
							parentNode.append(clone);
						});
					}
				};

				scope.$watch('cartData', function(cartData, oldCartData) {
						if (cartData) {
							if (!scope.currentStep) {
								self.nextStep();
							}
						}
					}
				);

				this.nextStep = function() {
					this.setCurrentStep(this.getNextStep(scope.currentStep));
				};

				this.getNextStep = function(step) {
					if (!step) {
						return scope.steps[0];
					}
					for (var i = 0; i < scope.steps.length - 1; i++) {
						if (step == scope.steps[i]) {
							return scope.steps[i + 1];
						}
					}
					return null;
				};

				function locateCurrentStep() {
					var offset = jQuery('#' + scope.currentStep).offset();
					if (offset && offset.hasOwnProperty('top')) {
						jQuery('html, body').animate({ scrollTop: offset.top - 20 }, 500);
					}
					else if (scope.currentStep) {
						$timeout(locateCurrentStep, 100);
					}
				}

				this.setCurrentStep = function(currentStep) {
					scope.currentStep = currentStep;
					var enabled = currentStep !== null, checked = enabled;
					for (var i = 0; i < scope.steps.length; i++) {
						var step = scope.steps[i];
						var stepProcessData = this.getStepProcessData(step);
						if (step == currentStep) {
							checked = false;
							stepProcessData.isCurrent = true;
							stepProcessData.isChecked = checked;
							stepProcessData.isEnabled = enabled;
							enabled = false;
						}
						else {
							stepProcessData.isCurrent = false;
							stepProcessData.isChecked = checked;
							stepProcessData.isEnabled = enabled;
						}
					}
					if (currentStep) {
						$timeout(locateCurrentStep, 100);
					}
				};

				this.getStepProcessData = function(step) {
					if (step === null) {
						return { name: step, isCurrent: false, isEnabled: false, isChecked: false };
					}
					if (!angular.isObject(scope.processData[step])) {
						scope.processData[step] = { name: step, isCurrent: false, isEnabled: false, isChecked: false };
					}
					return scope.processData[step];
				};

				var cacheKey = elem.attr('data-cache-key');
				scope.parameters = AjaxAPI.getBlockParameters(cacheKey);

				var cartData = AjaxAPI.globalVar(cacheCartDataKey);
				if (!cartData) {
					this.loadObjectData(true);
				}
				else {
					setObjectData(cartData);
				}
			}],

			link: function(scope, elem, attrs, controller) {
				scope.showPrices = controller.showPrices();
				scope.isStepEnabled = function(step) {
					return controller.getStepProcessData(step).isEnabled;
				};
				scope.isStepChecked = function(step) {
					return controller.getStepProcessData(step).isChecked;
				};
			}
		}
	}

	rbsCommerceProcess.$inject = ['$rootScope', '$compile', 'RbsChange.AjaxAPI', '$timeout'];
	app.directive('rbsCommerceProcess', rbsCommerceProcess);

	function rbsCommerceCartLines() {
		return {
			restrict: 'A',
			templateUrl: '/rbsCommerceCartLines.tpl',
			require: '^rbsCommerceProcess',
			scope: {
				cartData: "=",
				getLineDirectiveName: "="
			},
			link: function(scope, elem, attrs, processController) {
				scope.showPrices = processController.showPrices();

				function redrawLines() {
					var linesContainer = elem.find('[data-role="cart-lines"]');
					var directiveName = angular.isFunction(scope.getLineDirectiveName) ? scope.getLineDirectiveName : function(line) {
						return 'rbs-commerce-process-line-default';
					};
					var lines = scope.cartData.lines;
					var html = [];
					angular.forEach(lines, function(line, idx) {
						html.push('<tr data-line="cartData.lines[' + idx + ']" ' + directiveName(line) + '=""></tr>');
					});
					processController.replaceChildren(linesContainer, scope, html.join(''));
				}

				scope.$watch('cartData', function(cartData) {
						if (cartData) {
							redrawLines();
						}
					}
				);
			}
		}
	}

	app.directive('rbsCommerceCartLines', rbsCommerceCartLines);

})(window.jQuery);