(function ()
{
	"use strict";

	var	app = angular.module('RbsChange'),
		INDEX_FUNCTION_CODE = 'Rbs_Website_Section';


	//-----------------------------------------------------------------------------------------------------------------


	app.directive('rbsRepeatCount', function ()
	{
		return {
			restrict : 'A',

			link : function (scope, iElement, iAttrs)
			{
				iAttrs.$observe('rbsRepeatCount', function (count)
				{
					var i;
					if (angular.isDefined(count))
					{
						count = parseInt(count, 10);
						if (isNaN(count) || count < 0) {
							throw new Error("Invalid value for Directive rbs-repeat-count: must be an integer >= 0");
						}

						if (count === 0) {
							iElement.remove();
						}
						else {
							for (i=0 ; i<(count-1) ; i++) {
								iElement.after(iElement.clone().removeAttr('rbs-repeat-count'));
							}
						}
					}
				});
			}
		};
	});



	/**
	 *
	 *
	 * @param $scope
	 * @param $routeParams
	 * @param $location
	 * @param REST
	 * @param Utils
	 * @constructor
	 */
	function HeaderController ($scope, $routeParams, REST, Utils)
	{
		$scope.currentWebsite = null;
		$scope.viewUrl = null;

		REST.treeChildren('Rbs/Website').then(function (root)
		{
			REST.treeChildren(root.resources[0], {column:['baseurl']}).then(function (websites)
			{
				$scope.websites = websites.resources;
				if (! $scope.currentWebsite) {
					$scope.currentWebsite = websites.resources[0];
				}

				$scope.$watch(function () { return $routeParams.view; }, function (view)
				{
					if (view) {
						$scope.view = view;
					}
				});

			});
		});
	}
	HeaderController.$inject = ['$scope', '$routeParams', 'RbsChange.REST', 'RbsChange.Utils'];
	app.controller('Rbs_Website_HeaderController', HeaderController);



	/**
	 *
	 * @param $scope
	 * @param $q
	 * @param $location
	 * @param ErrorFormatter
	 * @param i18n
	 * @param REST
	 * @param Query
	 * @param NotificationCenter
	 * @param Utils
	 * @param $cacheFactory
	 * @param Navigation
	 * @constructor
	 */
	function StructureController($scope, $q, ErrorFormatter, i18n, REST, Query, NotificationCenter, Utils, $cacheFactory, Navigation)
	{
		var cacheKeyPrefix = 'RbsWebsiteStructureData',
			cache,
			treeDb;

		function selectWebsite (website)
		{
			Navigation.setContext($scope, 'RbsWebsiteStructure').then(function (context)
			{
				$scope.reloadNode(context.params['node']);
			});


			var cacheKey = cacheKeyPrefix + website.id,
				shouldLoad;

			cache = $cacheFactory.get(cacheKey);

			shouldLoad = angular.isUndefined(cache);
			if (shouldLoad)
			{
				cache = $cacheFactory(cacheKey);
				cache.put('collection', []);
				cache.put('tree', {});
			}

			treeDb = cache.get('tree');
			$scope.browseCollection = cache.get('collection');

			if (shouldLoad)
			{
				toggleNode(getNodeInfo($scope.currentWebsite));
			}

		}


		REST.action('collectionItems', { 'code': 'Rbs_Website_AvailablePageFunctions' }).then(function (result)
		{
			$scope.allFunctions = result.items;
			$scope.allFunctions['Rbs_Website_Section'] = { "label": i18n.trans('m.rbs.website.adminjs.function_index_page | ucf') };

			$scope.$watch('currentWebsite', function (website)
			{
				if (website) {
					selectWebsite(website);
				}
			});
		});



		function getNodeIndex (nodeInfo)
		{
			var i, doc, id;
			for (i=0 ; i<$scope.browseCollection.length ; i++)
			{
				doc = $scope.browseCollection[i];
				id = doc.hasOwnProperty('__treeId') ? doc.__treeId : doc.id;
				if (id === nodeInfo.id) {
					return i;
				}
			}
			return -1;
		}


		function loadNode (nodeInfo)
		{
			console.log(nodeInfo.id);
			var query = Query.simpleQuery('Rbs_Website_SectionPageFunction', 'section', nodeInfo.id);
			query.offset = 0;
			query.limit = 100; // FIXME ?

			$q.all([
					REST.treeChildren(nodeInfo.document, {limit: 100, column:['functions','title']}),
					REST.query(query, {column: ['page', 'section', 'functionCode']})
				]).then(

				// Success
				function (results)
				{
					// Children in tree
					nodeInfo.children = results[0].resources;

					var functionsRow = {
						'model' : 'Functions',
						'id' : 'FunctionsFor:' + nodeInfo.id,
						'label' : 'Functions',
						'functions' : []
					};

					// Functions
					angular.forEach(results[1].resources, function (func)
					{
						if ($scope.allFunctions.hasOwnProperty(func.functionCode)) {
							func.functionLabel = $scope.allFunctions[func.functionCode].label;
						}
						if (func.page && func.page.model === "Rbs_Website_FunctionalPage")
						{
							functionsRow.functions.push(func);
						}
					});

					if (functionsRow.functions.length) {
						nodeInfo.children.push(functionsRow);
					}

					angular.forEach(nodeInfo.children, function (c) {
						getNodeInfo(c).level = nodeInfo.level + 1;
					});
					expandNode(nodeInfo);
				},

				// Error
				function (error) {
					$scope.loadingFunctions = false;
					NotificationCenter.error("Fonctions", ErrorFormatter.format(error));
				}
			);
		}



		function expandNode (nodeInfo, index)
		{
			var childInfo,
				count = 0;

			if (angular.isUndefined(index)) {
				index = Math.max(0, getNodeIndex(nodeInfo));
			}

			angular.forEach(nodeInfo.children, function (c)
			{
				count++;
				$scope.browseCollection.splice(++index, 0, c);
				childInfo = getNodeInfo(c);
				if (childInfo.open) {
					var excount = expandNode(childInfo, index);
					count += excount;
					index += excount;
				}
			});
			nodeInfo.open = true;
			return count;
		}


		function collapseNode (nodeInfo)
		{
			var index = Math.max(0, getNodeIndex(nodeInfo));
			$scope.browseCollection.splice(index+1, getDescendantsCount(nodeInfo));
			delete nodeInfo.open;
		}


		function getDescendantsCount (nodeInfo)
		{
			var count = 0, childInfo;
			if (nodeInfo.open)
			{
				count = nodeInfo.children.length;
				angular.forEach(nodeInfo.children, function (c)
				{
					childInfo = getNodeInfo(c);
					count += getDescendantsCount(childInfo);
				});
			}
			return count;
		}


		function getNodeInfo (doc)
		{
			var id;

			if (angular.isObject(doc)) {
				id = doc.hasOwnProperty('__treeId') ? doc.__treeId : doc.id;
			} else {
				id = doc;
			}
			if (! treeDb.hasOwnProperty(id)) {
				treeDb[id] = {
					children : null,
					document : doc,
					id : id,
					level : 0
				};
			}
			return treeDb[id];
		}


		function toggleNode (nodeInfo)
		{
			if (nodeInfo.open) {
				collapseNode(nodeInfo);
			}
			else {
				if (nodeInfo.children !== null) {
					expandNode(nodeInfo);
				}
				else {
					loadNode(nodeInfo);
				}
			}
		}


		function getParent (index)
		{
			var i = index - 1,
				level = getNodeInfo($scope.browseCollection[index]).level;

			if (level === 1) {
				return $scope.currentWebsite;
			}

			while (i >= 0) {
				if (getNodeInfo($scope.browseCollection[i]).level < level) {
					return $scope.browseCollection[i];
				}
				i--;
			}

			return null;
		}


		function setListBusy (value) {
			$scope.$broadcast('Change:DocumentList:DLRbsWebsiteBrowser:call', {'method':value?'setBusy':'setNotBusy'});
		}


		// This object is exposed in the <rbs-document-list/> ('extend' attribute).
		$scope.browser =
		{
			toggleNode : function (doc)
			{
				return toggleNode(getNodeInfo(doc));
			},

			isTopic : function (doc)
			{
				return doc.is && doc.is('Rbs_Website_Topic');
			},

			isPage : function (doc)
			{
				return doc.is && (doc.is('Rbs_Website_StaticPage') || doc.is('Rbs_Website_FunctionalPage'));
			},

			isFunction : function (doc)
			{
				return doc.model === 'Functions';
			},

			showFunctions : {},

			getNodeInfo : getNodeInfo,

			isNodeOpen : function (doc)
			{
				return getNodeInfo(doc).open === true;
			},

			getRowStyle : function (doc) {
				var lvl = getNodeInfo(doc).level-1;
				if (lvl === 0) {
					return {};
				}
				return { backgroundColor: 'rgb('+(249-lvl*6)+','+(252-lvl*5)+','+(255-lvl*4)+')' };
			},

			isIndexPage : function (page)
			{
				return page.functions && page.functions.indexOf(INDEX_FUNCTION_CODE) !== -1;
			},

			setIndexPage : function (page, rowIndex)
			{
				if (this.isIndexPage(page)) {
					return;
				}

				var section = getParent(rowIndex);

				setListBusy(true);
				// Retrieve "index" SectionPageFunction for the current section (if any).
				REST.query(Query.simpleQuery('Rbs_Website_SectionPageFunction', {
					'section' : section.id,
					'functionCode' : INDEX_FUNCTION_CODE
				}), {'column':['page']}).then(

					// Success
					function (spf)
					{
						// SectionPageFunction exists: set new page on it.
						if (spf.resources.length === 1) {
							spf = spf.resources[0];
							// Nothing to do it the index page is the same.
							if (spf.page && spf.page.id === page.id) {
								return;
							}
						}
						// SectionPageFunction does NOT exist: create a new one.
						else {
							spf = REST.newResource('Rbs_Website_SectionPageFunction');
							spf.section = section;
							spf.functionCode = INDEX_FUNCTION_CODE;
						}
						spf.page = page.id;

						REST.save(spf).then(
							// Success
							function () {
								setListBusy(false);
								$scope.reloadNode(section);
							},
							// Error
							function (error)
							{
								setListBusy(false);
								NotificationCenter.error(i18n.trans('m.rbs.website.adminjs.index_page_error | ucf'), error);
							}
						);
					},

					// Error
					function (error)
					{
						setListBusy(false);
						NotificationCenter.error(i18n.trans('m.rbs.website.adminjs.index_page_error | ucf'), error);
					}
				);
			},

			getDocumentErrors : function (doc)
			{
				if (! Utils.isModel(doc, 'Rbs_Website_StaticPage')) {
					return null;
				}
				if (this.isIndexPage(doc) && ! Utils.hasStatus(doc, 'PUBLISHABLE')) {
					return [
						"UNPUBLISHED_INDEX_PAGE_"
					];
				}
				return null;
			}
		};


		$scope.reloadNode = function (doc)
		{
			var node = getNodeInfo(doc);
			collapseNode(node);
			node.children = null;
			toggleNode(node);
		};

	}


	StructureController.$inject = [
		'$scope', '$q',
		'RbsChange.ErrorFormatter', 'RbsChange.i18n', 'RbsChange.REST', 'RbsChange.Query',
		'RbsChange.NotificationCenter', 'RbsChange.Utils', '$cacheFactory', 'RbsChange.Navigation'
	];
	app.controller('Rbs_Website_StructureController', StructureController);




	/**
	 * @param $scope
	 * @param Query
	 * @constructor
	 */
	function MenusController($scope, Query)
	{
		$scope.$watch('currentWebsite', function (website)
		{
			if (website) {
				$scope.listLoadQuery = Query.simpleQuery('Rbs_Website_Menu', 'website', website.id);
			}
		});
	}


	MenusController.$inject = ['$scope', 'RbsChange.Query'];
	app.controller('Rbs_Website_MenusController', MenusController);


})();