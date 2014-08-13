(function() {
	"use strict";

	function rbsSimpleformFormFieldEditor($compile) {
		return {
			restrict: 'A',
			link: function(scope, elm, attrs) {
				if (!angular.isObject(scope.document.parameters) || angular.isArray(scope.document.parameters)) {
					scope.document.parameters = {};
				}

				scope.$watch('document.fieldTypeCode', function(directiveName) {
					if (!directiveName) {
						elm.find('[data-role="fieldTypeConfig"]').empty();
					}
					else {
						var callback = function(element) {
							elm.find('[data-role="fieldTypeConfig"]').replaceWith(element);
						};
						directiveName = directiveName.replace(/_/g, '-').replace(/([a-z])([A-Z])/, '$1-$2').toLowerCase();
						var html = '<div data-role="fieldTypeConfig"><div ' + directiveName + '=""></div></div>';
						$compile(html)(scope, callback);
					}
				});
			}
		};
	}

	rbsSimpleformFormFieldEditor.$inject = ['$compile'];
	angular.module('RbsChange').directive('rbsSimpleformFormFieldEditor', rbsSimpleformFormFieldEditor);
})();