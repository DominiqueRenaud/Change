(function (jQuery)
{
	"use strict";

	function rbsDiscountFreeShippingFee ($routeParams, REST) {

		return {
			restrict : 'A',
			templateUrl : 'Rbs/Discount/freeShippingFee.twig',
			scope: {discount:'=', parameters:'='},
			link : function (scope, element, attrs) {
				if (!scope.parameters.hasOwnProperty('shippingMode')) {
					scope.parameters['shippingMode'] = null;
				}
			}
		}
	}

	rbsDiscountFreeShippingFee.$inject = ['$routeParams', 'RbsChange.REST'];
	angular.module('RbsChange').directive('rbsDiscountFreeShippingFee', rbsDiscountFreeShippingFee);


	function rbsDiscountRowsFixed ($routeParams, REST) {
		return {
			restrict : 'A',
			templateUrl : 'Rbs/Discount/rowsFixed.twig',
			scope: {discount:'=', parameters:'='},
			link : function (scope, element, attrs) {
				if (!scope.parameters.hasOwnProperty('amount')) {
					scope.parameters['amount'] = 10.0;
				}
				if (!scope.parameters.hasOwnProperty('withTax')) {
					scope.parameters['withTax'] = true;
				}
			}
		}
	}

	rbsDiscountRowsFixed.$inject = ['$routeParams', 'RbsChange.REST'];
	angular.module('RbsChange').directive('rbsDiscountRowsFixed', rbsDiscountRowsFixed);

	function rbsDiscountRowsPercent ($routeParams, REST) {
		return {
			restrict : 'A',
			templateUrl : 'Rbs/Discount/rowsPercent.twig',
			scope: {discount:'=', parameters:'='},
			link : function (scope, element, attrs) {
				if (!scope.parameters.hasOwnProperty('percent')) {
					scope.parameters['percent'] = 5;
				}
			}
		}
	}
	rbsDiscountRowsPercent.$inject = ['$routeParams', 'RbsChange.REST'];
	angular.module('RbsChange').directive('rbsDiscountRowsPercent', rbsDiscountRowsPercent);

	function rbsDiscountRowsCountReferenceBased ($routeParams, REST) {
		return {
			restrict : 'A',
			templateUrl : 'Rbs/Discount/rowsCountReferenceBased.twig',
			scope: {discount:'=', parameters:'='},
			link : function (scope, element, attrs) {
				if (!scope.parameters.hasOwnProperty('count')) {
					scope.parameters['count'] = 2;
				}
				if (!scope.parameters.hasOwnProperty('bonusCount')) {
					scope.parameters['bonusCount'] = 1;
				}
				if (!scope.parameters.hasOwnProperty('bonusOperator')) {
					scope.parameters['bonusOperator'] = "eq";
				}
				if (!scope.parameters.hasOwnProperty('productId')) {
					scope.parameters['productId'] = 0;
				}
				if (!scope.parameters.hasOwnProperty('multipleDiscount')) {
					scope.parameters['multipleDiscount'] = false;
				}
			}
		}
	}
	rbsDiscountRowsCountReferenceBased.$inject = ['$routeParams', 'RbsChange.REST'];
	angular.module('RbsChange').directive('rbsDiscountRowsCountReferenceBased', rbsDiscountRowsCountReferenceBased);

})(window.jQuery);