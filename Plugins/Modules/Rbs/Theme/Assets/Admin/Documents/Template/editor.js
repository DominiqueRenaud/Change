(function() {
	"use strict";

	function rbsDocumentEditorRbsThemeTemplateEdit(ArrayUtils, REST, $http, $compile, $templateCache) {
		return {
			restrict: 'A',
			require: '^rbsDocumentEditorBase',

			link: function(scope, element, attrs, editorCtrl) {
				scope.select = {websiteId: null};
				scope.blockList = [];
				scope.block = null;
				scope.blockParameters = null;

				scope.$on('Navigation.saveContext', function(event, args) {
					var data = {select: scope.select, blockList: scope.blockList,
						block: scope.block, blockParameters: scope.blockParameters};
					args.context.savedData('templateEditor', data);
				});

				scope.onRestoreContext = function(currentContext) {
					var data = currentContext.savedData('templateEditor');
					if (data) {
						scope.select = data.select;
						scope.block = data.block;
						scope.blockList = data.blockList;
						scope.blockParameters = data.blockParameters;
					}
				};

				scope.$on('blockSelected', function(event, args) {
					angular.forEach(scope.blockList, function(value, key) {
						if (value.block && value.block.name === args.name && value.name != args.name) {
							// If there is a block, load default parameters and open parameterize panel.
							if (value.block.name) {
								REST.blockInfo(args.name).then(function(blockInfo) {
									scope.blockList[key].name = args.name;
									var parameters = {};
									angular.forEach(blockInfo.parameters, function(parameter) {
										if (parameter.hasOwnProperty('defaultValue')) {
											parameters[parameter.name] = parameter.defaultValue;
										}
									});
									var block = scope.getBlockById(value.id);
									block.parameters = parameters;
									if (scope.canParametrizeBlock(value)) {
										scope.parametrizeBlock(key);
									}
								});
							}
						}
					});
				});

				function onRenderParams() {
					if (scope.block && scope.blockTemplate) {
						var template = scope.blockTemplate;

						scope.labelClass = 'col-lg-3';
						scope.controlsClass = 'col-lg-9';
						$http.get(template, {cache: $templateCache}).success(function (html) {
							html = $(html);
							html.find('rbs-document-picker-single')
								.attr('data-navigation-block-id', scope.block.id)
								.each(function () {
									var el = $(this);
									el.attr('property-label',
										el.attr('property-label') + ' (' + scope.block.label + ')');
								});
							$compile(html)(scope, function (clone) {
								element.find('[data-role="blockParametersContainer"]').append(clone);
								onRenderTemplateParams();
							});
						});
					}
				}

				function onRenderTemplateParams() {
					if (!scope.block || !scope.blockParameters) {
						return;
					}
					var blockType = {};
					var blocName = scope.block.name;
					var template = null;
					angular.forEach(scope.blockList, function(value) {
						if (value.block && value.block.name === blocName) {
							blockType = value.block;
							template = value.block.template;
						}
					});

					if (!template) {
						return;
					}

					element.find('[data-role="templateBlockParametersContainer"]').html('');

					var templateURL;
					var fullyQualifiedTemplateName = scope.blockParameters['fullyQualifiedTemplateName'];
					if (angular.isString(fullyQualifiedTemplateName) && fullyQualifiedTemplateName.length) {
						templateURL = template + '?fullyQualifiedTemplateName=' + fullyQualifiedTemplateName;
					}
					else if (angular.isObject(blockType['defaultTemplate']) && blockType['defaultTemplate']['hasParameter']) {
						templateURL = template + '?fullyQualifiedTemplateName=default:default';
					}

					if (templateURL) {
						$http.get(templateURL, {cache: $templateCache}).success(function (html) {
							html = $(html);
							html.find('rbs-document-picker-single')
								.attr('data-navigation-block-id', scope.block.id)
								.each(function () {
									var el = $(this);
									el.attr('property-label',
										el.attr('property-label') + ' (' + scope.block.label + ')');
								});

							$compile(html)(scope, function (clone) {
								element.find('[data-role="templateBlockParametersContainer"]').append(clone);
							});
						});
					}
				}

				scope.$watch('blockParameters.fullyQualifiedTemplateName', function (templateName) {
					onRenderTemplateParams();
				});

				scope.$watch('blockTemplate', function (templateName) {
					onRenderParams();
				});

				scope.onLoad = function() {
					if (!angular.isObject(scope.document.editableContent) || angular.isArray(scope.document.editableContent)) {
						scope.document.editableContent = {}
					}
					if (!angular.isObject(scope.document.contentByWebsite) || angular.isArray(scope.document.contentByWebsite)) {
						scope.document.contentByWebsite = {}
					}
				};

				scope.onReady = function() {
					if (scope.blockList.length == 0) {
						scope.buildBlockList();
					}
				};

				scope.onReload = function() {
					scope.block = null;
					scope.blockTemplate = null;
					scope.blockParameters = null;
					scope.select = {websiteId: null};
					scope.buildBlockList();
				};

				scope.buildBlockList = function() {
					var editableContent = scope.document.editableContent,
						contentByWebsite = scope.document.contentByWebsite,
						blockList = [], webBlocks = {}, byWebsite, row;

					byWebsite = scope.select.websiteId != null;

					if (byWebsite && contentByWebsite.hasOwnProperty(scope.select.websiteId)) {
						webBlocks = contentByWebsite[scope.select.websiteId];
					}

					angular.forEach(editableContent, function(value, key) {
						if (value.type == 'block') {
							row = {id: value.id, name: value.name, override: false, block: {name: ''}};
							if (webBlocks.hasOwnProperty(key)) {
								row.name = webBlocks[key].name;
								row.override = true
							}
							blockList.push(row);
						}
					});
					scope.blockList = blockList;
				};

				scope.$watch('select.websiteId', function(newValue, oldValue) {
					if (newValue !== oldValue) {
						scope.buildBlockList();
					}
				});

				scope.isEditorRow = function(row) {
					return row.parameters;
				};

				scope.inEditMode = function() {
					return scope.block !== null;
				};

				scope.canChangeBlockName = function(row) {
					return !(scope.inEditMode() || (scope.select.websiteId && !row.override));
				};

				scope.closeBlock = function(index) {
					scope.blockTemplate = null;
					scope.block = null;
					scope.blockParameters = null;
					scope.blockList.splice(index, 1);
				};

				scope.getBlockById = function(id) {
					var blockList;
					if (scope.select.websiteId) {
						if (scope.document.contentByWebsite.hasOwnProperty(scope.select.websiteId)) {
							blockList = scope.document.contentByWebsite[scope.select.websiteId];
						}
					}
					else {
						blockList = scope.document.editableContent;
					}
					if (blockList && blockList.hasOwnProperty(id)) {
						return blockList[id];
					}
					return null;
				};

				scope.canParametrizeBlock = function(row) {
					return row.block && row.block.template && scope.getBlockById(row.id) != null;
				};

				scope.parametrizeBlock = function(index) {
					var row = scope.blockList[index];

					scope.block = scope.getBlockById(row.id);
					scope.blockTemplate = row.block.template;

					if (row.block && row.block.hasOwnProperty('name')) {
						if (row.name != row.block.name) {
							row.name = row.block.name;
							scope.block.parameters = {};
						}
					}

					scope.block.name = row.name;
					if (!angular.isObject(scope.block.parameters) || angular.isArray(scope.block.parameters)) {
						scope.block.parameters = {};
					}

					scope.blockParameters = scope.block.parameters;
					if (!scope.blockParameters.hasOwnProperty('TTL')) {
						scope.blockParameters.TTL = 60;
					}
					scope.blockList.splice(index + 1, 0, {parameters: row.id, template: row.block.template});
				};

				scope.canEmptyBlock = function(row) {
					return row.name != '' && scope.getBlockById(row.id) != null;
				};

				scope.emptyBlock = function(row) {
					var block = scope.getBlockById(row.id);
					block.name = '';
					block.parameters = {};
					row.name = '';
					row.block = {};
				};

				scope.canOverrideBlock = function(row) {
					if (scope.select.websiteId) {
						return !row.override;
					}
					return false;
				};

				scope.addBlockOverride = function(row) {
					var block = {};
					angular.copy(scope.document.editableContent[row.id], block);
					var contentByWebsite = scope.document.contentByWebsite;
					if (!contentByWebsite.hasOwnProperty(scope.select.websiteId)) {
						contentByWebsite[scope.select.websiteId] = {};
					}
					contentByWebsite[scope.select.websiteId][row.id] = block;
					row.override = true;
				};

				scope.canRemoveOverrideBlock = function(row) {
					if (scope.select.websiteId) {
						return row.override;
					}
					return false;
				};

				scope.removeBlockOverride = function(row) {
					var contentByWebsite = scope.document.contentByWebsite;
					delete contentByWebsite[scope.select.websiteId][row.id];
					var block = scope.document.editableContent[row.id];
					row.block = {};
					row.name = block.name;
					row.override = false;
				};

				scope.hasTTL = function(seconds) {
					return scope.blockParameters.TTL == seconds;
				};

				scope.setTTL = function(seconds) {
					scope.blockParameters.TTL = seconds;
				};

				scope.isVisibleFor = function(device) {
					if (device == 'raw') {
						return scope.block.visibility == 'raw';
					}
					else if (scope.block.visibility == 'raw') {
						return false;
					}
					return (!scope.block.visibility || scope.block.visibility.indexOf(device) !== -1);
				};

				scope.toggleVisibility = function(device) {
					var value = !scope.isVisibleFor(device), splat;

					if (device == 'raw') {
						if (value) {
							scope.block.visibility = device;
						}
						else {
							delete scope.block.visibility;
						}
						return;
					}
					else if (scope.block.visibility == 'raw') {
						delete scope.block.visibility;
					}

					if (scope.block.visibility) {
						splat = scope.block.visibility.split('');
						if (ArrayUtils.inArray(device, splat) !== -1 && !value) {
							ArrayUtils.removeValue(splat, device);
						}
						else if (ArrayUtils.inArray(device, splat) === -1 && value) {
							splat.push(device);
						}
						splat.sort();
						if (splat.join('') == '') {
							delete scope.block.visibility;
						}
						else {
							scope.block.visibility = splat.join('');
						}
					}
					else {
						if (value) {
							scope.block.visibility = device;
						}
						else {
							switch (device) {
								case 'X' :
									scope.block.visibility = 'SML';
									break;
								case 'S' :
									scope.block.visibility = 'XML';
									break;
								case 'M' :
									scope.block.visibility = 'XSL';
									break;
								case 'L' :
									scope.block.visibility = 'XSM';
									break;
							}
						}
					}
				};
			}
		};
	}

	rbsDocumentEditorRbsThemeTemplateEdit.$inject = ['RbsChange.ArrayUtils', 'RbsChange.REST', '$http', '$compile', '$templateCache'];
	angular.module('RbsChange').directive('rbsDocumentEditorRbsThemeTemplateEdit', rbsDocumentEditorRbsThemeTemplateEdit);
})();